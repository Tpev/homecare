<?php

namespace App\Services\CareRequests;

use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestInvitation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CareRequestExpirationService
{
    public function eligible(?CarbonInterface $at = null): Builder
    {
        $cutoff = CarbonImmutable::instance($at ?? now())->subRealHours(24);

        return CareRequest::query()
            ->whereIn('status', [CareRequest::STATUS_OPEN, CareRequest::STATUS_DRAFT])
            ->where('request_type', CareRequest::TYPE_ONE_TIME)
            ->where('is_system_generated', false)
            ->whereNotNull('requested_end_at')
            ->where('requested_end_at', '<=', $cutoff)
            // A known end is sufficient; never infer one from the start or posting date.
            ->where(fn (Builder $query) => $query->whereNull('requested_start_at')
                ->orWhereColumn('requested_end_at', '>', 'requested_start_at'))
            ->whereNull('care_plan_id')
            ->whereNull('care_arrangement_id')
            ->whereNull('first_hire_at')
            ->whereDoesntHave('sourceCarePlans')
            ->whereDoesntHave('booking')
            ->whereDoesntHave('releasedBookings')
            ->whereDoesntHave('applications', fn (Builder $query) => $query->where('status', CareRequestApplication::STATUS_HIRED));
    }

    /** Includes the existing backlog; safe to run repeatedly. */
    public function expireDue(?CarbonInterface $at = null): int
    {
        $at = CarbonImmutable::instance($at ?? now());
        $expired = 0;

        $this->eligible($at)->select('id')->chunkById(200, function ($requests) use ($at, &$expired): void {
            foreach ($requests as $candidate) {
                $expired += (int) DB::transaction(function () use ($candidate, $at): bool {
                    // Hiring locks this same request. Recheck after acquiring the lock so
                    // a booking or reschedule made since the scan always wins.
                    $request = CareRequest::query()->lockForUpdate()->find($candidate->id);
                    if (! $request || ! $this->eligible($at)->whereKey($request->id)->exists()) {
                        return false;
                    }

                    $request->forceFill(['status' => CareRequest::STATUS_EXPIRED])->saveQuietly();
                    $request->invitations()->where('status', CareRequestInvitation::STATUS_PENDING)->update([
                        'status' => CareRequestInvitation::STATUS_EXPIRED,
                        'expires_at' => $at,
                    ]);

                    // Preserve applications, conversations, and all financial records.
                    return true;
                }, 3);
            }
        });

        return $expired;
    }
}
