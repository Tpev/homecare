<?php

namespace App\Livewire\Admin;

use App\Models\CaregiverCertification;
use App\Models\CaregiverIdentityVerification;
use App\Models\CaregiverModerationLog;
use App\Models\CareRequest;
use App\Models\FamilyAccount;
use App\Models\FamilyAccountMember;
use App\Models\User;
use App\Services\Caregiver\CaregiverCertificationReviewService;
use App\Services\FamilyAccounts\FamilyAccountOwnershipService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class UserShow extends Component
{
    public User $user;

    public array $certificationRejectionReasons = [];

    public ?int $transferOwnershipMemberId = null;

    public string $transferReason = '';

    public string $notificationEmail = '';

    public function mount(User $user): void
    {
        $this->user = $this->loadUser($user->id);
        $this->notificationEmail = (string) ($this->user->notification_email ?? '');
    }

    public function saveNotificationEmail(): void
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
        abort_unless($this->user->isAdministrator(), 404);

        $this->notificationEmail = trim($this->notificationEmail);
        $validated = $this->validate([
            'notificationEmail' => ['nullable', 'email', 'max:255'],
        ]);
        $notificationEmail = Str::lower(trim((string) ($validated['notificationEmail'] ?? '')));

        $this->user->forceFill([
            'notification_email' => $notificationEmail !== '' ? $notificationEmail : null,
        ])->save();

        $this->user = $this->loadUser($this->user->id);
        $this->notificationEmail = (string) ($this->user->notification_email ?? '');
        session()->flash('status', 'Administrator notification email updated. Login and account-security email are unchanged.');
    }

    public function approveIdentityVerification(): void
    {
        $caregiverProfile = $this->user->caregiverProfile;

        if (! $caregiverProfile) {
            $this->addError('identity', 'No caregiver profile exists for this user.');

            return;
        }

        if ($caregiverProfile->identity_verification_status === CaregiverIdentityVerification::STATUS_APPROVED
            && $caregiverProfile->identity_verified_at) {
            session()->flash('status', 'Identity verification is already marked approved.');

            return;
        }

        DB::transaction(function () use ($caregiverProfile): void {
            $verification = $caregiverProfile->latestIdentityVerification;

            if ($verification) {
                $verification->forceFill([
                    'status' => CaregiverIdentityVerification::STATUS_APPROVED,
                    'decision_payload' => array_filter([
                        ...((array) $verification->decision_payload),
                        'source' => 'admin_override',
                        'approved_by_user_id' => auth()->id(),
                        'approved_at' => now()->toIso8601String(),
                    ]),
                    'webhook_payload' => [
                        'source' => 'admin_override',
                        'approved_by_user_id' => auth()->id(),
                    ],
                    'last_webhook_at' => now(),
                    'completed_at' => now(),
                    'approved_at' => now(),
                    'declined_at' => null,
                    'failure_reason' => null,
                ])->save();
            } else {
                CaregiverIdentityVerification::query()->create([
                    'caregiver_profile_id' => $caregiverProfile->id,
                    'user_id' => $this->user->id,
                    'didit_session_id' => 'admin-override-'.Str::lower((string) Str::uuid()),
                    'status' => CaregiverIdentityVerification::STATUS_APPROVED,
                    'vendor_data' => 'admin_override_user_'.$this->user->id.'_profile_'.$caregiverProfile->id,
                    'decision_payload' => [
                        'source' => 'admin_override',
                        'approved_by_user_id' => auth()->id(),
                        'approved_at' => now()->toIso8601String(),
                    ],
                    'webhook_payload' => [
                        'source' => 'admin_override',
                        'approved_by_user_id' => auth()->id(),
                    ],
                    'started_at' => now(),
                    'completed_at' => now(),
                    'approved_at' => now(),
                    'last_webhook_at' => now(),
                ]);
            }

            $caregiverProfile->forceFill([
                'identity_verification_status' => CaregiverIdentityVerification::STATUS_APPROVED,
                'identity_verification_checked_at' => now(),
                'identity_verified_at' => $caregiverProfile->identity_verified_at ?: now(),
            ])->save();

            CaregiverModerationLog::query()->create([
                'caregiver_profile_id' => $caregiverProfile->id,
                'actor_user_id' => auth()->id(),
                'action' => 'identity_admin_verified',
                'note' => 'Admin manually approved identity verification.',
                'meta' => [
                    'source' => 'admin_override',
                    'status' => CaregiverIdentityVerification::STATUS_APPROVED,
                ],
            ]);
        });

        $this->user = $this->loadUser($this->user->id);
        session()->flash('status', 'Identity verification approved by admin.');
    }

    public function verifyCertification(int $id, CaregiverCertificationReviewService $review): void
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
        $certification = $this->ownedCertification($id);
        $review->verify(auth()->user(), $certification);
        $this->user = $this->loadUser($this->user->id);
        session()->flash('status', 'Credential verified.');
    }

    public function rejectCertification(int $id, CaregiverCertificationReviewService $review): void
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
        $certification = $this->ownedCertification($id);
        $review->reject(auth()->user(), $certification, (string) ($this->certificationRejectionReasons[$id] ?? ''));
        unset($this->certificationRejectionReasons[$id]);
        $this->user = $this->loadUser($this->user->id);
        session()->flash('status', 'Credential returned to the caregiver for attention.');
    }

    public function transferFamilyOwnership(FamilyAccountOwnershipService $ownership): void
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
        $validated = $this->validate([
            'transferOwnershipMemberId' => ['required', 'integer'],
            'transferReason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $account = $this->familyAccountForUser();
        abort_unless($account, 404);
        $destination = $account->activeMemberships()->findOrFail((int) $validated['transferOwnershipMemberId']);

        $ownership->transfer(auth()->user(), $account, $destination, $validated['transferReason']);
        $this->reset(['transferOwnershipMemberId', 'transferReason']);
        $this->user = $this->loadUser($this->user->id);
        session()->flash('status', 'Family Account ownership transferred and recorded in the audit history.');
    }

    public function render(): View
    {
        $caregiverProfile = $this->user->caregiverProfile;

        $familyAccount = $this->user->role === 'family' ? $this->familyAccountForUser() : null;
        $latestFamilyRequests = $familyAccount
            ? CareRequest::query()->forFamilyAccount($familyAccount)->latest()->limit(6)->get()
            : collect();

        $latestCaregiverApplications = $this->user->role === 'caregiver'
            ? $this->user->careRequestApplications()->with('careRequest')->latest()->limit(6)->get()
            : collect();

        $latestCaregiverBookings = $this->user->role === 'caregiver'
            ? $this->user->caregiverBookings()->with('careRequest')->latest()->limit(6)->get()
            : collect();

        return view('livewire.admin.user-show', [
            'caregiverProfile' => $caregiverProfile,
            'latestFamilyRequests' => $latestFamilyRequests,
            'latestCaregiverApplications' => $latestCaregiverApplications,
            'latestCaregiverBookings' => $latestCaregiverBookings,
            'familyAccount' => $familyAccount?->load([
                'owner:id,name,email',
                'memberships' => fn ($query) => $query->with('user:id,name,email,role')->latest('joined_at'),
                'invitations' => fn ($query) => $query->latest(),
                'activityLogs' => fn ($query) => $query->with(['actor:id,name,email', 'subjectUser:id,name,email'])->latest('created_at')->limit(30),
            ]),
        ]);
    }

    private function loadUser(int $userId): User
    {
        return User::query()
            ->with([
                'caregiverProfile.skills',
                'caregiverProfile.languages',
                'caregiverProfile.availabilities',
                'caregiverProfile.careExperiences',
                'caregiverProfile.certifications.type',
                'caregiverProfile.certifications.verifier',
                'caregiverProfile.latestIdentityVerification',
            ])
            ->findOrFail($userId);
    }

    private function ownedCertification(int $id): CaregiverCertification
    {
        $profileId = $this->user->caregiverProfile?->id;
        abort_unless($profileId, 404);

        return CaregiverCertification::query()
            ->where('caregiver_profile_id', $profileId)
            ->findOrFail($id);
    }

    private function familyAccountForUser(): ?FamilyAccount
    {
        return FamilyAccountMember::query()
            ->where('user_id', $this->user->id)
            ->latest('joined_at')
            ->first()
            ?->familyAccount;
    }
}
