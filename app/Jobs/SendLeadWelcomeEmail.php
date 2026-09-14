<?php

namespace App\Jobs;

use App\Models\LeadWelcomeEmail;
use App\Services\FamilyAcquisition\LeadWelcomeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendLeadWelcomeEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public int $welcomeEmailId) {}

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(LeadWelcomeService $service): void
    {
        $service->send($this->welcomeEmailId);
    }

    public function failed(?Throwable $exception): void
    {
        $message = LeadWelcomeEmail::find($this->welcomeEmailId);
        $reason = 'Could not prepare the email after three attempts. Check mail configuration, then retry.';
        if ($message && LeadWelcomeEmail::whereKey($message->id)->whereIn('status', ['queued', 'retrying'])
            ->whereNull('last_attempt_at')->whereNull('sent_at')->whereNull('previewed_at')
            ->update(['status' => 'failed', 'reason' => $reason])) {
            $message->refresh();
            app(LeadWelcomeService::class)->activity($message, 'Welcome email failed', $message->reason);
        }
    }
}
