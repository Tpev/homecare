<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Payments\Oct3PaymentRecoveryService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Throwable;

class RecoverOct3Payment extends Command
{
    protected $signature = 'homecare:recover-oct3-payment
        {--apply : Authorize/capture the reviewed USD 372.52 and reconcile caregiver earnings}
        {--admin= : Administrator recording the recovery}
        {--expected-state= : Fingerprint from the reviewed preview}
        {--request-id= : One stable UUID, reused for all retries}
        {--reason= : Recovery reason and evidence for the approval basis}
        {--approval-basis= : auto-approval or family}
        {--approved-by= : Actual approving family user ID, only for family approval}
        {--provider-verified : Stripe and any separate/manual payment were checked for duplicates}';

    protected $description = 'Preview or recover only booking 239/payment 120, preserving the recorded 721 minutes';

    public function handle(Oct3PaymentRecoveryService $recovery): int
    {
        try {
            if (! $this->option('apply')) {
                $this->line(json_encode($recovery->preview(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                $this->info('READ-ONLY PREVIEW. No records, Stripe requests, or messages changed.');

                return self::SUCCESS;
            }
            $result = $recovery->apply(User::findOrFail((int) $this->option('admin')),
                (string) $this->option('expected-state'), (string) $this->option('request-id'),
                (string) $this->option('reason'), (string) $this->option('approval-basis'),
                $this->option('approved-by') ? (int) $this->option('approved-by') : null,
                (bool) $this->option('provider-verified'));
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $this->info('Recovery recorded. Review capture and caregiver transfer status above; bank payout is a separate Stripe step.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception instanceof ValidationException
                ? collect($exception->errors())->flatten()->implode(' ') : $exception->getMessage());
            $this->warn('If apply was started, preserve its request ID and original options. Do not create a second recovery after an uncertain result.');

            return self::FAILURE;
        }
    }
}
