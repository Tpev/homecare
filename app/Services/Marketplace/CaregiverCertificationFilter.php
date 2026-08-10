<?php

namespace App\Services\Marketplace;

use App\Models\CaregiverCertification;
use App\Models\CaregiverProfile;
use App\Support\CaregiverCertificationCriteria;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CaregiverCertificationFilter
{
    public function apply(Builder $profiles, CaregiverCertificationCriteria $criteria): Builder
    {
        foreach ($criteria->typeIds() as $typeId) {
            $profiles->whereHas('certifications', function (Builder $certifications) use ($criteria, $typeId): void {
                $certifications
                    ->where('caregiver_certification_type_id', $typeId)
                    ->current()
                    ->when(
                        $criteria->requiresVerification(),
                        fn (Builder $query): Builder => $query->verified(),
                    );
            });
        }

        return $profiles;
    }

    /**
     * Add the public, current certification fields to an existing text-search OR group.
     */
    public function orWhereTextMatches(Builder $profiles, string $term): Builder
    {
        $safeTerm = str_replace(['%', '_'], '', trim($term));
        if (mb_strlen($safeTerm) < 2) {
            return $profiles;
        }

        return $profiles->orWhereHas('certifications', function (Builder $certifications) use ($safeTerm): void {
            $certifications
                ->current()
                ->where(function (Builder $credentialQuery) use ($safeTerm): void {
                    $credentialQuery
                        ->where('custom_name', 'like', '%'.$safeTerm.'%')
                        ->orWhereHas('type', function (Builder $typeQuery) use ($safeTerm): void {
                            $typeQuery
                                ->where('active', true)
                                ->where(function (Builder $labelQuery) use ($safeTerm): void {
                                    $labelQuery
                                        ->where('label', 'like', '%'.$safeTerm.'%')
                                        ->orWhere('slug', 'like', '%'.$safeTerm.'%');
                                });
                        });
                });
        });
    }

    public function matches(CaregiverProfile $profile, CaregiverCertificationCriteria $criteria): bool
    {
        if (! $criteria->hasSelections()) {
            return true;
        }

        /** @var Collection<int, CaregiverCertification> $certifications */
        $certifications = $profile->relationLoaded('publicSearchCertifications')
            ? $profile->publicSearchCertifications
            : $profile->publicSearchCertifications()->get();

        foreach ($criteria->typeIds() as $typeId) {
            $matchesType = $certifications->contains(
                fn (CaregiverCertification $certification): bool => (int) $certification->caregiver_certification_type_id === $typeId
                    && $certification->isCurrent()
                    && (! $criteria->requiresVerification() || $certification->isCurrentlyVerified()),
            );

            if (! $matchesType) {
                return false;
            }
        }

        return true;
    }
}
