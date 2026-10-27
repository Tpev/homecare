<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class AdminCareRequestUpcomingPaginator
{
    public function __construct(private AdminCareRequestSchedule $schedules) {}

    public function paginate(Builder $query, int $perPage, int $page): LengthAwarePaginator
    {
        $ranked = [];

        // Weekly schedules need calendar and timezone calculations. Rank the entire
        // filtered result before pagination, loading schedule data in bounded chunks.
        (clone $query)
            ->setEagerLoads([])
            ->with(AdminCareRequestSchedule::relations())
            ->select([
                'id', 'care_plan_id', 'request_type', 'status',
                'requested_start_at', 'requested_end_at',
                'recurring_days', 'recurring_start_time', 'recurring_end_time',
                'recurring_schedule', 'recurring_starts_on', 'recurring_ends_on',
            ])
            ->reorder()
            ->chunkById(250, function ($requests) use (&$ranked): void {
                foreach ($requests as $request) {
                    $ranked[] = [
                        'id' => $request->id,
                        'next' => $this->schedules->present($request)['next_at']?->getTimestamp(),
                    ];
                }
            });

        usort($ranked, static fn (array $a, array $b): int => (($a['next'] ?? PHP_INT_MAX) <=> ($b['next'] ?? PHP_INT_MAX))
                ?: ($b['id'] <=> $a['id'])
        );

        $ids = array_column(array_slice($ranked, ($page - 1) * $perPage, $perPage), 'id');
        $positions = array_flip($ids);
        $items = $ids === []
            ? $query->getModel()->newCollection()
            : (clone $query)->whereKey($ids)->reorder()->get()
                ->sortBy(fn ($request): int => $positions[$request->id])->values();

        return new LengthAwarePaginator($items, count($ranked), $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'query' => request()->query(),
        ]);
    }
}
