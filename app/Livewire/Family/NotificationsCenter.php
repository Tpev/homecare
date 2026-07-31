<?php

namespace App\Livewire\Family;

use App\Models\UserNotificationPreference;
use App\Services\Notifications\NotificationChannels;
use App\Support\MarketplaceEvent;
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
        MarketplaceEvent::INVITATION_SENT,
        MarketplaceEvent::NEW_APPLICANT,
        MarketplaceEvent::INVITE_ACCEPTED,
        MarketplaceEvent::INVITE_DECLINED,
        MarketplaceEvent::HIRE_CONFIRMED,
        MarketplaceEvent::SHIFT_CANCELLED,
        MarketplaceEvent::SHIFT_STARTING_SOON,
        MarketplaceEvent::SHIFT_REMINDER_24H,
        MarketplaceEvent::SHIFT_STARTED,
        MarketplaceEvent::SHIFT_COMPLETED,
        MarketplaceEvent::TIMESHEET_AUTO_APPROVED,
        MarketplaceEvent::MESSAGE_RECEIVED,
        MarketplaceEvent::PAYMENT_AUTHORIZED,
        MarketplaceEvent::PAYMENT_ACTION_REQUIRED,
        MarketplaceEvent::PAYMENT_AUTHORIZATION_FAILED,
        MarketplaceEvent::PAYMENT_CAPTURED,
        MarketplaceEvent::PAYMENT_REFUNDED,
        MarketplaceEvent::REGULAR_CARE_ACCEPTED,
        MarketplaceEvent::REGULAR_CARE_COUNTERED,
        MarketplaceEvent::REGULAR_CARE_DECLINED,
        MarketplaceEvent::REGULAR_CARE_PAYMENT_ATTENTION,
        MarketplaceEvent::REGULAR_CARE_SCHEDULE_CHANGE_ACCEPTED,
        MarketplaceEvent::REGULAR_CARE_SCHEDULE_CHANGE_DECLINED,
        MarketplaceEvent::REGULAR_CARE_PAUSED,
        MarketplaceEvent::REGULAR_CARE_RESUMED,
        MarketplaceEvent::REGULAR_CARE_ENDED,
        MarketplaceEvent::TIME_CORRECTION_REQUESTED,
        MarketplaceEvent::TIME_CORRECTION_CHANGES_REQUESTED,
        MarketplaceEvent::TIME_CORRECTION_RESUBMITTED,
        MarketplaceEvent::TIME_CORRECTION_APPROVED,
        MarketplaceEvent::TIME_CORRECTION_PAYMENT_ACTION_REQUIRED,
        MarketplaceEvent::TIME_CORRECTION_APPLIED,
        MarketplaceEvent::TIME_CORRECTION_ESCALATED,
        MarketplaceEvent::TIME_CORRECTION_WITHDRAWN,
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'family', 403);
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
            'preferences.*.sms' => ['boolean'],
            'preferences.*.push' => ['boolean'],
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
                    'sms_enabled' => (bool) ($row[NotificationChannels::SMS] ?? false),
                    'push_enabled' => (bool) ($row[NotificationChannels::PUSH] ?? false),
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
                    'event_label' => $this->eventLabel($eventKey),
                    'title' => (string) data_get($notification->data, 'title', 'Notification'),
                    'body' => (string) data_get($notification->data, 'body', ''),
                    'url' => (string) data_get($notification->data, 'url', ''),
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                    'tone' => $this->eventTone($eventKey),
                ];
            });

        return view('livewire.family.notifications-center', [
            'notifications' => $notifications,
            'unreadCount' => auth()->user()->unreadNotifications()->count(),
            'eventOptions' => collect($this->eventKeys)->map(fn (string $key) => [
                'label' => $this->eventLabel($key),
                'value' => $key,
            ])->values()->all(),
        ]);
    }

    private function eventLabel(string $eventKey): string
    {
        return match ($eventKey) {
            MarketplaceEvent::INVITATION_SENT => 'Invitation sent',
            MarketplaceEvent::NEW_APPLICANT => 'New caregiver reply',
            MarketplaceEvent::INVITE_ACCEPTED => 'Invitation accepted',
            MarketplaceEvent::INVITE_DECLINED => 'Invitation declined',
            MarketplaceEvent::HIRE_CONFIRMED => 'Hire confirmed',
            MarketplaceEvent::SHIFT_CANCELLED => 'Visit cancelled',
            MarketplaceEvent::SHIFT_STARTING_SOON => 'Visit reminder',
            MarketplaceEvent::SHIFT_REMINDER_24H => 'Visit tomorrow',
            MarketplaceEvent::SHIFT_STARTED => 'Visit started',
            MarketplaceEvent::SHIFT_COMPLETED => 'Visit completed',
            MarketplaceEvent::TIMESHEET_AUTO_APPROVED => 'Timesheet approved',
            MarketplaceEvent::MESSAGE_RECEIVED => 'Message',
            MarketplaceEvent::PAYMENT_AUTHORIZED => 'Payment authorized',
            MarketplaceEvent::PAYMENT_ACTION_REQUIRED => 'Payment action required',
            MarketplaceEvent::PAYMENT_AUTHORIZATION_FAILED => 'Payment issue',
            MarketplaceEvent::PAYMENT_CAPTURED => 'Payment captured',
            MarketplaceEvent::PAYMENT_REFUNDED => 'Payment refunded',
            MarketplaceEvent::REGULAR_CARE_ACCEPTED => 'Regular care accepted',
            MarketplaceEvent::REGULAR_CARE_COUNTERED => 'Regular care countered',
            MarketplaceEvent::REGULAR_CARE_DECLINED => 'Regular care declined',
            MarketplaceEvent::REGULAR_CARE_PAYMENT_ATTENTION => 'Regular care payment',
            MarketplaceEvent::REGULAR_CARE_SCHEDULE_CHANGE_ACCEPTED => 'Schedule change accepted',
            MarketplaceEvent::REGULAR_CARE_SCHEDULE_CHANGE_DECLINED => 'Schedule change declined',
            MarketplaceEvent::REGULAR_CARE_PAUSED => 'Regular care paused',
            MarketplaceEvent::REGULAR_CARE_RESUMED => 'Regular care resumed',
            MarketplaceEvent::REGULAR_CARE_ENDED => 'Regular care ended',
            MarketplaceEvent::TIME_CORRECTION_REQUESTED => 'Time correction requested',
            MarketplaceEvent::TIME_CORRECTION_CHANGES_REQUESTED => 'Changes requested',
            MarketplaceEvent::TIME_CORRECTION_RESUBMITTED => 'Updated time ready',
            MarketplaceEvent::TIME_CORRECTION_APPROVED => 'Time correction approved',
            MarketplaceEvent::TIME_CORRECTION_PAYMENT_ACTION_REQUIRED => 'Correction payment needed',
            MarketplaceEvent::TIME_CORRECTION_APPLIED => 'Visit time updated',
            MarketplaceEvent::TIME_CORRECTION_ESCALATED => 'LoLo review',
            MarketplaceEvent::TIME_CORRECTION_WITHDRAWN => 'Time correction withdrawn',
            default => 'Update',
        };
    }

    private function eventTone(string $eventKey): string
    {
        return match ($eventKey) {
            MarketplaceEvent::INVITATION_SENT,
            MarketplaceEvent::PAYMENT_AUTHORIZED,
            MarketplaceEvent::PAYMENT_CAPTURED,
            MarketplaceEvent::HIRE_CONFIRMED,
            MarketplaceEvent::REGULAR_CARE_ACCEPTED,
            MarketplaceEvent::REGULAR_CARE_SCHEDULE_CHANGE_ACCEPTED,
            MarketplaceEvent::TIMESHEET_AUTO_APPROVED,
            MarketplaceEvent::REGULAR_CARE_RESUMED => 'success',
            MarketplaceEvent::TIME_CORRECTION_APPROVED,
            MarketplaceEvent::TIME_CORRECTION_APPLIED => 'success',
            MarketplaceEvent::NEW_APPLICANT,
            MarketplaceEvent::INVITE_ACCEPTED,
            MarketplaceEvent::SHIFT_REMINDER_24H,
            MarketplaceEvent::SHIFT_STARTED => 'info',
            MarketplaceEvent::TIME_CORRECTION_REQUESTED,
            MarketplaceEvent::TIME_CORRECTION_RESUBMITTED => 'info',
            MarketplaceEvent::INVITE_DECLINED,
            MarketplaceEvent::SHIFT_CANCELLED,
            MarketplaceEvent::PAYMENT_AUTHORIZATION_FAILED,
            MarketplaceEvent::PAYMENT_ACTION_REQUIRED,
            MarketplaceEvent::PAYMENT_REFUNDED,
            MarketplaceEvent::REGULAR_CARE_COUNTERED,
            MarketplaceEvent::REGULAR_CARE_DECLINED,
            MarketplaceEvent::REGULAR_CARE_PAYMENT_ATTENTION,
            MarketplaceEvent::REGULAR_CARE_SCHEDULE_CHANGE_DECLINED,
            MarketplaceEvent::REGULAR_CARE_PAUSED,
            MarketplaceEvent::REGULAR_CARE_ENDED => 'warning',
            MarketplaceEvent::TIME_CORRECTION_CHANGES_REQUESTED,
            MarketplaceEvent::TIME_CORRECTION_PAYMENT_ACTION_REQUIRED,
            MarketplaceEvent::TIME_CORRECTION_ESCALATED => 'warning',
            MarketplaceEvent::TIME_CORRECTION_WITHDRAWN => 'neutral',
            default => 'neutral',
        };
    }
}
