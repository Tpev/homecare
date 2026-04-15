<?php

namespace App\Livewire\Admin;

use App\Models\CaregiverIdentityVerification;
use App\Models\CaregiverModerationLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class UserShow extends Component
{
    public User $user;

    public function mount(User $user): void
    {
        $this->user = $this->loadUser($user->id);
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

    public function render(): View
    {
        $caregiverProfile = $this->user->caregiverProfile;

        $latestFamilyRequests = $this->user->role === 'family'
            ? $this->user->careRequests()->latest()->limit(6)->get()
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
        ]);
    }

    private function loadUser(int $userId): User
    {
        return User::query()
            ->with([
                'caregiverProfile.skills',
                'caregiverProfile.languages',
                'caregiverProfile.availabilities',
                'caregiverProfile.latestIdentityVerification',
            ])
            ->findOrFail($userId);
    }
}
