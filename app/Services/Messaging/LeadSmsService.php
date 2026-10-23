<?php

namespace App\Services\Messaging;

use App\Jobs\SendIncomingSmsAlert;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\SmsMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class LeadSmsService
{
    public function send(Lead $lead, User $actor, string $body, string $requestKey): SmsMessage
    {
        abort_unless($actor->fresh()?->canAccessCrm(), 403);
        $lead->refresh();
        $body = trim($body);
        $to = TwilioSmsClient::normalizePhone($lead->phone);
        $from = TwilioSmsClient::normalizePhone((string) config('services.twilio.sms_from'));

        Validator::make(['smsBody' => $body, 'phone' => $to, 'requestKey' => $requestKey], [
            'smsBody' => ['required', 'string', 'max:1600'],
            'phone' => ['required', 'regex:/^\+[1-9][0-9]{7,14}$/'],
            'requestKey' => ['required', 'uuid'],
        ], ['phone.*' => 'Save a valid phone number on this lead before sending a text.'])->validate();

        if ($lead->do_not_contact_at) {
            throw ValidationException::withMessages(['send' => 'This lead is marked do not contact.']);
        }
        if (! preg_match('/^\+[1-9][0-9]{7,14}$/', $from)) {
            throw ValidationException::withMessages(['send' => 'The SMS sending number is not configured.']);
        }

        $message = DB::transaction(function () use ($lead, $actor, $body, $to, $from, $requestKey): SmsMessage {
            $message = SmsMessage::query()->firstOrCreate(['request_key' => $requestKey], [
                'lead_id' => $lead->id,
                'sent_by_user_id' => $actor->id,
                'direction' => SmsMessage::DIRECTION_OUTGOING,
                'status' => SmsMessage::STATUS_SENDING,
                'from_phone' => $from,
                'to_phone' => $to,
                'body' => $body,
            ]);
            abort_unless($message->lead_id === $lead->id && $message->sent_by_user_id === $actor->id, 403);
            $this->recordActivity($message);

            return $message;
        });

        // A retried Livewire request must never send the same message twice.
        if (! $message->wasRecentlyCreated) {
            return $message;
        }

        $callback = trim((string) config('services.twilio.status_callback_url')) ?: route('webhooks.twilio.sms.status');
        $callback .= (str_contains($callback, '?') ? '&' : '?').'crm_message='.$message->id;

        try {
            $payload = app(TwilioSmsClient::class)->sendMessage($to, $body, $from, $callback);
        } catch (Throwable $exception) {
            DB::transaction(function () use ($message, $exception): void {
                $locked = SmsMessage::query()->lockForUpdate()->findOrFail($message->id);
                // A callback may already have confirmed acceptance while the API response timed out.
                if (! $locked->twilio_sid) {
                    $locked->update([
                        'status' => SmsMessage::STATUS_FAILED,
                        'error_message' => $exception->getMessage(),
                    ]);
                }
            });

            return $message->refresh();
        }

        DB::transaction(function () use ($message, $payload, $lead): void {
            $locked = SmsMessage::query()->lockForUpdate()->findOrFail($message->id);
            $raw = $locked->raw_payload ?? [];
            $locked->fill([
                'twilio_sid' => $payload['sid'] ?? $locked->twilio_sid,
                'twilio_account_sid' => $payload['account_sid'] ?? config('services.twilio.account_sid'),
                'sent_at' => $locked->sent_at ?: now(),
                'raw_payload' => array_merge($payload, $raw),
            ]);
            if (! isset($raw['status_callback'])) {
                $locked->status = $payload['status'] ?? SmsMessage::STATUS_QUEUED;
                $locked->twilio_status = $locked->status;
                $locked->error_code = $payload['error_code'] ?? null;
                $locked->error_message = $payload['error_message'] ?? null;
            }
            $locked->save();
            $lead->update(['last_contacted_at' => now()]);
        });

        return $message->refresh();
    }

    public function receive(SmsMessage $message): void
    {
        DB::transaction(function () use ($message): void {
            $message = SmsMessage::query()->lockForUpdate()->findOrFail($message->id);
            if (! $message->lead_id) {
                $message->lead_id = $this->matchingLead($message)?->id;
                $message->save();
            }
            $this->recordActivity($message);
        });

        if (! $message->fresh()->inbound_notified_at) {
            SendIncomingSmsAlert::dispatch($message->id)->afterCommit();
        }
    }

    private function matchingLead(SmsMessage $message): ?Lead
    {
        $phone = TwilioSmsClient::normalizePhone($message->from_phone);
        $ourPhone = TwilioSmsClient::normalizePhone($message->to_phone);
        if ($phone === '' || $ourPhone !== TwilioSmsClient::normalizePhone((string) config('services.twilio.sms_from'))) {
            return null;
        }

        $candidates = Lead::query()->where('sms_phone', $phone)->pluck('id');
        if ($candidates->count() === 1) {
            return Lead::find($candidates->first());
        }

        // Shared phone numbers are associated with the most recently contacted CRM lead.
        // Without an existing conversation, leave an ambiguous message in the admin inbox.
        $outgoing = SmsMessage::query()->where('direction', SmsMessage::DIRECTION_OUTGOING)
            ->where('to_phone', $phone)->where('from_phone', $ourPhone)
            ->whereNotNull('lead_id')->whereNotNull('twilio_sid')
            ->when($candidates->isNotEmpty(), fn ($query) => $query->whereIn('lead_id', $candidates))
            ->latest('id')->first();

        return $outgoing?->lead;
    }

    private function recordActivity(SmsMessage $message): void
    {
        if (! $message->lead_id) {
            return;
        }

        LeadActivity::query()->firstOrCreate(['sms_message_id' => $message->id], [
            'lead_id' => $message->lead_id,
            'actor_user_id' => $message->sent_by_user_id,
            'type' => LeadActivity::TYPE_SMS,
            'summary' => $message->direction === SmsMessage::DIRECTION_INCOMING ? 'SMS received' : 'Outgoing SMS',
            'body' => $message->body,
            'occurred_at' => $message->received_at ?: $message->created_at,
            'metadata' => [
                'direction' => $message->direction,
                'from_phone' => $message->from_phone,
                'to_phone' => $message->to_phone,
            ],
        ]);
    }

    public function updateDelivery(SmsMessage $message, array $payload): void
    {
        DB::transaction(function () use ($message, $payload): void {
            $message = SmsMessage::query()->lockForUpdate()->findOrFail($message->id);
            $status = (string) ($payload['MessageStatus'] ?? $payload['SmsStatus'] ?? '');
            $rank = ['accepted' => 0, 'scheduled' => 0, 'queued' => 1, 'sending' => 2, 'sent' => 3,
                'delivered' => 4, 'undelivered' => 4, 'failed' => 4, 'canceled' => 4, 'read' => 5];
            // Callbacks can arrive out of order. Preserve the most advanced delivery state.
            if (! isset($rank[$status]) || ($message->twilio_status !== null
                && $rank[$status] < ($rank[$message->status] ?? -1))
                || (isset($message->raw_payload['status_callback']) && $rank[$status] === ($rank[$message->status] ?? -1))) {
                return;
            }

            $message->update([
                'twilio_sid' => $message->twilio_sid ?: $payload['MessageSid'],
                'status' => $status,
                'twilio_status' => $status,
                'sent_at' => $message->sent_at ?: now(),
                'error_code' => filled($payload['ErrorCode'] ?? null) ? (string) $payload['ErrorCode'] : null,
                'error_message' => filled($payload['ErrorMessage'] ?? null) ? (string) $payload['ErrorMessage'] : null,
                'raw_payload' => array_merge($message->raw_payload ?? [], ['status_callback' => $payload]),
            ]);
        });
    }
}
