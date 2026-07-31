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
            return 'Waiting for first caregiver';
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
                'title' => 'Waiting for caregivers',
                'action' => 'Invite matching caregivers now',
                'tone' => 'amber',
            ];
        }

        if ($request->status === CareRequest::STATUS_OPEN && $pendingCandidates > 0) {
            return [
                'title' => 'Caregivers are waiting',
                'action' => 'Review caregivers and choose one',
                'tone' => 'sky',
            ];
        }

        if ($request->status === CareRequest::STATUS_FILLED) {
            $booking = $request->relationLoaded('booking')
                ? $request->booking
                : $request->booking()->first();

            if ($booking && $booking->status === CareBooking::STATUS_SCHEDULED) {
                return [
                    'title' => 'Visit is scheduled',
                    'action' => 'Check time, address, caregiver, and help options',
                    'tone' => 'sky',
                ];
            }

            if ($booking && $booking->status === CareBooking::STATUS_IN_PROGRESS) {
                return [
                    'title' => 'Visit is happening now',
                    'action' => 'Track check-in, location, chat, and safety options',
                    'tone' => 'emerald',
                ];
            }

            if ($booking && $booking->status === CareBooking::STATUS_PAUSED) {
                return [
                    'title' => 'Visit is paused',
                    'action' => 'Watch for caregiver resume or checkout',
                    'tone' => 'amber',
                ];
            }

            if ($booking && in_array($booking->status, [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED], true) && ! $booking->family_confirmed_at) {
                return [
                    'title' => 'Hours need your approval',
                    'action' => 'Review hours and approve payment',
                    'tone' => 'amber',
                ];
            }

            if ($booking && $booking->status === CareBooking::STATUS_REVIEWED) {
                return [
                    'title' => 'Visit is complete',
                    'action' => 'Review receipt, notes, and rebook if needed',
                    'tone' => 'emerald',
                ];
            }

            if ($booking && $booking->status === CareBooking::STATUS_CANCELLED) {
                return [
                    'title' => 'Visit was cancelled',
                    'action' => 'Review details or book care again',
                    'tone' => 'slate',
                ];
            }

            if ($booking && $booking->status === CareBooking::STATUS_DISPUTED) {
                return [
                    'title' => 'Support is reviewing this visit',
                    'action' => 'Open visit details or chat with support',
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

    /**
     * @return array{
     *     key:string,
     *     eyebrow:string,
     *     title:string,
     *     body:string,
     *     primary_tab:string,
     *     tabs:array<int,array{key:string,label:string,description:string}>,
     *     tiles:array<int,array{label:string,value:string,help:string}>
     * }
     */
    public static function familyLifecycleStage(CareRequest $request): array
    {
        $booking = $request->relationLoaded('booking')
            ? $request->booking
            : $request->booking()->first();

        $applicationsCount = $request->relationLoaded('applications')
            ? $request->applications->count()
            : $request->applications()->count();

        $pendingStatuses = [
            CareRequestApplication::STATUS_APPLIED,
            CareRequestApplication::STATUS_SHORTLISTED,
        ];

        $pendingCandidates = $request->relationLoaded('applications')
            ? $request->applications->whereIn('status', $pendingStatuses)->count()
            : $request->applications()->whereIn('status', $pendingStatuses)->count();

        $invitesCount = $request->relationLoaded('invitations')
            ? $request->invitations->count()
            : $request->invitations()->count();

        $baseTabs = [
            'overview' => ['key' => 'overview', 'label' => 'Care details', 'description' => 'Needs, address, tasks'],
            'caregiver_invites' => ['key' => 'applicants', 'label' => 'Caregivers', 'description' => 'Invite matching people'],
            'caregiver_review' => ['key' => 'applicants', 'label' => 'Caregivers', 'description' => 'Review, chat, hire'],
            'selected_caregiver' => ['key' => 'applicants', 'label' => 'Caregiver', 'description' => 'Profile, chat'],
            'past_caregivers' => ['key' => 'applicants', 'label' => 'Caregivers', 'description' => 'Past responses'],
            'shift' => ['key' => 'shift', 'label' => 'Visit', 'description' => 'Time, location, payment'],
            'support' => ['key' => 'support', 'label' => 'Help', 'description' => 'Change or get help'],
        ];

        $make = function (
            string $key,
            string $eyebrow,
            string $title,
            string $body,
            string $primaryTab,
            array $tabKeys,
            array $tiles
        ) use ($baseTabs): array {
            return [
                'key' => $key,
                'eyebrow' => $eyebrow,
                'title' => $title,
                'body' => $body,
                'primary_tab' => $primaryTab,
                'tabs' => collect($tabKeys)->map(fn (string $tab) => $baseTabs[$tab])->values()->all(),
                'tiles' => $tiles,
            ];
        };

        if ($request->status === CareRequest::STATUS_CANCELLED) {
            return $make(
                'request_withdrawn',
                'Request closed',
                'This request was withdrawn.',
                'Caregivers can no longer apply or respond. Keep the details here for your records.',
                'overview',
                ['overview', 'past_caregivers'],
                [
                    ['label' => 'Status', 'value' => 'Withdrawn', 'help' => 'No new replies'],
                    ['label' => 'Caregivers', 'value' => (string) $applicationsCount, 'help' => 'Past responses'],
                    ['label' => 'Invited', 'value' => (string) $invitesCount, 'help' => 'Closed invites'],
                ],
            );
        }

        if (! $booking && $request->status === CareRequest::STATUS_OPEN && $pendingCandidates === 0) {
            return $make(
                'waiting_for_caregivers',
                'Finding care',
                'Your request is live.',
                'Caregivers can reply now. Invite a good match if you want to move faster.',
                'applicants',
                ['caregiver_invites', 'overview'],
                [
                    ['label' => 'Replies', 'value' => '0', 'help' => 'Waiting'],
                    ['label' => 'Invited', 'value' => (string) $invitesCount, 'help' => 'Direct invites'],
                    ['label' => 'Schedule', 'value' => self::scheduleTile($request, $booking), 'help' => 'Requested time'],
                ],
            );
        }

        if (! $booking && $request->status === CareRequest::STATUS_OPEN) {
            return $make(
                'reviewing_caregivers',
                'Choose caregiver',
                'Caregivers are ready for review.',
                'Compare profiles, chat if needed, then hire the person you trust.',
                'applicants',
                ['caregiver_review', 'overview'],
                [
                    ['label' => 'To review', 'value' => (string) $pendingCandidates, 'help' => 'Interested caregivers'],
                    ['label' => 'Invited', 'value' => (string) $invitesCount, 'help' => 'Direct invites'],
                    ['label' => 'Schedule', 'value' => self::scheduleTile($request, $booking), 'help' => 'Requested time'],
                ],
            );
        }

        if (! $booking) {
            return $make(
                'caregiver_selected',
                'Caregiver selected',
                'Caregiver selected. Visit setup is next.',
                'The caregiver is chosen, but the visit record is not ready yet.',
                'overview',
                ['overview', 'selected_caregiver'],
                [
                    ['label' => 'Status', 'value' => 'Selected', 'help' => 'Caregiver chosen'],
                    ['label' => 'Caregivers', 'value' => (string) $applicationsCount, 'help' => 'Request flow'],
                    ['label' => 'Visit', 'value' => 'Pending', 'help' => 'Not scheduled yet'],
                ],
            );
        }

        $timesheetNeedsReview = in_array($booking->status, [
            CareBooking::STATUS_COMPLETED,
            CareBooking::STATUS_REVIEWED,
        ], true) && ! $booking->family_confirmed_at;

        if ($timesheetNeedsReview) {
            return $make(
                'timesheet_review',
                'Review hours',
                'Caregiver hours need your review.',
                'Check the worked time and payment amount. Approving will capture payment and move payout forward.',
                'shift',
                ['shift', 'support', 'selected_caregiver', 'overview'],
                [
                    ['label' => 'Worked', 'value' => self::minutesTile((int) ($booking->worked_minutes ?? 0)), 'help' => 'Submitted time'],
                    ['label' => 'Caregiver', 'value' => self::caregiverTile($request), 'help' => 'Submitted by'],
                    ['label' => 'Payment', 'value' => 'Review', 'help' => 'Capture after approval'],
                ],
            );
        }

        return match ($booking->status) {
            CareBooking::STATUS_SCHEDULED => $make(
                'visit_scheduled',
                'Visit scheduled',
                'Your visit is scheduled.',
                'Your caregiver is booked. Check the time and address, message them if needed, or change the visit before it starts.',
                'shift',
                ['shift', 'selected_caregiver', 'support', 'overview'],
                [
                    ['label' => 'Visit', 'value' => 'Scheduled', 'help' => 'Waiting for check-in'],
                    ['label' => 'Caregiver', 'value' => self::caregiverTile($request), 'help' => 'Assigned person'],
                    ['label' => 'Time', 'value' => self::scheduleTile($request, $booking), 'help' => 'Scheduled visit'],
                ],
            ),
            CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED => $make(
                'visit_live',
                'Visit in progress',
                $booking->status === CareBooking::STATUS_PAUSED ? 'The visit is paused.' : 'Care is happening now.',
                'Use this screen for check-in details, chat, safety support, and completion.',
                'shift',
                ['shift', 'support', 'selected_caregiver', 'overview'],
                [
                    ['label' => 'Visit', 'value' => $booking->status === CareBooking::STATUS_PAUSED ? 'Paused' : 'Live', 'help' => 'Caregiver checked in'],
                    ['label' => 'Caregiver', 'value' => self::caregiverTile($request), 'help' => 'Assigned person'],
                    ['label' => 'Started', 'value' => $booking->started_at?->format('g:i A') ?: '-', 'help' => 'Check-in time'],
                ],
            ),
            CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED => $make(
                'visit_complete',
                'Visit complete',
                'This visit is complete.',
                'Review the final record, caregiver feedback, and book again when you need the same help.',
                'shift',
                ['shift', 'selected_caregiver', 'support', 'overview'],
                [
                    ['label' => 'Visit', 'value' => 'Complete', 'help' => 'Closed record'],
                    ['label' => 'Caregiver', 'value' => self::caregiverTile($request), 'help' => 'Assigned person'],
                    ['label' => 'Worked', 'value' => self::minutesTile((int) ($booking->worked_minutes ?? 0)), 'help' => 'Final time'],
                ],
            ),
            CareBooking::STATUS_CANCELLED => $make(
                'visit_cancelled',
                'Visit cancelled',
                $booking->no_show_flag ? 'This visit was marked no-show.' : 'This visit was cancelled.',
                'Review the record or book care again if you still need help.',
                'shift',
                ['shift', 'support', 'selected_caregiver', 'overview'],
                [
                    ['label' => 'Visit', 'value' => $booking->no_show_flag ? 'No-show' : 'Cancelled', 'help' => 'Closed'],
                    ['label' => 'Caregiver', 'value' => self::caregiverTile($request), 'help' => 'Assigned person'],
                    ['label' => 'Payment', 'value' => 'Released', 'help' => 'Authorization handled'],
                ],
            ),
            CareBooking::STATUS_DISPUTED => $make(
                'visit_disputed',
                'Support review',
                'Support is reviewing this visit.',
                'Keep messages, visit details, and support actions together while the issue is open.',
                'support',
                ['support', 'shift', 'selected_caregiver', 'overview'],
                [
                    ['label' => 'Visit', 'value' => 'Dispute', 'help' => 'Support open'],
                    ['label' => 'Caregiver', 'value' => self::caregiverTile($request), 'help' => 'Assigned person'],
                    ['label' => 'Status', 'value' => strtoupper((string) ($booking->dispute_status ?? 'open')), 'help' => 'Dispute state'],
                ],
            ),
            default => $make(
                'visit_status',
                'Visit status',
                'Visit details are available.',
                'Use this screen for time, caregiver, payment, and support actions.',
                'shift',
                ['shift', 'support', 'caregiver_review', 'overview'],
                [
                    ['label' => 'Visit', 'value' => ucfirst(str_replace('_', ' ', (string) $booking->status)), 'help' => 'Current status'],
                    ['label' => 'Caregiver', 'value' => self::caregiverTile($request), 'help' => 'Assigned person'],
                    ['label' => 'Time', 'value' => self::scheduleTile($request, $booking), 'help' => 'Scheduled visit'],
                ],
            ),
        };
    }

    private static function scheduleTile(CareRequest $request, ?CareBooking $booking): string
    {
        if ($booking?->scheduled_start_at) {
            return $booking->scheduled_start_at->format('M d, g:i A');
        }

        if ($request->requested_start_at) {
            return $request->requested_start_at->format('M d, g:i A');
        }

        return $request->request_type === CareRequest::TYPE_RECURRING ? 'Weekly' : 'Pending';
    }

    private static function caregiverTile(CareRequest $request): string
    {
        $application = $request->relationLoaded('applications')
            ? $request->applications->firstWhere('status', CareRequestApplication::STATUS_HIRED)
            : $request->applications()->where('status', CareRequestApplication::STATUS_HIRED)->with('caregiver:id,name')->first();

        return trim((string) ($application?->caregiver?->name ?? 'Caregiver')) ?: 'Caregiver';
    }

    private static function minutesTile(int $minutes): string
    {
        if ($minutes <= 0) {
            return 'Pending';
        }

        return sprintf('%dh %02dm', intdiv($minutes, 60), $minutes % 60);
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
