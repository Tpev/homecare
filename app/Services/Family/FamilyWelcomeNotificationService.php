<?php

namespace App\Services\Family;

use App\Models\User;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Support\MarketplaceEvent;

class FamilyWelcomeNotificationService
{
    public function __construct(private readonly MarketplaceNotificationService $notifications) {}

    public function send(User $user): void
    {
        if (! in_array($user->role, ['family', null, ''], true)) {
            return;
        }

        $this->notifications->notify(
            recipients: $user,
            eventKey: MarketplaceEvent::FAMILY_WELCOME,
            title: 'Welcome to LoLo Care',
            body: 'Tell us what kind of support you need, then review and choose caregivers who fit your family.',
            url: route('family.requests.create'),
            payload: [
                'first_name' => str($user->name)->before(' ')->toString(),
                'preheader' => 'Create your first care request and start finding the right support with LoLo Care.',
                'email_next_steps' => [
                    'Create a care request with the schedule, location, and support you need.',
                    'Review caregiver profiles and applications in your LoLo Care account.',
                    'Message caregivers before choosing who you would like to work with.',
                ],
            ],
            subject: $user,
            dedupeKey: 'family-welcome:user-'.$user->id,
        );
    }
}
