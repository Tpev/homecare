<?php

namespace App\Services\Family;

use App\Jobs\SendFamilyOnboardingEmail;
use App\Mail\Ops\FamilyOnboardingCompletedMail;
use App\Models\FamilyOnboarding;
use App\Models\FamilyOnboardingDelivery;
use App\Services\Ops\OpsAlertService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class FamilyOnboardingDeliveryService
{
    public function createIntents(FamilyOnboarding $record): void
    {
        $recipients = app(OpsAlertService::class)->recipients();
        foreach ($recipients ?: [''] as $recipient) {
            $delivery = $record->deliveries()->firstOrCreate(['recipient' => strtolower($recipient)], [
                'status' => $recipient === '' ? 'blocked' : 'pending',
                'last_error_code' => $recipient === '' ? 'missing_recipients' : null,
            ]);
            if ($delivery->wasRecentlyCreated && $recipient !== '') {
                DB::afterCommit(fn () => $this->dispatch($delivery->id));
            }
        }
    }

    public function dispatch(int $id): void
    {
        try {
            $claimed = FamilyOnboardingDelivery::query()->whereKey($id)->whereIn('status', ['pending', 'failed'])
                ->where(fn ($q) => $q->whereNull('queued_at')->orWhere('queued_at', '<=', now()->subMinutes(10)))
                ->update(['queued_at' => now()]);
            if (! $claimed) {
                return;
            }
            SendFamilyOnboardingEmail::dispatch($id);
        } catch (Throwable $exception) {
            // Dispatch happens after completion commits. Neither queue nor database
            // outages here may turn that successful submission into a failed response.
            try {
                FamilyOnboardingDelivery::query()->whereKey($id)->update(['queued_at' => null]);
            } catch (Throwable) {
                // Recovery also picks up claims older than ten minutes.
            }
            // The outbox remains pending. Never include care notes, addresses or transport text in logs.
            Log::warning('Family onboarding email dispatch deferred', ['delivery_id' => $id, 'exception_type' => $exception::class]);
        }
    }

    public function recover(int $limit = 100): int
    {
        FamilyOnboardingDelivery::query()->where('status', 'sending')->where('claimed_at', '<', now()->subMinutes(15))
            ->update(['status' => 'unconfirmed', 'last_error_code' => 'worker_interrupted']);
        if (app(OpsAlertService::class)->recipients() !== []) {
            FamilyOnboardingDelivery::query()->where('status', 'blocked')->where('last_error_code', 'missing_recipients')->with('onboarding')->limit($limit)->get()
                ->each(function ($delivery) {
                    DB::transaction(function () use ($delivery) {
                        $this->createIntents($delivery->onboarding);
                        $delivery->update(['status' => 'superseded']);
                    });
                });
        }
        if (! $this->capturesMail()) {
            FamilyOnboardingDelivery::query()->where('status', 'blocked')->where('last_error_code', 'non_delivery_mailer')
                ->update(['status' => 'pending', 'queued_at' => null, 'last_error_code' => null]);
        }
        $ids = FamilyOnboardingDelivery::query()->whereIn('status', ['pending', 'failed'])->where('attempts', '<', 5)
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('queued_at')->orWhere('queued_at', '<=', now()->subMinutes(10)))
            ->orderBy('id')->limit($limit)->pluck('id');
        foreach ($ids as $id) {
            $this->dispatch($id);
        }

        return $ids->count();
    }

    public function send(int $id): void
    {
        $delivery = DB::transaction(function () use ($id) {
            $row = FamilyOnboardingDelivery::query()->lockForUpdate()->find($id);
            if (! $row || ! in_array($row->status, ['pending', 'failed'], true) || $row->attempts >= 5
                || ($row->next_attempt_at && $row->next_attempt_at->isFuture())) {
                return null;
            }
            $row->update(['status' => 'sending', 'claimed_at' => now(), 'attempts' => $row->attempts + 1]);

            return $row;
        });
        if (! $delivery) {
            return;
        }
        $transportStarted = false;
        try {
            if ($this->capturesMail()) {
                // Do not log the family's care notes or claim provider acceptance for local mail capture.
                $delivery->update(['status' => 'blocked', 'last_error_code' => 'non_delivery_mailer', 'queued_at' => null]);

                return;
            }
            $mail = new FamilyOnboardingCompletedMail($delivery->onboarding);
            $mail->render(); // Rendering failures are safe to retry, before any transport call.
            $mailer = Mail::mailer();
            $transportStarted = true;
            $sent = $mailer->to($delivery->recipient)->send($mail);
            if ($sent === null && ! Mail::isFake()) {
                $delivery->update(['status' => 'failed', 'last_error_code' => 'mail_cancelled', 'queued_at' => null,
                    'next_attempt_at' => now()->addMinutes(2 ** $delivery->attempts)]);

                return;
            }
            $delivery->update([
                'status' => 'accepted', 'accepted_at' => now(), 'last_error_code' => null,
                'provider_message_id' => $sent?->getMessageId(), 'next_attempt_at' => null,
            ]);
        } catch (Throwable $exception) {
            // Only an explicit SMTP rejection or a pre-transport failure is automatically retried.
            // Timeouts / worker crashes can occur after acceptance and require staff reconciliation.
            $rejected = $exception instanceof TransportExceptionInterface
                && preg_match('/got code ["\']?[45]\d{2}/i', $exception->getMessage());
            $uncertain = $transportStarted && ! $rejected;
            $delivery->update([
                'status' => $uncertain ? 'unconfirmed' : 'failed',
                'queued_at' => null,
                'last_error_code' => $uncertain ? 'acceptance_unknown' : ($rejected ? 'provider_rejected' : 'mail_preparation_failed'),
                'next_attempt_at' => $uncertain || $delivery->attempts >= 5 ? null : now()->addMinutes(2 ** $delivery->attempts),
            ]);
            Log::warning('Family onboarding email needs attention', ['delivery_id' => $id, 'status' => $delivery->status]);
        }
    }

    private function capturesMail(): bool
    {
        return ! Mail::isFake() && in_array(config('mail.mailers.'.config('mail.default').'.transport'), ['log', 'array'], true);
    }
}
