<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class UsersIndex extends Component
{
    use WithPagination;

    public string $q = '';
    public string $role = 'all';
    public int $perPage = 25;

    protected $queryString = [
        'q' => ['except' => ''],
        'role' => ['except' => 'all'],
        'perPage' => ['except' => 25],
    ];

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function updatingRole(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function deleteUser(int $userId): void
    {
        $target = User::query()->findOrFail($userId);

        if ((int) $target->id === (int) auth()->id()) {
            $this->addError('delete', 'You cannot delete your own admin account.');

            return;
        }

        if ($this->isAdminUser($target)) {
            $this->addError('delete', 'Admin users cannot be deleted from this screen.');

            return;
        }

        try {
            $target->delete();
        } catch (QueryException) {
            $this->addError('delete', 'Could not delete this user because related records are protected.');

            return;
        }
    }

    public function loginAs(int $userId): void
    {
        $admin = auth()->user();
        abort_unless($admin && $this->isAdminUser($admin), 403);

        $target = User::query()->findOrFail($userId);

        if ((int) $target->id === (int) $admin->id) {
            $this->addError('loginAs', 'You are already logged in as this admin account.');

            return;
        }

        if ($this->isAdminUser($target)) {
            $this->addError('loginAs', 'Login as is only available for caregiver/family accounts.');

            return;
        }

        auth()->login($target);
        session()->regenerate();

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }

    public function render(): View
    {
        $query = User::query()
            ->with([
                'caregiverProfile' => fn ($profileQuery) => $profileQuery
                    ->withCount(['skills', 'languages', 'availabilities']),
            ]);

        if ($this->role !== 'all') {
            $query->where('role', $this->role);
        }

        if ($this->q !== '') {
            $term = trim($this->q);
            $query->where(function ($subQuery) use ($term) {
                $subQuery
                    ->where('name', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%')
                    ->orWhere('city', 'like', '%'.$term.'%')
                    ->orWhere('state', 'like', '%'.$term.'%');
            });
        }

        $users = $query->latest('created_at')->paginate($this->perPage);
        $reviewReadiness = $this->buildReviewReadinessMap($users->getCollection());

        return view('livewire.admin.users-index', [
            'users' => $users,
            'reviewReadiness' => $reviewReadiness,
            'roleOptions' => [
                ['label' => 'All user types', 'value' => 'all'],
                ['label' => 'Caregivers', 'value' => 'caregiver'],
                ['label' => 'Families', 'value' => 'family'],
                ['label' => 'Admins', 'value' => 'admin'],
            ],
        ]);
    }

    /**
     * @param Collection<int, User> $users
     * @return array<int, array{status: string, status_label: string, missing: array<int, string>}>
     */
    private function buildReviewReadinessMap(Collection $users): array
    {
        $map = [];

        foreach ($users as $user) {
            if ((string) $user->role !== 'caregiver') {
                continue;
            }

            $profile = $user->caregiverProfile;
            $missing = [];

            if (! $profile) {
                $missing = ['Profile basics', 'Identity verification', 'Task comfort selection'];
            } else {
                $basicsComplete = filled($profile->bio)
                    && ! is_null($profile->years_experience)
                    && filled($profile->service_area_zip)
                    && ! is_null($profile->service_radius_miles)
                    && (int) ($profile->languages_count ?? 0) > 0
                    && (int) ($profile->availabilities_count ?? 0) > 0;
                $identityComplete = $profile->hasIdentityVerifiedBadge();
                $tasksComplete = (int) ($profile->skills_count ?? 0) > 0;

                if (! $basicsComplete) {
                    $missing[] = 'Profile basics';
                }
                if (! $identityComplete) {
                    $missing[] = 'Identity verification';
                }
                if (! $tasksComplete) {
                    $missing[] = 'Task comfort selection';
                }
            }

            $status = 'missing';
            $statusLabel = 'Missing required steps';

            if ($profile && $profile->status === 'under_review') {
                $status = 'under_review';
                $statusLabel = 'Under review';
            } elseif ($profile && $profile->status === 'active') {
                $status = 'active';
                $statusLabel = 'Active profile';
            } elseif ($missing === []) {
                $status = 'ready';
                $statusLabel = 'Ready for review submission';
            }

            $map[(int) $user->id] = [
                'status' => $status,
                'status_label' => $statusLabel,
                'missing' => $missing,
            ];
        }

        return $map;
    }

    private function isAdminUser(User $user): bool
    {
        return $user->role === 'admin' || strtolower((string) $user->email) === 'test@test.com';
    }
}
