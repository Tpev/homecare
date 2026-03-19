<?php

namespace App\Services\Caregiver;

use App\Models\CaregiverProfile;
use App\Models\User;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Support\MarketplaceEvent;

class CaregiverOnboardingEmailService
{
    public function __construct(
        private readonly MarketplaceNotificationService $notifications,
    ) {
    }

    public function sendWelcome(User $user): void
    {
        if ($user->role !== 'caregiver') {
            return;
        }

        $this->notifications->notify(
            recipients: $user,
            eventKey: MarketplaceEvent::CAREGIVER_WELCOME,
            title: 'Welcome to HomeCare - let\'s get you caregiver-ready',
            body: 'You are just a few steps away from receiving your first opportunities.',
            url: route('caregiver.onboarding'),
            payload: [
                'first_name' => $this->firstName($user->name),
                'preheader' => 'Complete your caregiver profile and KYC to start receiving matches.',
                'checklist' => [
                    'Complete your profile basics',
                    'Set your availability',
                    'Choose your task preferences',
                    'Finish identity verification (KYC)',
                ],
            ],
            subject: $user,
            dedupeKey: 'caregiver-onboarding-welcome:user-'.$user->id
        );
    }

    public function send24HourReminderIfIncomplete(User $user): bool
    {
        if ($user->role !== 'caregiver' || $this->isProfileAndKycComplete($user)) {
            return false;
        }

        $checklist = $this->incompleteChecklist($user);
        if ($checklist === []) {
            return false;
        }

        $this->notifications->notify(
            recipients: $user,
            eventKey: MarketplaceEvent::CAREGIVER_ONBOARDING_REMINDER_24H,
            title: 'You\'re almost there - finish your caregiver setup',
            body: 'Complete your profile and KYC so families can find and trust your profile.',
            url: route('caregiver.onboarding'),
            payload: [
                'first_name' => $this->firstName($user->name),
                'preheader' => 'Quick reminder: finish setup to unlock caregiver opportunities.',
                'checklist' => $checklist,
            ],
            subject: $user,
            dedupeKey: 'caregiver-onboarding-reminder-24h:user-'.$user->id
        );

        return true;
    }

    public function isProfileAndKycComplete(User $user): bool
    {
        $profile = $user->caregiverProfile;
        if (! $profile) {
            return false;
        }

        $checks = $profile->marketplaceReadinessChecks();

        return (bool) ($checks['bio'] ?? false)
            && (bool) ($checks['years_experience'] ?? false)
            && (bool) ($checks['service_area_zip'] ?? false)
            && (bool) ($checks['service_radius_miles'] ?? false)
            && (bool) ($checks['tasks'] ?? false)
            && (bool) ($checks['languages'] ?? false)
            && (bool) ($checks['availability'] ?? false)
            && (bool) ($checks['identity_verification'] ?? false);
    }

    /**
     * @return list<string>
     */
    private function incompleteChecklist(User $user): array
    {
        $profile = $user->caregiverProfile ?: CaregiverProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['status' => 'draft']
        );
        $checks = $profile->marketplaceReadinessChecks();

        $items = [];

        if (! ($checks['bio'] ?? false) || ! ($checks['years_experience'] ?? false) || ! ($checks['service_area_zip'] ?? false) || ! ($checks['service_radius_miles'] ?? false)) {
            $items[] = 'Complete profile basics (bio, experience, service area)';
        }
        if (! ($checks['languages'] ?? false)) {
            $items[] = 'Add at least one language';
        }
        if (! ($checks['availability'] ?? false)) {
            $items[] = 'Set your availability schedule';
        }
        if (! ($checks['tasks'] ?? false)) {
            $items[] = 'Choose your task comfort preferences';
        }
        if (! ($checks['identity_verification'] ?? false)) {
            $items[] = 'Finish identity verification (KYC)';
        }

        return $items;
    }

    private function firstName(string $name): string
    {
        $value = trim($name);
        if ($value === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $value) ?: [];

        return (string) ($parts[0] ?? $value);
    }
}

