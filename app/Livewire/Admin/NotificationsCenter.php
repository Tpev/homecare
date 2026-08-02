<?php

namespace App\Livewire\Admin;

use App\Models\UserNotificationPreference;
use App\Services\Notifications\NotificationChannels;
use App\Support\MarketplaceNotificationPresentation;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class NotificationsCenter extends Component
{
    public string $scope = 'unread';

    public string $eventFilter = 'all';

    /** @var array<string, array<string, bool>> */
    public array $preferences = [];

    /** @var list<string> */
    public array $eventKeys = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
        $this->eventKeys = MarketplaceNotificationPresentation::eventsForRole('admin');
        $stored = UserNotificationPreference::query()
            ->where('user_id', auth()->id())
            ->whereIn('event_key', $this->eventKeys)
            ->get()
            ->keyBy('event_key');

        foreach ($this->eventKeys as $eventKey) {
            $row = $stored->get($eventKey);
            $this->preferences[$eventKey] = [
                NotificationChannels::IN_APP => $row ? (bool) $row->in_app_enabled : true,
                NotificationChannels::EMAIL => $eventKey === \App\Support\MarketplaceEvent::SUPPORT_TICKET_CREATED
                    ? false
                    : ($row ? (bool) $row->email_enabled : true),
            ];
        }
    }

    public function markRead(string $notificationId): void
    {
        $notification = auth()->user()->notifications()->whereKey($notificationId)->first();
        if ($notification && ! $notification->read_at) {
            $notification->markAsRead();
        }
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function openNotification(string $notificationId): void
    {
        $notification = auth()->user()->notifications()->whereKey($notificationId)->first();
        if (! $notification) {
            return;
        }
        if (! $notification->read_at) {
            $notification->markAsRead();
        }

        $url = (string) data_get($notification->data, 'url', '');
        $this->redirect($url !== '' ? $url : route('dashboard'), navigate: true);
    }

    public function savePreferences(): void
    {
        $this->validate([
            'preferences' => ['array'],
            'preferences.*.in_app' => ['boolean'],
            'preferences.*.email' => ['boolean'],
        ]);

        foreach ($this->eventKeys as $eventKey) {
            $row = $this->preferences[$eventKey] ?? [];
            UserNotificationPreference::query()->updateOrCreate(
                ['user_id' => auth()->id(), 'event_key' => $eventKey],
                [
                    'in_app_enabled' => (bool) ($row[NotificationChannels::IN_APP] ?? true),
                    'email_enabled' => $eventKey === \App\Support\MarketplaceEvent::SUPPORT_TICKET_CREATED
                        ? false
                        : (bool) ($row[NotificationChannels::EMAIL] ?? true),
                    'sms_enabled' => false,
                    'push_enabled' => false,
                ],
            );
        }

        session()->flash('status', 'Notification preferences saved.');
    }

    public function render()
    {
        $query = auth()->user()->notifications();
        if ($this->scope === 'unread') {
            $query->whereNull('read_at');
        } elseif ($this->scope === 'read') {
            $query->whereNotNull('read_at');
        }
        if ($this->eventFilter !== 'all') {
            $query->where('data->event_key', $this->eventFilter);
        }

        $notifications = $query->latest()->limit(80)->get()->map(fn (DatabaseNotification $notification): array => [
            'id' => $notification->id,
            'event_key' => (string) data_get($notification->data, 'event_key', 'generic'),
            'event_label' => MarketplaceNotificationPresentation::label((string) data_get($notification->data, 'event_key', 'generic')),
            'title' => (string) data_get($notification->data, 'title', 'LoLo Care update'),
            'body' => (string) data_get($notification->data, 'body', ''),
            'details' => collect((array) data_get($notification->data, 'payload.email_details', []))->take(3)->values()->all(),
            'url' => (string) data_get($notification->data, 'url', ''),
            'read_at' => $notification->read_at,
            'created_at' => $notification->created_at,
            'tone' => MarketplaceNotificationPresentation::tone((string) data_get($notification->data, 'event_key', 'generic')),
        ]);

        return view('livewire.admin.notifications-center', [
            'notifications' => $notifications,
            'unreadCount' => auth()->user()->unreadNotifications()->count(),
            'eventOptions' => collect($this->eventKeys)->map(fn (string $key): array => [
                'label' => MarketplaceNotificationPresentation::label($key),
                'value' => $key,
            ])->all(),
        ]);
    }
}
