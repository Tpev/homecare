<?php

namespace App\Services\FamilyAcquisition;

use App\Jobs\SendLeadWelcomeEmail;
use App\Mail\FamilyLeadWelcomeMail;
use App\Models\CareRequest;
use App\Models\FamilyAcquisitionSetting;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadWelcomeEmail;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class LeadWelcomeService
{
    public const SUBJECT = 'LoLo Care | Ready to find help at home?';

    public const BODY = "Thanks for reaching out to LoLo!\n\nYou can take the next step right now. Create your free account and post a care request describing the help you need and when you need it.\n\nIt takes about 2 minutes to get started.";

    public const SESSION_KEY = 'lead_welcome';

    public function capture(Lead $lead): void
    {
        if (! $lead->isFacebookLead() || $lead->welcomeEmail()->exists() || (bool) data_get($lead->data, 'demo')) {
            return;
        }

        $settings = FamilyAcquisitionSetting::current();
        $email = strtolower(trim((string) $lead->email));
        $message = $lead->welcomeEmail()->firstOrCreate([], [
            'email' => $email ?: null,
            'status' => 'skipped',
            'subject' => $settings->welcome_email_subject ?: self::SUBJECT,
            'body' => $settings->welcome_email_body ?: self::BODY,
            'reply_to' => $settings->welcome_email_reply_to ?: config('mail.from.address'),
        ]);
        if (! $message->wasRecentlyCreated) {
            return;
        }

        if ($reason = $this->skipReason($message, $settings)) {
            $this->skip($message, $reason);

            return;
        }

        $existingUser = User::whereRaw('LOWER(TRIM(email)) = ?', [$email])->first();
        if ($existingUser) {
            $this->linkAccount($message, $existingUser, false);
            $this->skip($message, 'An account already exists for this email.');

            return;
        }

        try {
            // The database owns the claim, including concurrent imports of the same contact.
            $message->update(['dedupe_key' => hash('sha256', $email), 'status' => 'queued']);
        } catch (UniqueConstraintViolationException) {
            $message->refresh();
            $this->skip($message, 'A welcome email is already recorded for this email address.');

            return;
        }

        $this->activity($message, 'Welcome email queued');
        try {
            SendLeadWelcomeEmail::dispatch($message->id)->afterCommit();
        } catch (Throwable $exception) {
            LeadWelcomeEmail::whereKey($message->id)->where('status', 'queued')->whereNull('last_attempt_at')
                ->update(['status' => 'failed', 'reason' => 'Could not queue the welcome email. Retry when the queue is available.']);
            report($exception);
        }
    }

    public function send(int $id): void
    {
        // Only one worker can prepare this message. A transport attempt is never replayed.
        $claimed = LeadWelcomeEmail::whereKey($id)->whereIn('status', ['queued', 'retrying'])
            ->whereNotNull('dedupe_key')->whereNull('last_attempt_at')
            ->whereNull('sent_at')->whereNull('previewed_at')->update([
                'status' => 'sending', 'reason' => null, 'attempts' => DB::raw('attempts + 1'),
            ]);
        if (! $claimed) {
            return;
        }

        try {
            $message = LeadWelcomeEmail::with('lead')->findOrFail($id);
            if ($reason = $this->skipReason($message, FamilyAcquisitionSetting::current())) {
                $this->skip($message, $reason);

                return;
            }
            if ($message->dedupe_key !== hash('sha256', strtolower(trim($message->email)))) {
                $this->skip($message, 'The recipient no longer matches the reserved email address.');

                return;
            }
            if ($message->account_linked_at || User::whereRaw('LOWER(TRIM(email)) = ?', [$message->email])->exists()) {
                $this->skip($message, 'An account already exists for this email.');

                return;
            }

            $mailerName = $this->mailer();
            // A failover transport may send twice internally after an ambiguous error.
            if (in_array(config("mail.mailers.{$mailerName}.transport"), ['failover', 'roundrobin'], true)) {
                throw new \RuntimeException('Welcome emails require a single mail transport.');
            }
            $mailer = Mail::mailer($mailerName);
            $mail = $this->mail($message);
            $mail->render();
            $local = $this->capturesMail();
        } catch (Throwable $exception) {
            LeadWelcomeEmail::whereKey($id)->where('status', 'sending')->whereNull('last_attempt_at')
                ->update(['status' => 'retrying', 'reason' => 'Could not prepare the email. An automatic retry is scheduled.']);
            throw $exception;
        }

        // Persist this boundary before contacting the provider, including across worker crashes.
        $message->update(['last_attempt_at' => now()]);
        try {
            $mailer->to($message->email)->send($mail);
        } catch (Throwable $exception) {
            $message->update(['status' => 'unconfirmed', 'reason' => 'Delivery could not be confirmed. No resend will be attempted; check the mail provider.']);
            report($exception);
            $this->activity($message, 'Welcome email delivery unconfirmed', $message->reason);

            return;
        }

        $message->update([
            'status' => $local ? 'previewed' : 'sent', 'reason' => null,
            $local ? 'previewed_at' : 'sent_at' => now(),
        ]);
        $this->activity($message, $local ? 'Welcome email preview saved locally' : 'Welcome email sent', $local ? 'Captured locally. No email was delivered.' : null);
    }

    public function skipReason(LeadWelcomeEmail $message, FamilyAcquisitionSetting $settings): ?string
    {
        $lead = $message->lead;
        if (! $lead || $lead->do_not_contact_at || $message->unsubscribed_at
            || LeadWelcomeEmail::where('email', $message->email)->whereNotNull('unsubscribed_at')->exists()
            || (filled($message->email) && Lead::whereRaw('LOWER(TRIM(email)) = ?', [$message->email])->whereNotNull('do_not_contact_at')->exists())) {
            return 'Contact has opted out.';
        }
        if (! filter_var($message->email, FILTER_VALIDATE_EMAIL)) {
            return filled($message->email) ? 'Invalid email address.' : 'No email address provided.';
        }
        if (in_array($lead->status, ['converted', 'closed', 'lost', 'not_fit', 'unreachable'], true)) {
            return 'This lead is no longer active.';
        }
        if (! $settings->welcome_email_enabled) {
            return 'Automatic welcome email was paused.';
        }

        return null;
    }

    private function skip(LeadWelcomeEmail $message, string $reason): void
    {
        $message->update(['status' => 'skipped', 'reason' => $reason]);
        $this->activity($message, 'Welcome email not sent', $reason);
    }

    public function mail(LeadWelcomeEmail $message): FamilyLeadWelcomeMail
    {
        return new FamilyLeadWelcomeMail(
            firstName: (string) str(trim((string) $message->lead?->name))->before(' '),
            emailSubject: $message->subject ?: self::SUBJECT,
            emailBody: $message->body ?: self::BODY,
            startUrl: $message->startUrl(),
            unsubscribeUrl: $message->unsubscribeUrl(),
            replyAddress: $message->reply_to ?: (string) config('mail.from.address'),
        );
    }

    public function mailer(): string
    {
        return app()->environment('local') ? 'log' : (string) config('mail.default');
    }

    public function capturesMail(): bool
    {
        return in_array($this->mailer(), ['log', 'array'], true);
    }

    public function sessionMessage(): ?LeadWelcomeEmail
    {
        if (! app()->bound('session') || (int) session(self::SESSION_KEY.'.expires', 0) < now()->timestamp) {
            return null;
        }

        return LeadWelcomeEmail::with('lead')->find(session(self::SESSION_KEY.'.id'));
    }

    public function accountRegistered(User $user): void
    {
        if ($user->role !== 'family') {
            return;
        }
        LeadWelcomeEmail::where('email', strtolower(trim($user->email)))
            ->whereNull('account_user_id')->whereNull('unsubscribed_at')
            ->each(fn (LeadWelcomeEmail $message) => $this->linkAccount($message, $user, true));
    }

    public function continueFor(User $user): bool
    {
        $message = $this->sessionMessage();
        if (! $message || $user->role !== 'family' || strtolower(trim($user->email)) !== $message->email) {
            session()->forget(self::SESSION_KEY);

            return false;
        }
        $this->linkAccount($message, $user, false);

        return true;
    }

    private function linkAccount(LeadWelcomeEmail $message, User $user, bool $created): void
    {
        if ($user->role !== 'family') {
            return;
        }
        $updated = LeadWelcomeEmail::whereKey($message->id)->whereNull('account_user_id')->update([
            'account_user_id' => $user->id, 'account_linked_at' => now(),
            'account_created_at' => $created ? now() : null,
        ]);
        if ($updated) {
            $this->activity($message, $created ? 'Free account created' : 'Existing account linked');
        }
    }

    public function requestPosted(CareRequest $request): void
    {
        if ($request->status !== CareRequest::STATUS_OPEN || $request->is_system_generated) {
            return;
        }
        $ownerId = $request->created_by_user_id ?: $request->family_user_id;
        LeadWelcomeEmail::where('account_user_id', $ownerId)->whereNull('request_posted_at')->each(function (LeadWelcomeEmail $message) use ($request): void {
            $updated = LeadWelcomeEmail::whereKey($message->id)->whereNull('request_posted_at')->update([
                'care_request_id' => $request->id, 'request_posted_at' => now(),
            ]);
            if ($updated) {
                $this->activity($message, 'Care request posted', $request->title, ['care_request_id' => $request->id]);
            }
        });
    }

    public function activity(LeadWelcomeEmail $message, string $summary, ?string $body = null, array $metadata = []): void
    {
        $message->lead?->activities()->create([
            'type' => LeadActivity::TYPE_EMAIL, 'summary' => $summary, 'body' => $body,
            'occurred_at' => now(), 'metadata' => ['source' => 'lead_welcome', 'welcome_email_id' => $message->id] + $metadata,
        ]);
    }
}
