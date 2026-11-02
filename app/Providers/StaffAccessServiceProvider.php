<?php

namespace App\Providers;

use App\Listeners\SuppressOffboardedStaffMail;
use App\Models\User;
use App\Support\StaffAccess;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class StaffAccessServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The same provider gates password login, session/remember-cookie lookup,
        // and the password broker. Historical model relations remain readable.
        Auth::provider('active-eloquent', fn ($app, array $config) => (new EloquentUserProvider($app['hash'], $config['model']))
            ->withQuery(fn ($query) => StaffAccess::allowed($query)));
        Event::listen(MessageSending::class, SuppressOffboardedStaffMail::class);
        Event::listen(NotificationSending::class, fn (NotificationSending $event) => $event->notifiable instanceof User && ! StaffAccess::allowsUser($event->notifiable) ? false : null);
    }
}
