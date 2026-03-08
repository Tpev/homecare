<?php

namespace App\Support;

use App\Models\CareRequestInvitation;
use App\Models\CaregiverProfile;
use Illuminate\Support\Carbon;

class CaregiverResponseMetrics
{
    public static function recomputeForCaregiver(int $caregiverUserId): void
    {
        $windowStart = Carbon::now()->subDays(30);

        $base = CareRequestInvitation::query()
            ->where('caregiver_user_id', $caregiverUserId)
            ->where('created_at', '>=', $windowStart);

        $received = (clone $base)->count();
        $responded = (clone $base)->whereNotNull('responded_at')->count();

        $responseRate = $received > 0
            ? round(($responded / $received) * 100, 2)
            : null;

        $avgMinutes = (int) round((clone $base)
            ->whereNotNull('responded_at')
            ->get(['created_at', 'responded_at'])
            ->avg(fn (CareRequestInvitation $invitation) => $invitation->responded_at->diffInMinutes($invitation->created_at)) ?? 0);

        CaregiverProfile::query()
            ->where('user_id', $caregiverUserId)
            ->update([
                'invite_response_rate' => $responseRate,
                'avg_invite_response_minutes' => $responded > 0 ? $avgMinutes : null,
                'response_metrics_updated_at' => now(),
            ]);
    }
}
