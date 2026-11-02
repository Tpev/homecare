<?php

namespace App\Listeners;

use App\Support\StaffAccess;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Mime\Address;

class SuppressOffboardedStaffMail
{
    public function handle(MessageSending $event): ?bool
    {
        // Filter at transport time, also covering mail queued before offboarding,
        // raw configured recipients, CC and BCC. Do not cancel other recipients' mail.
        $message = $event->message;
        $blocked = DB::table('staff_access_blocks')->where('kind', 'email')->pluck('value')->all();
        $keep = fn (Address $address): bool => ! in_array(StaffAccess::email($address->getAddress()), $blocked, true);
        $message->to(...array_filter($message->getTo(), $keep));
        $message->cc(...array_filter($message->getCc(), $keep));
        $message->bcc(...array_filter($message->getBcc(), $keep));
        $message->replyTo(...array_filter($message->getReplyTo(), $keep));

        return count($message->getTo()) + count($message->getCc()) + count($message->getBcc()) === 0 ? false : null;
    }
}
