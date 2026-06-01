<?php

namespace App\Livewire\Caregiver;

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
        MarketplaceEvent::MATCHING_REQUEST_REMINDER,
        MarketplaceEvent::APPLICATION_SUBMITTED,
        MarketplaceEvent::CAREGIVER_HIRED,
        MarketplaceEvent::SHIFT_STARTING_SOON,
        MarketplaceEvent::SHIFT_STARTED,
        MarketplaceEvent::SHIFT_COMPLETED,
        MarketplaceEvent::REVIEW_RECEIVED,
        MarketplaceEvent::MESSAGE_RECEIVED,
        MarketplaceEvent::PAYOUT_TRANSFERRED,
        MarketplaceEvent::PAYOUT_TRANSFER_FAILED,
        MarketplaceEvent::PAYMENT_REFUNDED,
        MarketplaceEvent::REGULAR_CARE_OFFERED,
        MarketplaceEvent::REGULAR_CARE_ACCEPTED,
        MarketplaceEvent::REGULAR_CARE_ENDED,
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'caregiver', 403);
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

        return view('livewire.caregiver.notifications-center', [
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
            MarketplaceEvent::MATCHING_REQUEST_REMINDER => 'Invitation / match',
            MarketplaceEvent::APPLICATION_SUBMITTED => 'Application submitted',
            MarketplaceEvent::CAREGIVER_HIRED => 'Hired',
            MarketplaceEvent::SHIFT_STARTING_SOON => 'Shift reminder',
            MarketplaceEvent::SHIFT_STARTED => 'Shift started',
            MarketplaceEvent::SHIFT_COMPLETED => 'Shift completed',
            MarketplaceEvent::REVIEW_RECEIVED => 'Review received',
            MarketplaceEvent::MESSAGE_RECEIVED => 'Message',
            MarketplaceEvent::PAYOUT_TRANSFERRED => 'Payout sent',
            MarketplaceEvent::PAYOUT_TRANSFER_FAILED => 'Payout issue',
            MarketplaceEvent::PAYMENT_REFUNDED => 'Refund update',
            MarketplaceEvent::REGULAR_CARE_OFFERED => 'Regular care offer',
            MarketplaceEvent::REGULAR_CARE_ACCEPTED => 'Regular care accepted',
            MarketplaceEvent::REGULAR_CARE_ENDED => 'Regular care ended',
            default => 'Update',
        };
    }

    private function eventTone(string $eventKey): string
    {
        return match ($eventKey) {
            MarketplaceEvent::APPLICATION_SUBMITTED,
            MarketplaceEvent::CAREGIVER_HIRED,
            MarketplaceEvent::SHIFT_STARTED,
            MarketplaceEvent::REGULAR_CARE_ACCEPTED => 'success',
            MarketplaceEvent::SHIFT_STARTING_SOON,
            MarketplaceEvent::REGULAR_CARE_OFFERED => 'info',
            MarketplaceEvent::REVIEW_RECEIVED => 'warning',
            MarketplaceEvent::MESSAGE_RECEIVED => 'neutral',
            MarketplaceEvent::MATCHING_REQUEST_REMINDER => 'info',
            MarketplaceEvent::SHIFT_COMPLETED => 'neutral',
            MarketplaceEvent::PAYOUT_TRANSFERRED => 'success',
            MarketplaceEvent::PAYOUT_TRANSFER_FAILED => 'warning',
            MarketplaceEvent::PAYMENT_REFUNDED,
            MarketplaceEvent::REGULAR_CARE_ENDED => 'warning',
            default => 'neutral',
        };
    }
}
