<?php

namespace App\Livewire\Caregiver;

use App\Models\CaregiverProfile;
use App\Models\Skill;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class TaskComfortSetup extends Component
{
    public CaregiverProfile $profile;
    public array $selectedSkills = [];
    public array $skillOptions = [];

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->role === 'caregiver', 403);

        $this->profile = CaregiverProfile::firstOrCreate(['user_id' => $user->id], ['status' => 'draft']);
        $this->skillOptions = Skill::query()->orderBy('name')->get(['id', 'name'])->toArray();
        $this->selectedSkills = $this->profile->skills()->pluck('skills.id')->all();
    }

    public function save(): void
    {
        $this->validate([
            'selectedSkills' => ['required', 'array', 'min:1'],
            'selectedSkills.*' => ['integer', Rule::exists('skills', 'id')],
        ]);

        $this->profile->skills()->sync($this->selectedSkills);

        session()->flash('status', 'Task comfort preferences saved.');
    }

    public function render()
    {
        return view('livewire.caregiver.task-comfort-setup');
    }
}

