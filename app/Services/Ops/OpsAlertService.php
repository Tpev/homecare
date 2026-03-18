<?php

namespace App\Services\Ops;

use App\Mail\Ops\CaregiverReadyForReviewOpsAlertMail;
use App\Mail\Ops\UserRegisteredOpsAlertMail;
use App\Models\CaregiverProfile;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OpsAlertService
{
    public function notifyUserRegistered(User $user): void
    {
        $recipients = $this->recipients();
        if ($recipients === []) {
            return;
        }

        try {
            Mail::to($recipients)->send(new UserRegisteredOpsAlertMail($user));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function notifyCaregiverReadyForReview(User $user, CaregiverProfile $profile): void
    {
        $recipients = $this->recipients();
        if ($recipients === []) {
            return;
        }

        try {
            Mail::to($recipients)->send(new CaregiverReadyForReviewOpsAlertMail($user, $profile));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function recipients(): array
    {
        $configured = (array) config('marketplace.ops_alert_recipients', []);

        return collect($configured)
            ->map(static fn ($email) => trim((string) $email))
            ->filter(static fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }
}

