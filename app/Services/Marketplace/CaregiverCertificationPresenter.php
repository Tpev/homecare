<?php

namespace App\Services\Marketplace;

use App\Models\CaregiverCertification;
use App\Models\CaregiverProfile;
use App\Support\CaregiverCertificationCriteria;
use Illuminate\Support\Collection;

class CaregiverCertificationPresenter
{
    /**
     * @return array{tags:array<int, array{type_id:int,type_slug:string,label:string,status_label:string,verified:bool,matches_filter:bool,sort_order:int}>,hidden_count:int,total:int}
     */
    public function summary(
        CaregiverProfile $profile,
        ?CaregiverCertificationCriteria $criteria = null,
        int $limit = 3,
    ): array {
        $criteria ??= CaregiverCertificationCriteria::empty();

        /** @var Collection<int, CaregiverCertification> $certifications */
        $certifications = $profile->relationLoaded('publicSearchCertifications')
            ? $profile->publicSearchCertifications
            : ($profile->relationLoaded('publicCertifications')
                ? $profile->publicCertifications
                : $profile->publicSearchCertifications()->get());

        $tags = $certifications
            ->filter(fn (CaregiverCertification $certification): bool => $certification->isCurrent())
            ->map(function (CaregiverCertification $certification) use ($criteria): array {
                $typeId = (int) $certification->caregiver_certification_type_id;

                return [
                    'type_id' => $typeId,
                    'type_slug' => (string) $certification->type?->slug,
                    'label' => $certification->displayName(),
                    'status_label' => $certification->publicStatusLabel(),
                    'verified' => $certification->isCurrentlyVerified(),
                    'matches_filter' => $criteria->selectedPosition($typeId) !== null,
                    'sort_order' => (int) ($certification->type?->sort_order ?? 999),
                ];
            })
            ->filter(fn (array $tag): bool => filled($tag['label']))
            ->unique(fn (array $tag): string => mb_strtolower($tag['type_slug'].'|'.$tag['label']))
            ->sort(function (array $left, array $right) use ($criteria): int {
                $leftSelected = $criteria->selectedPosition($left['type_id']);
                $rightSelected = $criteria->selectedPosition($right['type_id']);

                if ($leftSelected !== null || $rightSelected !== null) {
                    if ($leftSelected === null) {
                        return 1;
                    }
                    if ($rightSelected === null) {
                        return -1;
                    }

                    return $leftSelected <=> $rightSelected;
                }

                if ($left['verified'] !== $right['verified']) {
                    return $left['verified'] ? -1 : 1;
                }

                return [$left['sort_order'], $left['label']] <=> [$right['sort_order'], $right['label']];
            })
            ->values();

        $selectedMatches = $tags->where('matches_filter', true)->count();
        $visibleLimit = $criteria->hasSelections()
            ? max(max(0, $limit), $selectedMatches)
            : max(0, $limit);

        return [
            'tags' => $tags->take($visibleLimit)->values()->all(),
            'hidden_count' => max(0, $tags->count() - $visibleLimit),
            'total' => $tags->count(),
        ];
    }
}
