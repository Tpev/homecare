<?php

namespace App\Observers;

use App\Models\User;
use App\Services\Marketing\RegisteredCaregiverCount;
use Throwable;

class UserObserver
{
    public function saving(User $user): void
    {
        if ((! $user->exists || $user->isDirty('email')) && \App\Support\StaffAccess::blocksEmail((string) $user->email)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['email' => 'This email address cannot be used for an account.']);
        }
        if ($user->exists && $user->getRawOriginal('access_revoked_at') !== null
            && ($user->access_revoked_at === null || $user->role !== 'offboarded' || $user->content_role !== null)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['user' => 'This staff account has been permanently offboarded.']);
        }
    }

    public function created(User $user): void
    {
        if ($user->role === 'caregiver') {
            $this->refreshAfterCommit($user);
        }
    }

    public function updated(User $user): void
    {
        if ($user->wasChanged('role')
            && ($user->role === 'caregiver' || $user->getOriginal('role') === 'caregiver')) {
            $this->refreshAfterCommit($user);
        }
    }

    public function deleted(User $user): void
    {
        if ($user->role === 'caregiver') {
            $this->refreshAfterCommit($user);
        }
    }

    private function refreshAfterCommit(User $user): void
    {
        $connectionName = $user->getConnectionName();

        // Inspect the change now: a later save can change this model's dirty state.
        // Recount after commit so rollbacks and concurrent account changes stay correct.
        $user->getConnection()->afterCommit(function () use ($connectionName): void {
            try {
                app(RegisteredCaregiverCount::class)->refresh($connectionName);
            } catch (Throwable $exception) {
                // The account is already committed. Keep signup working; the
                // scheduled refresh repairs the statistic after a transient failure.
                report($exception);
            }
        });
    }
}
