<?php

namespace App\Console\Commands;

use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\User;
use App\Services\Matching\CaregiverSuggestionService;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Support\MarketplaceEvent;
use Illuminate\Console\Command;

class DispatchMarketplaceNotifications extends Command
{
    protected $signature = 'homecare:dispatch-notifications {--type=all : all|matching|shift-soon}';

    protected $description = 'Dispatch marketplace reminders and lifecycle notifications.';

    public function handle(
        CaregiverSuggestionService $suggestions,
        MarketplaceNotificationService $notifications
    ): int {
        $type = (string) $this->option('type');

        if (! in_array($type, ['all', 'matching', 'shift-soon'], true)) {
            $this->error('Invalid --type. Use all, matching, or shift-soon.');
            return self::FAILURE;
        }

        if (in_array($type, ['all', 'matching'], true)) {
            $this->dispatchMatchingReminders($suggestions, $notifications);
        }

        if (in_array($type, ['all', 'shift-soon'], true)) {
            $this->dispatchShiftStartingSoon($notifications);
        }

        return self::SUCCESS;
    }

    private function dispatchMatchingReminders(
        CaregiverSuggestionService $suggestions,
        MarketplaceNotificationService $notifications
    ): void {
        $requests = CareRequest::query()
            ->where('status', CareRequest::STATUS_OPEN)
            ->where('created_at', '>=', now()->subHours(2))
            ->whereDoesntHave('applications')
            ->with(['family:id,name'])
            ->latest('created_at')
            ->limit(30)
            ->get();

        foreach ($requests as $request) {
            $matches = $suggestions->topMatchesForRequest($request, 3);

            foreach ($matches as $match) {
                $caregiver = User::query()->find($match['user_id']);
                if (! $caregiver) {
                    continue;
                }

                $notifications->notify(
                    recipients: $caregiver,
                    eventKey: MarketplaceEvent::MATCHING_REQUEST_REMINDER,
                    title: 'New request matching your profile',
                    body: 'A family posted a care request in your service area. Review and apply if it fits your schedule.',
                    url: route('care-requests.apply', $request->id),
                    payload: [
                        'care_request_id' => $request->id,
                        'score' => $match['score'],
                    ],
                    subject: $request,
                    dedupeKey: 'matching-reminder:request-'.$request->id.'-user-'.$caregiver->id
                );
            }
        }
    }

    private function dispatchShiftStartingSoon(MarketplaceNotificationService $notifications): void
    {
        $from = now()->addMinutes(55);
        $to = now()->addMinutes(65);

        $bookings = CareBooking::query()
            ->with(['careRequest:id,title', 'family:id,name', 'caregiver:id,name'])
            ->where('status', CareBooking::STATUS_SCHEDULED)
            ->whereNotNull('scheduled_start_at')
            ->whereBetween('scheduled_start_at', [$from, $to])
            ->get();

        foreach ($bookings as $booking) {
            $formatted = optional($booking->scheduled_start_at)?->format('M d, H:i');
            $title = 'Shift starting soon';
            $body = 'Your shift for "'.$booking->careRequest?->title.'" starts at '.$formatted.'.';

            if ($booking->family) {
                $notifications->notify(
                    recipients: $booking->family,
                    eventKey: MarketplaceEvent::SHIFT_STARTING_SOON,
                    title: $title,
                    body: $body,
                    url: route('family.requests.show', $booking->care_request_id),
                    payload: ['care_booking_id' => $booking->id],
                    subject: $booking,
                    dedupeKey: 'shift-soon:booking-'.$booking->id.'-user-'.$booking->family->id
                );
            }

            if ($booking->caregiver) {
                $notifications->notify(
                    recipients: $booking->caregiver,
                    eventKey: MarketplaceEvent::SHIFT_STARTING_SOON,
                    title: $title,
                    body: $body,
                    url: route('care-requests.apply', $booking->care_request_id),
                    payload: ['care_booking_id' => $booking->id],
                    subject: $booking,
                    dedupeKey: 'shift-soon:booking-'.$booking->id.'-user-'.$booking->caregiver->id
                );
            }
        }
    }
}
