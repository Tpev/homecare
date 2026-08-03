<?php

namespace App\Support;

use App\Models\CaregiverProfile;
use App\Models\User;

class CaregiverOnboardingState
{
    public const STEP_PROFILE_BASICS = 'profile_basics';

    public const STEP_CARE_BACKGROUND = 'care_background';

    public const STEP_IDENTITY = 'identity_verification';

    public const STEP_TASKS = 'task_comfort';

    public const STEP_INSURANCE = 'insurance';

    public const STEP_VIDEO = 'intro_video';

    public const STEP_PAYOUT = 'payout_setup';

    /**
     * @return array{
     *   profile: \App\Models\CaregiverProfile,
     *   status: string,
     *   onboarding_mode: bool,
     *   is_under_review: bool,
     *   is_active: bool,
     *   is_suspended: bool,
     *   rejection_reason: ?string,
     *   required_total: int,
     *   required_completed: int,
     *   ready_for_review: bool,
     *   can_submit_for_review: bool,
     *   required_steps: array<int, array<string, mixed>>,
     *   optional_steps: array<int, array<string, mixed>>,
     *   all_steps: array<int, array<string, mixed>>,
     *   next_required_step: ?array<string, mixed>,
     *   next_required_route: string,
     *   next_required_label: string,
     *   progress_percent: int,
     * }
     */
    public function build(User $user): array
    {
        $profile = CaregiverProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['status' => 'draft']
        );

        $basicsComplete = filled($profile->bio)
            && ! is_null($profile->years_experience)
            && filled($profile->service_area_zip)
            && ! is_null($profile->service_radius_miles)
            && $profile->languages()->exists()
            && $profile->availabilities()->exists()
            && filled($user->date_of_birth)
            && filled($user->city)
            && filled($user->state);

        $identityComplete = $profile->hasIdentityVerifiedBadge();
        $tasksComplete = $profile->skills()->exists();
        $insuranceComplete = $profile->insuranceIsComplete();
        $videoComplete = filled($profile->intro_video_path);
        $payoutComplete = $profile->stripeConnectIsReady();

        $requiredSteps = [
            [
                'key' => self::STEP_PROFILE_BASICS,
                'title' => 'Profile basics',
                'description' => 'Complete your profile, languages, service area, and availability.',
                'route' => route('caregiver.onboarding'),
                'cta' => 'Complete basics',
                'required' => true,
                'done' => $basicsComplete,
                'minutes' => 4,
            ],
        ];

        $backgroundStep = [
            'key' => self::STEP_CARE_BACKGROUND,
            'title' => 'Experience & training',
            'description' => 'Share general care experience and current credentials with families.',
            'route' => route('caregiver.onboarding', ['step' => 2]),
            'cta' => 'Add experience',
            'required' => $profile->requiresCareBackground(),
            'done' => $profile->careBackgroundIsAnswered(),
            'minutes' => 3,
        ];

        if ($profile->requiresCareBackground()) {
            $requiredSteps[] = $backgroundStep;
        }

        $requiredSteps = [
            ...$requiredSteps,
            [
                'key' => self::STEP_IDENTITY,
                'title' => 'Identity verification',
                'description' => 'Verify your ID and selfie through Didit.',
                'route' => route('caregiver.verification.show'),
                'cta' => 'Verify identity',
                'required' => true,
                'done' => $identityComplete,
                'minutes' => 2,
            ],
            [
                'key' => self::STEP_TASKS,
                'title' => 'Task comfort',
                'description' => 'Pick exactly which non-medical tasks you are comfortable doing.',
                'route' => route('caregiver.tasks.edit'),
                'cta' => 'Choose tasks',
                'required' => true,
                'done' => $tasksComplete,
                'minutes' => 1,
            ],
        ];

        $optionalSteps = [
            ...(! $profile->requiresCareBackground() ? [$backgroundStep] : []),
            [
                'key' => self::STEP_INSURANCE,
                'title' => 'Insurance setup',
                'description' => 'Optional. Share insurance status and proof if available.',
                'route' => route('caregiver.insurance.edit'),
                'cta' => 'Set insurance',
                'required' => false,
                'done' => $insuranceComplete,
                'minutes' => 1,
            ],
            [
                'key' => self::STEP_VIDEO,
                'title' => 'Intro video',
                'description' => 'Optional. Usually improves profile conversion.',
                'route' => route('caregiver.video.edit'),
                'cta' => 'Add intro video',
                'required' => false,
                'done' => $videoComplete,
                'minutes' => 2,
            ],
            [
                'key' => self::STEP_PAYOUT,
                'title' => 'Payout setup',
                'description' => 'Optional pre-launch. Connect Stripe for future payouts.',
                'route' => route('caregiver.payouts.connect.show'),
                'cta' => 'Connect payouts',
                'required' => false,
                'done' => $payoutComplete,
                'minutes' => 2,
            ],
        ];

        $requiredCompleted = collect($requiredSteps)->where('done', true)->count();
        $requiredTotal = count($requiredSteps);
        $readyForReview = $requiredCompleted >= $requiredTotal;

        $status = (string) ($profile->status ?: 'draft');
        $isActive = $status === 'active';
        $isUnderReview = $status === 'under_review';
        $isSuspended = $status === 'suspended';
        $onboardingMode = ! $isActive;

        $nextRequiredStep = collect($requiredSteps)->first(fn (array $step) => ! $step['done']);

        $canSubmitForReview = $readyForReview && in_array($status, ['draft', 'suspended'], true);

        $nextRequiredRoute = $canSubmitForReview
            ? route('caregiver.onboarding', ['step' => 5])
            : ($nextRequiredStep['route'] ?? route('caregiver.setup.index'));

        $nextRequiredLabel = $canSubmitForReview
            ? 'Submit for review'
            : ($nextRequiredStep['cta'] ?? 'Continue setup');

        return [
            'profile' => $profile,
            'status' => $status,
            'onboarding_mode' => $onboardingMode,
            'is_under_review' => $isUnderReview,
            'is_active' => $isActive,
            'is_suspended' => $isSuspended,
            'rejection_reason' => $profile->rejection_reason,
            'required_total' => $requiredTotal,
            'required_completed' => $requiredCompleted,
            'ready_for_review' => $readyForReview,
            'can_submit_for_review' => $canSubmitForReview,
            'required_steps' => $requiredSteps,
            'optional_steps' => $optionalSteps,
            'all_steps' => array_merge($requiredSteps, $optionalSteps),
            'next_required_step' => $nextRequiredStep,
            'next_required_route' => $nextRequiredRoute,
            'next_required_label' => $nextRequiredLabel,
            'progress_percent' => (int) round(($requiredCompleted / max(1, $requiredTotal)) * 100),
        ];
    }

    public function nextRequiredRoute(User $user): string
    {
        $state = $this->build($user);

        return (string) $state['next_required_route'];
    }

    public function trackHubViewed(User $user): void
    {
        $state = $this->build($user);

        FunnelTracker::track('caregiver_onboarding_hub_viewed', $user, $state['profile'], [
            'required_completed' => $state['required_completed'],
            'required_total' => $state['required_total'],
            'status' => $state['status'],
        ]);
    }

    public function trackStepViewed(User $user, string $stepKey): void
    {
        $state = $this->build($user);

        FunnelTracker::track('caregiver_onboarding_step_viewed', $user, $state['profile'], [
            'step' => $stepKey,
            'required_completed' => $state['required_completed'],
        ]);
    }

    public function trackStepCompleted(User $user, string $stepKey): void
    {
        $state = $this->build($user);

        FunnelTracker::track('caregiver_onboarding_step_completed', $user, $state['profile'], [
            'step' => $stepKey,
            'required_completed' => $state['required_completed'],
            'required_total' => $state['required_total'],
        ]);
    }

    public function trackStepError(User $user, string $stepKey, array $errors): void
    {
        $state = $this->build($user);

        FunnelTracker::track('caregiver_onboarding_step_error', $user, $state['profile'], [
            'step' => $stepKey,
            'fields' => array_keys($errors),
        ]);
    }
}
