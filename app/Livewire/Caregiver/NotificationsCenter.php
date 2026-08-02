<?php

namespace App\Livewire\Caregiver;

use App\Models\UserNotificationPreference;
use App\Services\Notifications\NotificationChannels;
use App\Support\MarketplaceEvent;
use App\Support\MarketplaceNotificationPresentation;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class NotificationsCenter extends Component
{
    public string $scope = 'unread';

    public string $eventFilter = 'all';

    /**
     * @var array<string, array<string, bool>>
     */
    public array $preferences = [];

    /**
     * @var list<string>
     */
    public array $eventKeys = [
        MarketplaceEvent::CAREGIVER_WELCOME,
        MarketplaceEvent::CAREGIVER_ONBOARDING_REMINDER_24H,
        MarketplaceEvent::INVITATION_RECEIVED,
        MarketplaceEvent::MATCHING_REQUEST_REMINDER,
        MarketplaceEvent::APPLICATION_SUBMITTED,
        MarketplaceEvent::CARE_REQUEST_WITHDRAWN,
        MarketplaceEvent::CAREGIVER_HIRED,
        MarketplaceEvent::SHIFT_CANCELLED,
        MarketplaceEvent::SHIFT_STARTING_SOON,
        MarketplaceEvent::SHIFT_REMINDER_24H,
        MarketplaceEvent::SHIFT_STARTED,
        MarketplaceEvent::SHIFT_COMPLETED,
        MarketplaceEvent::TIMESHEET_AUTO_APPROVED,
        MarketplaceEvent::REVIEW_RECEIVED,
        MarketplaceEvent::MESSAGE_RECEIVED,
        MarketplaceEvent::BOOKING_CHANGE_REQUESTED,
        MarketplaceEvent::BOOKING_CHANGE_ACCEPTED,
        MarketplaceEvent::BOOKING_CHANGE_DECLINED,
        MarketplaceEvent::PAYOUT_TRANSFERRED,
        MarketplaceEvent::PAYOUT_TRANSFER_FAILED,
        MarketplaceEvent::PAYMENT_REFUNDED,
        MarketplaceEvent::REGULAR_CARE_OFFERED,
        MarketplaceEvent::REGULAR_CARE_ACCEPTED,
        MarketplaceEvent::REGULAR_CARE_ENDED,
        MarketplaceEvent::REGULAR_CARE_SCHEDULE_CHANGE_REQUESTED,
        MarketplaceEvent::REGULAR_CARE_EXTRA_VISIT_REQUESTED,
        MarketplaceEvent::REGULAR_CARE_VISIT_SKIPPED,
        MarketplaceEvent::REGULAR_CARE_PAUSED,
        MarketplaceEvent::REGULAR_CARE_RESUMED,
        MarketplaceEvent::TIME_CORRECTION_REQUESTED,
        MarketplaceEvent::TIME_CORRECTION_CHANGES_REQUESTED,
        MarketplaceEvent::TIME_CORRECTION_RESUBMITTED,
        MarketplaceEvent::TIME_CORRECTION_APPROVED,
        MarketplaceEvent::TIME_CORRECTION_PAYMENT_ACTION_REQUIRED,
        MarketplaceEvent::TIME_CORRECTION_APPLIED,
        MarketplaceEvent::TIME_CORRECTION_ESCALATED,
        MarketplaceEvent::TIME_CORRECTION_WITHDRAWN,
        MarketplaceEvent::COMPLETED_EXTRA_VISIT_CHANGES_REQUESTED,
        MarketplaceEvent::COMPLETED_EXTRA_VISIT_APPROVED,
        MarketplaceEvent::COMPLETED_EXTRA_VISIT_DISPUTED,
        MarketplaceEvent::COMPLETED_EXTRA_VISIT_APPLIED,
        MarketplaceEvent::COMPLETED_EXTRA_VISIT_ESCALATED,
        MarketplaceEvent::SUPPORT_TICKET_REPLY,
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'caregiver', 403);
        $this->eventKeys = MarketplaceNotificationPresentation::eventsForRole('caregiver');
        $this->loadPreferencesFromStore();
    }

    public function markRead(string $notificationId): void
    {
        $notification = auth()->user()
            ->notifications()
            ->whereKey($notificationId)
            ->first();

        if (! $notification) {
            return;
        }

        if (! $notification->read_at) {
            $notification->markAsRead();
        }
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function openNotification(string $notificationId): void
    {
        $notification = auth()->user()
            ->notifications()
            ->whereKey($notificationId)
            ->first();

        if (! $notification) {
            return;
        }

        if (! $notification->read_at) {
            $notification->markAsRead();
        }

        $url = (string) data_get($notification->data, 'url', '');
        if ($url !== '') {
            $this->redirect($url, navigate: true);

            return;
        }

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }

    public function savePreferences(): void
    {
        $this->validate([
            'preferences' => ['array'],
            'preferences.*.in_app' => ['boolean'],
            'preferences.*.email' => ['boolean'],
        ]);

        $userId = (int) auth()->id();

        foreach ($this->eventKeys as $eventKey) {
            $row = $this->preferences[$eventKey] ?? $this->defaultChannels();

            UserNotificationPreference::query()->updateOrCreate(
                [
                    'user_id' => $userId,
                    'event_key' => $eventKey,
                ],
                [
                    'in_app_enabled' => (bool) ($row[NotificationChannels::IN_APP] ?? true),
                    'email_enabled' => (bool) ($row[NotificationChannels::EMAIL] ?? true),
                    'sms_enabled' => false,
                    'push_enabled' => false,
                ]
            );
        }

        session()->flash('status', 'Notification preferences saved.');
    }

    private function loadPreferencesFromStore(): void
    {
        $stored = UserNotificationPreference::query()
            ->where('user_id', auth()->id())
            ->whereIn('event_key', $this->eventKeys)
            ->get()
            ->keyBy('event_key');

        foreach ($this->eventKeys as $eventKey) {
            $row = $stored->get($eventKey);
            $this->preferences[$eventKey] = [
                NotificationChannels::IN_APP => $row ? (bool) $row->in_app_enabled : true,
                NotificationChannels::EMAIL => $row ? (bool) $row->email_enabled : true,
                NotificationChannels::SMS => $row ? (bool) $row->sms_enabled : false,
                NotificationChannels::PUSH => $row ? (bool) $row->push_enabled : false,
            ];
        }
    }

    /**
     * @return array<string, bool>
     */
    private function defaultChannels(): array
    {
        return [
            NotificationChannels::IN_APP => true,
            NotificationChannels::EMAIL => true,
            NotificationChannels::SMS => false,
            NotificationChannels::PUSH => false,
        ];
    }

    public function render()
    {
        $notificationsQuery = auth()->user()->notifications();

        if ($this->scope === 'unread') {
            $notificationsQuery->whereNull('read_at');
        } elseif ($this->scope === 'read') {
            $notificationsQuery->whereNotNull('read_at');
        }

        if ($this->eventFilter !== 'all') {
            $notificationsQuery->where('data->event_key', $this->eventFilter);
        }

        $notifications = $notificationsQuery
            ->latest()
            ->limit(80)
            ->get()
            ->map(function (DatabaseNotification $notification): array {
                $eventKey = (string) data_get($notification->data, 'event_key', 'generic');

                return [
                    'id' => $notification->id,
                    'event_key' => $eventKey,
                    'event_label' => MarketplaceNotificationPresentation::label($eventKey),
                    'title' => (string) data_get($notification->data, 'title', 'LoLo Care update'),
                    'body' => (string) data_get($notification->data, 'body', ''),
                    'details' => collect((array) data_get($notification->data, 'payload.email_details', []))->take(3)->values()->all(),
                    'url' => (string) data_get($notification->data, 'url', ''),
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                    'tone' => MarketplaceNotificationPresentation::tone($eventKey),
                ];
            });

        return view('livewire.caregiver.notifications-center', [
            'notifications' => $notifications,
            'unreadCount' => auth()->user()->unreadNotifications()->count(),
            'eventOptions' => collect($this->eventKeys)->map(fn (string $key) => [
                'label' => MarketplaceNotificationPresentation::label($key),
                'value' => $key,
            ])->values()->all(),
        ]);
    }

    private function eventLabel(string $eventKey): string
    {
        return match ($eventKey) {
            MarketplaceEvent::MATCHING_REQUEST_REMINDER => 'Invitation / match',
            MarketplaceEvent::INVITATION_RECEIVED => 'Care invitation',
            MarketplaceEvent::CAREGIVER_WELCOME => 'Welcome to LoLo Care',
            MarketplaceEvent::CAREGIVER_ONBOARDING_REMINDER_24H => 'Finish caregiver setup',
            MarketplaceEvent::APPLICATION_SUBMITTED => 'Application submitted',
            MarketplaceEvent::CARE_REQUEST_WITHDRAWN => 'Request withdrawn',
            MarketplaceEvent::CAREGIVER_HIRED => 'Hired',
            MarketplaceEvent::SHIFT_CANCELLED => 'Shift cancelled',
            MarketplaceEvent::SHIFT_STARTING_SOON => 'Shift reminder',
            MarketplaceEvent::SHIFT_REMINDER_24H => 'Shift tomorrow',
            MarketplaceEvent::SHIFT_STARTED => 'Shift started',
            MarketplaceEvent::SHIFT_COMPLETED => 'Shift completed',
            MarketplaceEvent::TIMESHEET_AUTO_APPROVED => 'Timesheet approved',
            MarketplaceEvent::REVIEW_RECEIVED => 'Review received',
            MarketplaceEvent::MESSAGE_RECEIVED => 'Message',
            MarketplaceEvent::BOOKING_CHANGE_REQUESTED => 'Visit change requested',
            MarketplaceEvent::BOOKING_CHANGE_ACCEPTED => 'Visit change accepted',
            MarketplaceEvent::BOOKING_CHANGE_DECLINED => 'Visit change declined',
            MarketplaceEvent::PAYOUT_TRANSFERRED => 'Payout sent',
            MarketplaceEvent::PAYOUT_TRANSFER_FAILED => 'Payout issue',
            MarketplaceEvent::PAYMENT_REFUNDED => 'Refund update',
            MarketplaceEvent::REGULAR_CARE_OFFERED => 'Regular care offer',
            MarketplaceEvent::REGULAR_CARE_ACCEPTED => 'Regular care accepted',
            MarketplaceEvent::REGULAR_CARE_ENDED => 'Regular care ended',
            MarketplaceEvent::REGULAR_CARE_SCHEDULE_CHANGE_REQUESTED => 'Schedule change requested',
            MarketplaceEvent::REGULAR_CARE_EXTRA_VISIT_REQUESTED => 'Extra visit requested',
            MarketplaceEvent::REGULAR_CARE_VISIT_SKIPPED => 'Regular visit skipped',
            MarketplaceEvent::REGULAR_CARE_PAUSED => 'Regular care paused',
            MarketplaceEvent::REGULAR_CARE_RESUMED => 'Regular care resumed',
            MarketplaceEvent::TIME_CORRECTION_REQUESTED => 'Time correction requested',
            MarketplaceEvent::TIME_CORRECTION_CHANGES_REQUESTED => 'Time correction needs changes',
            MarketplaceEvent::TIME_CORRECTION_RESUBMITTED => 'Time correction updated',
            MarketplaceEvent::TIME_CORRECTION_APPROVED => 'Time correction approved',
            MarketplaceEvent::TIME_CORRECTION_PAYMENT_ACTION_REQUIRED => 'Correction payment needed',
            MarketplaceEvent::TIME_CORRECTION_APPLIED => 'Visit time updated',
            MarketplaceEvent::TIME_CORRECTION_ESCALATED => 'LoLo Care review',
            MarketplaceEvent::TIME_CORRECTION_WITHDRAWN => 'Time correction withdrawn',
            MarketplaceEvent::COMPLETED_EXTRA_VISIT_CHANGES_REQUESTED => 'Extra visit needs changes',
            MarketplaceEvent::COMPLETED_EXTRA_VISIT_APPROVED => 'Extra visit approved',
            MarketplaceEvent::COMPLETED_EXTRA_VISIT_DISPUTED => 'Extra visit disputed',
            MarketplaceEvent::COMPLETED_EXTRA_VISIT_APPLIED => 'Extra visit recorded',
            MarketplaceEvent::COMPLETED_EXTRA_VISIT_ESCALATED => 'Extra visit LoLo Care review',
            MarketplaceEvent::SUPPORT_TICKET_REPLY => 'Support reply',
            default => 'Update',
        };
    }

    private function eventTone(string $eventKey): string
    {
        return match ($eventKey) {
            MarketplaceEvent::APPLICATION_SUBMITTED,
            MarketplaceEvent::CAREGIVER_WELCOME,
            MarketplaceEvent::CAREGIVER_HIRED,
            MarketplaceEvent::SHIFT_STARTED,
            MarketplaceEvent::TIMESHEET_AUTO_APPROVED,
            MarketplaceEvent::REGULAR_CARE_ACCEPTED,
            MarketplaceEvent::REGULAR_CARE_RESUMED => 'success',
            MarketplaceEvent::TIME_CORRECTION_APPROVED,
            MarketplaceEvent::TIME_CORRECTION_APPLIED,
            MarketplaceEvent::COMPLETED_EXTRA_VISIT_APPROVED,
            MarketplaceEvent::COMPLETED_EXTRA_VISIT_APPLIED => 'success',
            MarketplaceEvent::SHIFT_STARTING_SOON,
            MarketplaceEvent::SHIFT_REMINDER_24H,
            MarketplaceEvent::REGULAR_CARE_OFFERED,
            MarketplaceEvent::REGULAR_CARE_SCHEDULE_CHANGE_REQUESTED,
            MarketplaceEvent::REGULAR_CARE_EXTRA_VISIT_REQUESTED => 'info',
            MarketplaceEvent::INVITATION_RECEIVED,
            MarketplaceEvent::BOOKING_CHANGE_REQUESTED,
            MarketplaceEvent::CAREGIVER_ONBOARDING_REMINDER_24H => 'info',
            MarketplaceEvent::TIME_CORRECTION_REQUESTED,
            MarketplaceEvent::TIME_CORRECTION_RESUBMITTED => 'info',
            MarketplaceEvent::CARE_REQUEST_WITHDRAWN,
            MarketplaceEvent::SHIFT_CANCELLED,
            MarketplaceEvent::REVIEW_RECEIVED => 'warning',
            MarketplaceEvent::BOOKING_CHANGE_DECLINED => 'warning',
            MarketplaceEvent::TIME_CORRECTION_CHANGES_REQUESTED,
            MarketplaceEvent::TIME_CORRECTION_PAYMENT_ACTION_REQUIRED,
            MarketplaceEvent::TIME_CORRECTION_ESCALATED,
            MarketplaceEvent::COMPLETED_EXTRA_VISIT_CHANGES_REQUESTED,
            MarketplaceEvent::COMPLETED_EXTRA_VISIT_DISPUTED,
            MarketplaceEvent::COMPLETED_EXTRA_VISIT_ESCALATED => 'warning',
            MarketplaceEvent::TIME_CORRECTION_WITHDRAWN => 'neutral',
            MarketplaceEvent::MESSAGE_RECEIVED => 'neutral',
            MarketplaceEvent::MATCHING_REQUEST_REMINDER => 'info',
            MarketplaceEvent::SHIFT_COMPLETED => 'neutral',
            MarketplaceEvent::PAYOUT_TRANSFERRED => 'success',
            MarketplaceEvent::PAYOUT_TRANSFER_FAILED => 'warning',
            MarketplaceEvent::PAYMENT_REFUNDED,
            MarketplaceEvent::REGULAR_CARE_ENDED,
            MarketplaceEvent::REGULAR_CARE_VISIT_SKIPPED,
            MarketplaceEvent::REGULAR_CARE_PAUSED => 'warning',
            default => 'neutral',
        };
    }
}
