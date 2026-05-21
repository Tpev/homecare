<?php

namespace App\Console\Commands;

use App\Mail\CaregiverLaunchEmail;
use App\Models\MarketplaceNotificationDelivery;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendCaregiverLaunchEmail extends Command
{
    private const EVENT_KEY = 'caregiver_lolo_launch_2026_05';

    private const DEDUPE_PREFIX = 'caregiver-lolo-launch-2026-05';

    protected $signature = 'lolo:send-caregiver-launch-email
        {--to=tpeverelli@hub.healthcare : Send a single test email to this address when --all is not used.}
        {--all : Send to every caregiver account.}
        {--force : Skip the confirmation prompt when sending to all caregivers.}
        {--resend : Send again even if this launch email was already logged for a caregiver.}
        {--chunk=100 : Number of caregivers to process per query chunk.}
        {--sleep-ms=0 : Pause this many milliseconds between production sends.}';

    protected $description = 'Send the LoLo Care launch email to a test recipient or all caregiver accounts.';

    public function handle(): int
    {
        if (! (bool) $this->option('all')) {
            return $this->sendTestEmail();
        }

        return $this->sendToCaregivers();
    }

    private function sendTestEmail(): int
    {
        $email = trim((string) $this->option('to'));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid --to email address.');

            return self::FAILURE;
        }

        try {
            Mail::to($email)->send(new CaregiverLaunchEmail);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Test email failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Test launch email sent to '.$email.'.');

        return self::SUCCESS;
    }

    private function sendToCaregivers(): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $sleepMs = max(0, (int) $this->option('sleep-ms'));
        $resend = (bool) $this->option('resend');

        $query = $this->caregiverQuery();
        $total = (clone $query)->count();

        if ($total === 0) {
            $this->warn('No caregiver accounts with email addresses were found.');

            return self::SUCCESS;
        }

        if (! (bool) $this->option('force')) {
            $confirmed = $this->confirm(
                'Send the LoLo Care launch email to '.$total.' caregiver account(s)?',
                false
            );

            if (! $confirmed) {
                $this->warn('Launch email send cancelled.');

                return self::FAILURE;
            }
        }

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        $query->chunkById($chunkSize, function ($caregivers) use (&$sent, &$skipped, &$failed, $sleepMs, $resend): void {
            foreach ($caregivers as $caregiver) {
                if (! $resend && $this->alreadySent($caregiver)) {
                    $skipped++;

                    continue;
                }

                $delivery = $this->startDelivery($caregiver, $resend);

                try {
                    Mail::to($caregiver->email)->send(new CaregiverLaunchEmail($caregiver));

                    $delivery->forceFill([
                        'status' => 'sent',
                        'sent_at' => now(),
                    ])->save();

                    $sent++;
                } catch (Throwable $exception) {
                    report($exception);

                    $delivery->forceFill([
                        'status' => 'failed',
                        'payload' => array_merge($delivery->payload ?? [], [
                            'provider_error' => $exception->getMessage(),
                        ]),
                        'sent_at' => now(),
                    ])->save();

                    $failed++;
                    $this->error('Failed sending to '.$caregiver->email.': '.$exception->getMessage());
                }

                if ($sleepMs > 0) {
                    usleep($sleepMs * 1000);
                }
            }
        });

        $this->info(sprintf(
            'LoLo Care launch email complete. Sent: %d. Skipped: %d. Failed: %d.',
            $sent,
            $skipped,
            $failed
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return Builder<User>
     */
    private function caregiverQuery(): Builder
    {
        return User::query()
            ->where('role', 'caregiver')
            ->whereNotNull('email')
            ->where('email', '<>', '');
    }

    private function alreadySent(User $caregiver): bool
    {
        return MarketplaceNotificationDelivery::query()
            ->where('user_id', $caregiver->id)
            ->where('dedupe_key', $this->dedupeKey($caregiver))
            ->whereIn('status', ['queued', 'sent'])
            ->exists();
    }

    private function dedupeKey(User $caregiver): string
    {
        return self::DEDUPE_PREFIX.':user-'.$caregiver->id.':email';
    }

    private function startDelivery(User $caregiver, bool $resend): MarketplaceNotificationDelivery
    {
        $attributes = [
            'event_key' => self::EVENT_KEY,
            'channel' => 'email',
            'status' => 'queued',
            'payload' => $this->deliveryPayload(),
            'sent_at' => null,
        ];

        if ($resend) {
            return MarketplaceNotificationDelivery::query()->create([
                'user_id' => $caregiver->id,
                'dedupe_key' => null,
                ...$attributes,
            ]);
        }

        $delivery = MarketplaceNotificationDelivery::query()
            ->where('user_id', $caregiver->id)
            ->where('dedupe_key', $this->dedupeKey($caregiver))
            ->first();

        if ($delivery) {
            $delivery->forceFill($attributes)->save();

            return $delivery;
        }

        return MarketplaceNotificationDelivery::query()->create([
            'user_id' => $caregiver->id,
            'dedupe_key' => $this->dedupeKey($caregiver),
            ...$attributes,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function deliveryPayload(): array
    {
        return [
            'subject' => CaregiverLaunchEmail::SUBJECT,
            'get_care_url' => CaregiverLaunchEmail::GET_CARE_URL,
            'facebook_url' => CaregiverLaunchEmail::FACEBOOK_URL,
            'instagram_url' => CaregiverLaunchEmail::INSTAGRAM_URL,
        ];
    }
}
