<?php

namespace App\Services\Messaging;

use App\Models\CaregiverCrmActivity;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\Screening\CaregiverWorkEligibility;
use Illuminate\Support\Facades\DB;

class CrmSmsHistory
{
    public function linkByPhone(SmsMessage $message, bool $caregiverOnly = false): void
    {
        DB::transaction(function () use ($message, $caregiverOnly): void {
            $message = SmsMessage::query()->lockForUpdate()->findOrFail($message->id);
            if (! $message->lead_id && ! $message->caregiver_user_id) {
                $contact = $this->matchingContact($message);
                if ($contact instanceof User) {
                    $message->caregiver()->associate($contact)->save();
                } elseif ($contact instanceof Lead && ! $caregiverOnly) {
                    $message->lead()->associate($contact)->save();
                }
            }
            $this->recordActivity($message);
        });
    }

    private function matchingContact(SmsMessage $message): Lead|User|null
    {
        $incoming = $message->direction === SmsMessage::DIRECTION_INCOMING;
        $phone = TwilioSmsClient::validPhone($incoming ? $message->from_phone : $message->to_phone);
        $ourPhone = TwilioSmsClient::validPhone($incoming ? $message->to_phone : $message->from_phone);
        if (! $phone || ! $ourPhone || $ourPhone !== TwilioSmsClient::validPhone(config('services.twilio.sms_from'))) {
            return null;
        }

        $leads = Lead::query()->where('sms_phone', $phone)->pluck('id');
        $caregivers = app(CaregiverWorkEligibility::class)->caregivers()->where('sms_phone', $phone)->pluck('id');
        $count = $leads->count() + $caregivers->count();
        $at = $message->received_at ?? $message->created_at;
        if ($count === 1) {
            return $leads->isNotEmpty() ? Lead::find($leads->first())
                : User::where('created_at', '<=', $at)->find($caregivers->first());
        }

        // Shared numbers follow the last confirmed CRM conversation preceding this text.
        $outgoing = SmsMessage::query()->where('direction', SmsMessage::DIRECTION_OUTGOING)
            ->where('to_phone', $phone)->where('from_phone', $ourPhone)->whereNotNull('twilio_sid')
            ->where(fn ($query) => $query->where('created_at', '<', $at)
                ->orWhere(fn ($query) => $query->where('created_at', $at)->where('id', '<', $message->id)))
            ->where(function ($query) use ($leads, $caregivers, $count) {
                if ($count === 0) {
                    // Preserve the existing lead conversation fallback after a lead phone edit.
                    $query->whereNotNull('lead_id')->orWhereNotNull('caregiver_user_id');
                } else {
                    $query->whereIn('lead_id', $leads)->orWhereIn('caregiver_user_id', $caregivers);
                }
            })->latest('created_at')->latest('id')->first();

        if ($outgoing?->caregiver_user_id) {
            return $caregivers->contains($outgoing->caregiver_user_id) ? $outgoing->caregiver : null;
        }

        return $outgoing?->lead;
    }

    public function recordActivity(SmsMessage $message): void
    {
        $attributes = [
            'actor_user_id' => $message->sent_by_user_id,
            'type' => LeadActivity::TYPE_SMS,
            'body' => $message->body,
            'occurred_at' => $message->received_at ?: $message->created_at,
            'metadata' => [
                'direction' => $message->direction,
                'from_phone' => $message->from_phone,
                'to_phone' => $message->to_phone,
            ],
        ];

        if ($message->caregiver_user_id) {
            CaregiverCrmActivity::firstOrCreate(['sms_message_id' => $message->id], [
                'caregiver_user_id' => $message->caregiver_user_id,
                ...$attributes,
            ]);
        } elseif ($message->lead_id) {
            LeadActivity::firstOrCreate(['sms_message_id' => $message->id], [
                'lead_id' => $message->lead_id,
                'summary' => $message->direction === SmsMessage::DIRECTION_INCOMING ? 'SMS received' : 'Outgoing SMS',
                ...$attributes,
            ]);
        }
    }
}
