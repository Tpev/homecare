<?php

namespace App\Services\Caregiver;

use App\Models\CaregiverCertification;
use App\Models\CaregiverCertificationType;
use App\Models\CaregiverExperienceType;
use App\Models\CaregiverProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CaregiverBackgroundService
{
    public const SCHEMA_VERSION = 1;

    public const NONE = 'none';

    /**
     * @return array<string, mixed>
     */
    public function formState(CaregiverProfile $profile): array
    {
        $profile->loadMissing(['careExperiences', 'certifications.type']);

        $experienceIds = $profile->careExperiences->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        if ($experienceIds === [] && $profile->care_experience_answered_at) {
            $experienceIds = [self::NONE];
        }

        $certificationIds = $profile->certifications->pluck('caregiver_certification_type_id')
            ->map(fn ($id) => (int) $id)->values()->all();
        if ($certificationIds === [] && $profile->certifications_answered_at) {
            $certificationIds = [self::NONE];
        }

        $details = [];
        $records = [];
        foreach ($profile->certifications as $certification) {
            $typeId = (int) $certification->caregiver_certification_type_id;
            $details[$typeId] = [
                'custom_name' => (string) $certification->custom_name,
                'issuer' => (string) $certification->issuer,
                'issuing_state' => (string) $certification->issuing_state,
                'expires_at' => $certification->expires_at?->format('Y-m-d') ?? '',
            ];
            $records[$typeId] = [
                'id' => (int) $certification->id,
                'has_document' => filled($certification->document_path),
                'name' => (string) ($certification->document_original_name ?: 'Supporting document'),
                'status' => (string) $certification->verification_status,
                'expired' => $certification->isExpired(),
                'rejection_reason' => (string) $certification->rejection_reason,
            ];
        }

        return [
            'selected_experiences' => $experienceIds,
            'experience_notes' => (string) $profile->care_experience_notes,
            'selected_certifications' => $certificationIds,
            'certification_details' => $details,
            'existing_records' => $records,
        ];
    }

    /**
     * Store uploads before the database transaction. Call discardUploads() if
     * the transaction fails and deletePaths() with the returned obsolete paths
     * only after it commits.
     *
     * @param  array<int|string, UploadedFile|null>  $documents
     * @param  array<int, int>  $selectedTypeIds
     * @return array<int, array{path:string, original_name:string, mime:?string, size:int}>
     */
    public function storeUploads(array $documents, array $selectedTypeIds): array
    {
        $selected = array_flip($selectedTypeIds);
        $stored = [];

        foreach ($documents as $typeId => $document) {
            $typeId = (int) $typeId;
            if (! $document instanceof UploadedFile || ! isset($selected[$typeId])) {
                continue;
            }

            $path = $document->store('caregiver-certifications', 'local');
            if (! is_string($path) || $path === '') {
                $this->discardUploads($stored);
                throw new RuntimeException('The certification document could not be stored.');
            }

            $stored[$typeId] = [
                'path' => $path,
                'original_name' => basename($document->getClientOriginalName()),
                'mime' => $document->getMimeType(),
                'size' => (int) $document->getSize(),
            ];
        }

        return $stored;
    }

    /**
     * @param  array<int, array{path:string}>  $uploads
     */
    public function discardUploads(array $uploads): void
    {
        $this->deletePaths(array_column($uploads, 'path'));
    }

    /**
     * This method must be called inside the caller's database transaction.
     *
     * @param  array<int|string>  $selectedExperiences
     * @param  array<int|string>  $selectedCertifications
     * @param  array<int|string, array<string, mixed>>  $details
     * @param  array<int, array{path:string, original_name:string, mime:?string, size:int}>  $uploads
     * @param  array<int|string, bool>  $removeDocuments
     * @return array{changed:bool, obsolete_paths:array<int, string>, snapshot:array<string, mixed>}
     */
    public function syncWithinTransaction(
        CaregiverProfile $profile,
        array $selectedExperiences,
        ?string $experienceNotes,
        array $selectedCertifications,
        array $details,
        array $uploads,
        array $removeDocuments,
    ): array {
        $before = $this->snapshot($profile->fresh(['careExperiences', 'certifications.type']));
        $experienceIds = $this->numericSelections($selectedExperiences);
        $certificationIds = $this->numericSelections($selectedCertifications);
        $obsoletePaths = [];

        $profile->careExperiences()->sync($experienceIds);
        $profile->forceFill([
            'care_experience_notes' => $this->nullableTrim($experienceNotes),
            'care_experience_answered_at' => $profile->care_experience_answered_at ?: now(),
            'certifications_answered_at' => $profile->certifications_answered_at ?: now(),
        ])->save();

        $existing = $profile->certifications()->get()->keyBy('caregiver_certification_type_id');
        foreach ($certificationIds as $typeId) {
            /** @var CaregiverCertification|null $certification */
            $certification = $existing->get($typeId);
            $detail = (array) ($details[$typeId] ?? $details[(string) $typeId] ?? []);
            $attributes = [
                'custom_name' => $this->nullableTrim($detail['custom_name'] ?? null),
                'issuer' => $this->nullableTrim($detail['issuer'] ?? null),
                'issuing_state' => $this->nullableUpper($detail['issuing_state'] ?? null),
                'expires_at' => $this->nullableTrim($detail['expires_at'] ?? null),
            ];

            $meaningfulChange = ! $certification || collect($attributes)->contains(
                fn ($value, string $key) => $this->comparable($certification?->{$key}) !== $this->comparable($value)
            );

            $removeDocument = (bool) ($removeDocuments[$typeId] ?? $removeDocuments[(string) $typeId] ?? false);
            $upload = $uploads[$typeId] ?? null;
            $resultingDocumentPath = $certification?->document_path;

            if ($upload) {
                if ($certification?->document_path && $certification->document_path !== $upload['path']) {
                    $obsoletePaths[] = $certification->document_path;
                }
                $resultingDocumentPath = $upload['path'];
                $attributes += [
                    'document_path' => $upload['path'],
                    'document_original_name' => $upload['original_name'],
                    'document_mime' => $upload['mime'],
                    'document_size' => $upload['size'],
                ];
            } elseif ($removeDocument) {
                if ($certification?->document_path) {
                    $obsoletePaths[] = $certification->document_path;
                }
                $resultingDocumentPath = null;
                $attributes += [
                    'document_path' => null,
                    'document_original_name' => null,
                    'document_mime' => null,
                    'document_size' => null,
                ];
            }

            if (! $certification || $meaningfulChange || $upload || $removeDocument) {
                $attributes += [
                    'verification_status' => $resultingDocumentPath
                        ? CaregiverCertification::STATUS_PENDING
                        : CaregiverCertification::STATUS_SELF_REPORTED,
                    'verified_by_user_id' => null,
                    'verified_at' => null,
                    'rejection_reason' => null,
                ];
            }

            $profile->certifications()->updateOrCreate(
                ['caregiver_certification_type_id' => $typeId],
                $attributes,
            );
        }

        $removed = $existing->reject(fn (CaregiverCertification $certification) => in_array(
            (int) $certification->caregiver_certification_type_id,
            $certificationIds,
            true,
        ));
        foreach ($removed as $certification) {
            if ($certification->document_path) {
                $obsoletePaths[] = $certification->document_path;
            }
            $certification->delete();
        }

        $snapshot = $this->snapshot($profile->fresh(['careExperiences', 'certifications.type']));

        return [
            'changed' => $before !== $snapshot,
            'obsolete_paths' => array_values(array_unique(array_filter($obsoletePaths))),
            'snapshot' => $snapshot,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(CaregiverProfile $profile): array
    {
        $profile->loadMissing(['careExperiences', 'certifications.type']);

        return [
            'schema_version' => $profile->care_background_schema_version,
            'experience_answered_at' => $profile->care_experience_answered_at?->toIso8601String(),
            'experience_type_ids' => $profile->careExperiences->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all(),
            'experience_notes' => $profile->care_experience_notes,
            'certifications_answered_at' => $profile->certifications_answered_at?->toIso8601String(),
            'certifications' => $profile->certifications
                ->sortBy('caregiver_certification_type_id')
                ->map(fn (CaregiverCertification $certification): array => [
                    'type_id' => (int) $certification->caregiver_certification_type_id,
                    'type' => $certification->type?->slug,
                    'name' => $certification->custom_name,
                    'issuer' => $certification->issuer,
                    'issuing_state' => $certification->issuing_state,
                    'expires_at' => $certification->expires_at?->format('Y-m-d'),
                    'has_document' => filled($certification->document_path),
                    'verification_status' => $certification->verification_status,
                    'verified_at' => $certification->verified_at?->toIso8601String(),
                ])->values()->all(),
        ];
    }

    /**
     * @param  array<int, string|null>  $paths
     */
    public function deletePaths(array $paths): void
    {
        $safePaths = collect($paths)
            ->filter(fn ($path) => is_string($path) && str_starts_with($path, 'caregiver-certifications/'))
            ->unique()
            ->values()
            ->all();

        if ($safePaths !== []) {
            Storage::disk('local')->delete($safePaths);
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function experienceOptions(): array
    {
        return CaregiverExperienceType::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->get(['id', 'slug', 'label', 'description'])
            ->toArray();
    }

    /** @return array<int, array<string, mixed>> */
    public function certificationOptions(): array
    {
        return CaregiverCertificationType::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->get(['id', 'slug', 'label'])
            ->toArray();
    }

    /** @param array<int|string> $selections @return array<int, int> */
    public function numericSelections(array $selections): array
    {
        return collect($selections)
            ->reject(fn ($value) => (string) $value === self::NONE)
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableUpper(mixed $value): ?string
    {
        $value = $this->nullableTrim($value);

        return $value ? strtoupper($value) : null;
    }

    private function comparable(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $this->nullableTrim($value);
    }
}
