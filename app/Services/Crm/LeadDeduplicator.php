<?php

namespace App\Services\Crm;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\VoiceAiCall;
use App\Support\LeadPhone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadDeduplicator
{
    public function save(Lead $lead, array $options = []): bool
    {
        $lead->phone_key = LeadPhone::key($lead->phone);
        $originalAttributes = $lead->getAttributes();
        $original = $lead->getRawOriginal();
        $existed = $lead->exists;

        for ($attempt = 0; ; $attempt++) {
            try {
                return DB::transaction(function () use ($lead, $options, $original, $originalAttributes, $existed) {
                    $lead->setRawAttributes($original, true);
                    $lead->setRawAttributes($originalAttributes);
                    $lead->exists = $existed;
                    $current = null;
                    if ($lead->exists) {
                        $current = Lead::query()->whereKey($lead->id)->lockForUpdate()->firstOrFail();
                    }
                    $match = $lead->phone_key && (! $lead->exists || $lead->isDirty(['phone_key', 'lead_type']))
                        ? Lead::query()->where('lead_type', $lead->lead_type)->where('phone_key', $lead->phone_key)
                            ->when($lead->exists, fn ($query) => $query->whereKeyNot($lead->id))
                            ->orderBy('id')->lockForUpdate()->first()
                        : null;

                    if ($match) {
                        if ($lead->exists) {
                            // Keep the edited record's ID, so the active CRM form remains valid.
                            $dirty = $lead->getDirty();
                            $lead->setRawAttributes($current->getAttributes(), true);
                            $lead->setRawAttributes(array_replace($current->getAttributes(), $dirty));
                            $this->merge($lead, $match);
                        } else {
                            $this->merge($match, $lead);
                            $lead->setRawAttributes($match->getAttributes(), true);
                            $lead->exists = true;
                            $lead->wasRecentlyCreated = false;
                            $lead->unsetRelations();

                            return true;
                        }
                    }

                    $this->rememberIdentity($lead, $lead->getOriginal('external_source'), $lead->getOriginal('external_id'));
                    $saved = $lead->saveWithoutDeduplication($options);
                    if ($saved) {
                        $this->rememberIdentity($lead, $lead->external_source, $lead->external_id);
                    }

                    return $saved;
                }, 3);
            } catch (UniqueConstraintViolationException $exception) {
                // Another request may have claimed this phone after our lookup. The unique
                // index is authoritative; retry in a new transaction and reuse its record.
                if ($attempt >= 2 || ! $lead->phone_key) {
                    throw $exception;
                }
                $lead->setRawAttributes($originalAttributes);
                $lead->exists = $existed;
                $lead->wasRecentlyCreated = false;
            }
        }
    }

    /** Both records must be locked by the caller when they already exist. */
    public function merge(Lead $survivor, Lead $duplicate): void
    {
        if ($survivor->family_account_id && $duplicate->family_account_id
            && (int) $survivor->family_account_id !== (int) $duplicate->family_account_id) {
            throw ValidationException::withMessages([
                'phone' => 'This phone belongs to leads linked to different family accounts. Review their account links before merging.',
            ]);
        }

        $snapshot = $duplicate->getAttributes();
        $survivorSnapshot = $survivor->getAttributes();
        $persisted = $duplicate->exists;
        $welcomeSnapshots = $persisted
            ? DB::table('lead_welcome_emails')->where('lead_id', $duplicate->id)->get()->all()
            : [];
        $this->rememberIdentity($survivor, $survivor->getOriginal('external_source'), $survivor->getOriginal('external_id'));

        // Fill missing contact details without resetting ownership, stage, follow-up or attribution.
        foreach (['name', 'email', 'phone', 'company', 'location', 'zip', 'contact_role', 'source',
            'source_detail', 'source_url', 'referrer_url', 'ip', 'user_agent', 'family_account_id',
            'assigned_admin_id', 'next_follow_up_at', 'closed_reason'] as $field) {
            if (blank($survivor->{$field}) && filled($duplicate->{$field})) {
                $survivor->{$field} = $duplicate->{$field};
            }
        }

        if (! $survivor->external_source && ! $survivor->external_id) {
            $survivor->external_source = $duplicate->external_source;
            $survivor->external_id = $duplicate->external_id;
        }

        foreach (['submitted_at', 'first_call_at', 'first_connected_at', 'converted_at', 'do_not_contact_at',
            'new_lead_alerted_at', 'first_call_escalated_at'] as $field) {
            if ($duplicate->{$field} && (! $survivor->{$field} || $duplicate->{$field}->lt($survivor->{$field}))) {
                $survivor->{$field} = $duplicate->{$field};
            }
        }
        if ($duplicate->last_contacted_at && (! $survivor->last_contacted_at || $duplicate->last_contacted_at->gt($survivor->last_contacted_at))) {
            $survivor->last_contacted_at = $duplicate->last_contacted_at;
        }

        if ($persisted) {
            if (in_array($survivor->status, [null, '', 'new'], true) && filled($duplicate->status)) {
                $survivor->status = $duplicate->status;
            }
            $survivor->call_attempt_count = (int) $survivor->call_attempt_count + (int) $duplicate->call_attempt_count;
            $survivor->unanswered_attempt_count = max((int) $survivor->unanswered_attempt_count, (int) $duplicate->unanswered_attempt_count);
        }

        $survivor->data = array_replace_recursive($duplicate->data ?? [], $survivor->data ?? []);
        if (! $persisted) {
            // Call alerts and the next callback must use the latest request. Prior call
            // details and the complete incoming payload are retained in the audit note.
            $data = $survivor->data;
            foreach (['intent', 'call_sid', 'transcript_excerpt', 'callback_time', 'callback_time_label',
                'requested_contact', 'reason', 'signup_link', 'consent_received'] as $field) {
                if (array_key_exists($field, $duplicate->data ?? [])) {
                    $data[$field] = $duplicate->data[$field];
                }
            }
            $survivor->data = $data;
        }
        if (data_get($duplicate->data, 'provider_outreach.do_not_call')) {
            $data = $survivor->data;
            data_set($data, 'provider_outreach.do_not_call', true);
            $survivor->data = $data;
        }
        $survivor->phone_key = LeadPhone::key($survivor->phone);

        if ($persisted) {
            // Release unique identifiers before moving them to the surviving record.
            DB::table('leads')->where('id', $duplicate->id)->update([
                'phone_key' => null, 'family_account_id' => null, 'external_source' => null, 'external_id' => null,
            ]);
            DB::table('lead_activities')->where('lead_id', $duplicate->id)->update(['lead_id' => $survivor->id]);
            DB::table('sms_messages')->where('lead_id', $duplicate->id)->update(['lead_id' => $survivor->id]);
            DB::table('lead_external_identities')->where('lead_id', $duplicate->id)->update(['lead_id' => $survivor->id]);

            $welcome = DB::table('lead_welcome_emails')->where('lead_id', $duplicate->id);
            if (! DB::table('lead_welcome_emails')->where('lead_id', $survivor->id)->exists()) {
                $welcome->update(['lead_id' => $survivor->id]);
            } else {
                // Keep all delivery reservations and signed unsubscribe links. Detached
                // pending messages cannot send another welcome email for the merged lead.
                $welcome->update(['lead_id' => null]);
            }

            VoiceAiCall::query()->where(function ($query) use ($duplicate) {
                $query->where('metadata->referral_lead_id', $duplicate->id)
                    ->orWhere('metadata->referral_lead_id', (string) $duplicate->id);
            })->each(function (VoiceAiCall $call) use ($survivor) {
                $metadata = $call->metadata;
                $metadata['referral_lead_id'] = $survivor->id;
                $call->update(['metadata' => $metadata]);
            });
        }

        if (! $survivor->saveWithoutDeduplication()) {
            throw new \RuntimeException('The surviving lead could not be saved. No records were merged.');
        }
        $this->rememberIdentity($survivor, $survivor->external_source, $survivor->external_id);
        $this->rememberIdentity($survivor, $duplicate->external_source, $duplicate->external_id);

        $survivor->activities()->create([
            'type' => LeadActivity::TYPE_NOTE,
            'summary' => $persisted ? 'Duplicate lead merged by phone' : 'Additional submission matched by phone',
            'body' => data_get($duplicate->data, 'notes') ?: data_get($duplicate->data, 'transcript_excerpt'),
            'occurred_at' => now(),
            'metadata' => [
                'source' => 'phone_deduplication',
                'merged_lead_id' => $persisted ? $duplicate->id : null,
                'original_lead' => $snapshot,
                'surviving_lead_before_merge' => $survivorSnapshot,
                'original_welcome_emails' => $welcomeSnapshots,
            ],
        ]);

        if ($persisted) {
            DB::table('leads')->where('id', $duplicate->id)->delete();
        }
    }

    private function rememberIdentity(Lead $lead, ?string $source, ?string $id): void
    {
        if ($lead->exists && filled($source) && filled($id)) {
            DB::table('lead_external_identities')->updateOrInsert(
                ['external_source' => $source, 'external_id' => $id], ['lead_id' => $lead->id],
            );
        }
    }
}
