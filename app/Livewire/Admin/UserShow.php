<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class UserShow extends Component
{
    public User $user;

    public function mount(User $user): void
    {
        $this->user = $user->load([
            'caregiverProfile.skills',
            'caregiverProfile.languages',
            'caregiverProfile.availabilities',
        ]);
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
}

