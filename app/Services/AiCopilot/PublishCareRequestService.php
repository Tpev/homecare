<?php

namespace App\Services\AiCopilot;

use App\Models\AiRequestSession;
use App\Models\CareRequest;
use App\Support\FunnelTracker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishCareRequestService
{
    public function __construct(
        private readonly MissingFieldsResolver $missingFieldsResolver
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function publish(AiRequestSession $session): CareRequest
    {
        $draft = is_array($session->draft_json) ? $session->draft_json : [];
        $missing = $this->missingFieldsResolver->requiredMissing($draft);
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'draft' => ['Missing required fields: '.implode(', ', $missing)],
            ]);
        }

        $request = DB::transaction(function () use ($session, $draft) {
            $isOneTime = ($draft['request_type'] ?? null) === CareRequest::TYPE_ONE_TIME;

            $careRequest = CareRequest::query()->create([
                'family_account_id' => $session->family_account_id,
                'family_user_id' => $session->family_user_id,
                'created_by_user_id' => auth()->id() ?: $session->family_user_id,
                'title' => (string) $draft['title'],
                'additional_info' => $draft['additional_info'] ?? null,
                'scope_of_work' => (string) $draft['scope_of_work'],
                'time_expectations' => (string) $draft['time_expectations'],
                'home_access_notes' => (string) $draft['home_access_notes'],
                'preferred_response_hours' => (int) ($draft['preferred_response_hours'] ?? 12),
                'status' => CareRequest::STATUS_OPEN,
                'request_type' => (string) $draft['request_type'],
                'requested_start_at' => $isOneTime ? $this->parseDateTime($draft['requested_start_at'] ?? null) : null,
                'requested_end_at' => $isOneTime ? $this->parseDateTime($draft['requested_end_at'] ?? null) : null,
                'recurring_days' => $isOneTime ? null : ($draft['recurring_days'] ?? []),
                'recurring_start_time' => $isOneTime ? null : ($draft['recurring_start_time'] ?? null),
                'recurring_end_time' => $isOneTime ? null : ($draft['recurring_end_time'] ?? null),
                'recurring_starts_on' => $isOneTime ? null : ($draft['recurring_starts_on'] ?? null),
                'recurring_ends_on' => $isOneTime ? null : ($draft['recurring_ends_on'] ?? null),
                'address_line1' => (string) $draft['address_line1'],
                'address_line2' => $draft['address_line2'] ?? null,
                'city' => (string) $draft['city'],
                'state' => strtoupper((string) $draft['state']),
                'zip' => (string) $draft['zip'],
            ]);

            $taskIds = collect($draft['task_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
            $careRequest->tasks()->sync(collect($taskIds)->mapWithKeys(fn ($id) => [$id => ['task_note' => null]])->all());

            $recipient = $draft['recipient'] ?? [];
            $relationship = (string) ($recipient['relationship_to_family'] ?? '');
            $careRequest->recipient()->create([
                'recipient_is_requester' => (bool) ($recipient['recipient_is_requester'] ?? strtolower(trim($relationship)) === 'self'),
                'full_name' => (string) ($recipient['full_name'] ?? ''),
                'date_of_birth' => $recipient['date_of_birth'] ?? null,
                'gender' => $recipient['gender'] ?? null,
                'mobility_level' => $recipient['mobility_level'] ?? null,
                'relationship_to_family' => $relationship,
                'care_notes' => $recipient['care_notes'] ?? null,
            ]);

            if ((bool) ($draft['include_third_party_contact'] ?? false)) {
                $contact = $draft['third_party_contact'] ?? [];
                $careRequest->thirdPartyContact()->create([
                    'full_name' => (string) ($contact['full_name'] ?? ''),
                    'relationship_to_recipient' => (string) ($contact['relationship_to_recipient'] ?? ''),
                    'phone' => (string) ($contact['phone'] ?? ''),
                    'email' => $contact['email'] ?? null,
                ]);
            }

            $session->forceFill([
                'status' => AiRequestSession::STATUS_PUBLISHED,
                'published_care_request_id' => $careRequest->id,
            ])->save();

            return $careRequest;
        });

        FunnelTracker::track('care_request_published_ai', $session->family, $request, [
            'request_type' => $request->request_type,
            'quality_score' => $session->quality_score,
        ]);

        return $request;
    }

    private function parseDateTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value);
    }
}
