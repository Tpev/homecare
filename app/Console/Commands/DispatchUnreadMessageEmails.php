<?php

namespace App\Console\Commands;

use App\Services\Notifications\UnreadMessageEmailService;
use Illuminate\Console\Command;

class DispatchUnreadMessageEmails extends Command
{
    protected $signature = 'homecare:dispatch-unread-message-emails';

    protected $description = 'Send grouped email reminders for messages that remain unread.';

    public function handle(UnreadMessageEmailService $emails): int
    {
        $this->info('Sent '.$emails->dispatchPending().' unread message email(s).');

        return self::SUCCESS;
    }
}
