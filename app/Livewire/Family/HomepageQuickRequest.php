<?php

namespace App\Livewire\Family;

use App\Models\CareTask;
use App\Support\FamilyQuickRequestDraft;
use Livewire\Component;

class HomepageQuickRequest extends Component
{
    public string $service_type = 'Companion care';

    public string $zip = '';

    public string $time_preference = 'today_afternoon';

    /** @var array<int, string> */
    public array $serviceOptions = [
        'Companion care',
        'Meal prep',
        'Errands and rides',
        'Light housekeeping',
    ];

    /** @var array<string, string> */
    public array $timeOptions = [
        'now' => 'As soon as possible',
        'today_afternoon' => 'Today, 4 PM',
        'tomorrow_morning' => 'Tomorrow morning',
        'this_week' => 'Later this week',
    ];

    public function mount(): void
    {
        $this->zip = '27601';
    }

    public function continueToRegister()
    {
        $validated = $this->validate([
            'service_type' => ['required', 'string', 'max:100'],
            'zip' => ['required', 'string', 'min:3', 'max:12'],
            'time_preference' => ['required', 'string'],
        ]);

        $taskId = $this->resolveTaskId($validated['service_type']);
        $timeLabel = $this->timeOptions[$validated['time_preference']] ?? $validated['time_preference'];

        FamilyQuickRequestDraft::put([
            'request_mode' => CreateCareRequestWizard::MODE_FAST_TRACK,
            'modeChosen' => true,
            'step' => 1,
            'request_type' => 'one_time',
            'recipient_full_name' => '',
            'selectedTasks' => $taskId ? [$taskId] : [],
            'additional_info' => trim("Homepage request lead. Service type: {$validated['service_type']}. Preferred time: {$timeLabel}."),
            'zip' => trim($validated['zip']),
        ]);

        session()->flash('status', 'Your request is started. Create your account to continue.');

        if (auth()->check() && auth()->user()?->role === 'family') {
            return $this->redirect(route('family.requests.create', absolute: false), navigate: true);
        }

        return $this->redirect(route('register', absolute: false), navigate: true);
    }

    private function resolveTaskId(string $serviceType): ?int
    {
        $normalized = mb_strtolower(trim($serviceType));

        $task = CareTask::query()
            ->get(['id', 'name'])
            ->first(function (CareTask $task) use ($normalized) {
                $name = mb_strtolower($task->name);

                if ($name === $normalized) {
                    return true;
                }

                return str_contains($normalized, $name) || str_contains($name, $normalized);
            });

        return $task?->id;
    }

    public function render()
    {
        return view('livewire.family.homepage-quick-request');
    }
}
