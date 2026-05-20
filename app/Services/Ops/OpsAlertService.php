<?php

namespace App\Services\Ops;

use App\Mail\Ops\CaregiverReadyForReviewOpsAlertMail;
use App\Mail\Ops\CallbackRequestOpsAlertMail;
use App\Mail\Ops\FamilyRegisteredOpsAlertMail;
use App\Mail\Ops\NewCareRequestOpsAlertMail;
use App\Mail\Ops\UserRegisteredOpsAlertMail;
use App\Mail\Ops\VoiceCallReportOpsAlertMail;
use App\Models\CareRequest;
use App\Models\CaregiverProfile;
use App\Models\Lead;
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

    public function notifyFamilyRegistered(User $user): void
    {
        $recipients = $this->recipients();
        if ($recipients === []) {
            return;
        }

        try {
            Mail::to($recipients)->send(new FamilyRegisteredOpsAlertMail($user));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function notifyCareRequestCreated(CareRequest $careRequest): void
    {
        $recipients = $this->recipients();
        if ($recipients === []) {
            return;
        }

        try {
            Mail::to($recipients)->send(new NewCareRequestOpsAlertMail($careRequest));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function notifyCallbackRequestCreated(Lead $lead): void
    {
        $recipients = $this->recipients();
        if ($recipients === []) {
            return;
        }

        try {
            Mail::to($recipients)->send(new CallbackRequestOpsAlertMail($lead));
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

    public function notifyVoiceCallReported(array $report): void
    {
        $recipients = $this->recipients();
        if ($recipients === []) {
            return;
        }

        try {
            Mail::to($recipients)->send(new VoiceCallReportOpsAlertMail($report));
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
