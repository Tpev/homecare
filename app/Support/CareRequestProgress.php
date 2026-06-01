<?php

namespace App\Support;

use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use Illuminate\Support\Carbon;

class CareRequestProgress
{
    public static function postedAgoLabel(CareRequest $request): string
    {
        return $request->created_at?->diffForHumans(now(), [
            'parts' => 2,
            'short' => true,
            'syntax' => Carbon::DIFF_RELATIVE_TO_NOW,
        ]) ?? '-';
    }

    public static function firstResponseLabel(CareRequest $request): string
    {
        if (! $request->first_applicant_at) {
            return 'Waiting for first applicant';
        }

        return self::minutesLabel(self::elapsedMinutes($request->created_at, $request->first_applicant_at));
    }

    public static function firstHireLabel(CareRequest $request): string
    {
        if (! $request->first_hire_at) {
            return 'Not hired yet';
        }

        return self::minutesLabel(self::elapsedMinutes($request->created_at, $request->first_hire_at));
    }

    public static function elapsedMinutes(?Carbon $from, ?Carbon $to): ?int
    {
        if (! $from || ! $to) {
            return null;
        }

        return max(0, intdiv(max(0, $to->getTimestamp() - $from->getTimestamp()), 60));
    }

    /**
     * @return array{title:string,action:string,tone:string}
     */
    public static function bestNextAction(CareRequest $request): array
    {
        $applicationsCount = isset($request->applications_count)
            ? (int) $request->applications_count
            : (int) $request->applications()->count();

        $pendingStatuses = [
            CareRequestApplication::STATUS_APPLIED,
            CareRequestApplication::STATUS_SHORTLISTED,
        ];

        if ($request->relationLoaded('applications')) {
            $pendingCandidates = (int) $request->applications
                ->whereIn('status', $pendingStatuses)
                ->count();
        } else {
            $pendingCandidates = (int) $request->applications()
                ->whereIn('status', $pendingStatuses)
                ->count();
        }

        if ($request->status === CareRequest::STATUS_OPEN && $applicationsCount === 0) {
            return [
                'title' => 'Waiting on applicants',
                'action' => 'Invite matching caregivers now',
                'tone' => 'amber',
            ];
        }

        if ($request->status === CareRequest::STATUS_OPEN && $pendingCandidates > 0) {
            return [
                'title' => 'Candidates are waiting',
                'action' => 'Review applicants and shortlist',
                'tone' => 'sky',
            ];
        }

        if ($request->status === CareRequest::STATUS_FILLED) {
            $booking = $request->relationLoaded('booking')
                ? $request->booking
                : $request->booking()->first();
            if ($booking && in_array($booking->status, [CareBooking::STATUS_SCHEDULED, CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED], true)) {
                return [
                    'title' => 'Shift is active',
                    'action' => 'Track shift operations',
                    'tone' => 'emerald',
                ];
            }

            if ($booking && $booking->status === CareBooking::STATUS_COMPLETED && ! $booking->family_confirmed_at) {
                return [
                    'title' => 'Timesheet waiting confirmation',
                    'action' => 'Confirm timesheet and leave review',
                    'tone' => 'amber',
                ];
            }
        }

        return [
            'title' => 'Request is up to date',
            'action' => 'Open request to review details',
            'tone' => 'slate',
        ];
    }

    public static function minutesLabel(?int $minutes): string
    {
        if ($minutes === null) {
            return '-';
        }

        if ($minutes < 60) {
            return $minutes.'m';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        if ($remaining === 0) {
            return $hours.'h';
        }

        return $hours.'h '.$remaining.'m';
    }
}
