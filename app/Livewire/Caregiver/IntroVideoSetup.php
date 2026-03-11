<?php

namespace App\Livewire\Caregiver;

use App\Models\CaregiverProfile;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

#[Layout('layouts.app')]
class IntroVideoSetup extends Component
{
    use WithFileUploads;

    public CaregiverProfile $profile;
    public ?string $intro_video_path = null;
    public $intro_video = null;

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->role === 'caregiver', 403);

        $this->profile = CaregiverProfile::firstOrCreate(['user_id' => $user->id], ['status' => 'draft']);
        $this->intro_video_path = $this->profile->intro_video_path;
    }

    public function save(): void
    {
        $this->validate([
            'intro_video' => ['required', 'file', 'mimes:mp4,mov,m4v,webm', 'max:51200'],
        ]);

        $this->intro_video_path = $this->intro_video->store('caregiver-intro-videos', 'public');

        $this->profile->update([
            'intro_video_path' => $this->intro_video_path,
            'intro_video_uploaded_at' => now(),
        ]);

        session()->flash('status', 'Intro video uploaded.');
    }

    public function remove(): void
    {
        $this->profile->update([
            'intro_video_path' => null,
            'intro_video_uploaded_at' => null,
        ]);

        $this->intro_video_path = null;
        session()->flash('status', 'Intro video removed.');
    }

    public function render()
    {
        return view('livewire.caregiver.intro-video-setup');
    }
}

