<?php

namespace App\Services\Partners;

use App\Models\Lead;
use App\Support\PartnerOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class PartnerLeadInventory
{
    public function __construct(private readonly PartnerLeadService $delivery) {}

    /**
     * Statistics describe the search/category selection before the status filter.
     * A partially delivered lead can count as both available and sent.
     *
     * @return array{leads: LengthAwarePaginator, requestedCategories: array, sentCategories: array, blockedReasons: array, stats: array}
     */
    public function page(
        string $search = '',
        string $category = 'all',
        string $status = 'all',
        int $page = 1,
        int $perPage = 20,
    ): array {
        $search = mb_substr(trim($search), 0, 200);
        $category = array_key_exists($category, PartnerOptions::categories()) ? $category : 'all';
        $status = in_array($status, ['available', 'sent', 'no_services'], true) ? $status : 'all';
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $query = $this->searchQuery($search);
        $matchingIds = [];
        $stats = ['all' => 0, 'available' => 0, 'sent' => 0, 'no_services' => 0];

        // Imported answers have several historical shapes and explicit-empty
        // precedence. Use the same parser as delivery before paginating, rather
        // than guessing at JSON paths or dropping matches after pagination.
        (clone $query)->select(['id', 'lead_type', 'email', 'phone', 'data', 'do_not_contact_at'])
            ->chunkById(200, function ($leads) use ($category, $status, &$matchingIds, &$stats): void {
                $sent = $this->delivery->sentCategoriesForLeads($leads);
                foreach ($leads as $lead) {
                    $requested = $this->delivery->categoriesFor($lead);
                    if ($category !== 'all' && ! in_array($category, $requested, true)) {
                        continue;
                    }

                    $requestedInScope = $category === 'all' ? $requested : [$category];
                    $sentCategories = array_keys($sent[$lead->id] ?? []);
                    $states = [
                        'all' => true,
                        'available' => $this->delivery->unavailabilityReason($lead) === null
                            && array_diff($requestedInScope, $sentCategories) !== [],
                        'sent' => $category === 'all'
                            ? $sentCategories !== []
                            : in_array($category, $sentCategories, true),
                        'no_services' => $requested === [],
                    ];
                    foreach ($states as $key => $matches) {
                        if ($matches) {
                            $stats[$key]++;
                        }
                    }
                    if ($states[$status]) {
                        $matchingIds[] = $lead->id;
                    }
                }
            });

        // Only IDs survive the scan; full lead data and display metadata are
        // loaded for the requested page. Integer IN avoids driver binding limits.
        $leads = $query->whereIntegerInRaw('id', $matchingIds)
            ->latest('submitted_at')->latest('id')->paginate($perPage, ['*'], 'page', $page);
        $requestedCategories = [];
        $blockedReasons = [];
        foreach ($leads as $lead) {
            $requestedCategories[$lead->id] = $this->delivery->categoriesFor($lead);
            $blockedReasons[$lead->id] = $this->delivery->unavailabilityReason($lead);
        }

        return [
            'leads' => $leads,
            'requestedCategories' => $requestedCategories,
            'sentCategories' => $this->delivery->sentCategoriesForLeads($leads->getCollection()),
            'blockedReasons' => $blockedReasons,
            'stats' => $stats,
        ];
    }

    private function searchQuery(string $search): Builder
    {
        return Lead::query()->where('lead_type', Lead::TYPE_FAMILY)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $match) use ($search): void {
                    if (ctype_digit($search)) {
                        $match->orWhere('id', $search);
                    }
                    foreach (['name', 'email', 'phone', 'location', 'zip', 'source_detail'] as $column) {
                        $match->orWhere($column, 'like', '%'.$search.'%');
                    }
                });
            });
    }
}
