<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Payments\Oct17PaymentRecoveryService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Throwable;

class RecoverOct17Payment extends Command
{
    protected $signature = 'homecare:recover-oct17-payment
        {--apply : Reassign the reviewed 697 minutes and capture USD 360.12 from the existing October 17 authorization}
        {--admin= : Administrator recording the recovery}
        {--expected-state= : Fingerprint from the reviewed preview}
        {--request-id= : One stable UUID, reused for all retries}
        {--reason= : Recovery reason and evidence for the visit assignment}
        {--provider-verified : Both Stripe payment histories and any separate/manual payment were checked}
        {--assignment-confirmed : October 9 was canceled and the recorded 697 minutes belong to October 17}';

    protected $description = 'Preview or recover the October 17 visit from booking 244 to booking 311 without creating an authorization';

    public function handle(Oct17PaymentRecoveryService $recovery): int
    {
        try {
            if (! $this->option('apply')) {
                $this->line(json_encode($recovery->preview(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                $this->info('READ-ONLY PREVIEW. No records changed and no Stripe requests made.');

                return self::SUCCESS;
            }
            $result = $recovery->apply(User::findOrFail((int) $this->option('admin')),
                (string) $this->option('expected-state'), (string) $this->option('request-id'),
                (string) $this->option('reason'), (bool) $this->option('provider-verified'),
                (bool) $this->option('assignment-confirmed'));
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $this->info('Recovery recorded. Review capture and caregiver transfer status above; bank payout is a separate Stripe step.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception instanceof ValidationException
                ? collect($exception->errors())->flatten()->implode(' ') : $exception->getMessage());
            $this->warn('If apply was started, preserve its request ID and original options. Do not create a new authorization or a second recovery after an uncertain result.');

            return self::FAILURE;
        }
    }
}
