<?php

namespace App\Services\FamilyAcquisition;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Support\FamilyLeadOutreach;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FamilyFollowUpCallService
{
    public function queue(Lead $lead, User $actor): Lead
    {
        abort_unless(in_array($actor->role, ['admin', 'sales', 'sdr'], true), 403);

        return DB::transaction(function () use ($lead, $actor): Lead {
            $lead = Lead::query()->lockForUpdate()->findOrFail($lead->id);

            if (! FamilyLeadOutreach::canQueueFollowUpCall($lead)) {
                throw ValidationException::withMessages([
                    'followUpCall' => 'Only active, contacted family leads with a phone number and permission to call can be queued for a follow-up call.',
                ]);
            }

            if (FamilyLeadOutreach::hasQueuedFollowUpCall($lead)) {
                return $lead;
            }

            $queuedAt = now();
            $data = $lead->data ?: [];
            data_set($data, 'family_outreach.follow_up_call_requested_at', $queuedAt->toISOString());

            $lead->forceFill([
                'data' => $data,
                'next_follow_up_at' => $queuedAt,
                'unanswered_attempt_count' => 0,
            ])->save();

            $lead->activities()->create([
                'actor_user_id' => $actor->id,
                'type' => LeadActivity::TYPE_NOTE,
                'summary' => 'Follow-up call queued',
                'body' => 'Added to the family calling queue. Stage remains '.$lead->stageLabel().'.',
                'occurred_at' => $queuedAt,
                'metadata' => [
                    'source' => 'family_follow_up_call',
                    'stage' => $lead->status,
                    'follow_up_at' => $queuedAt->toISOString(),
                ],
            ]);

            return $lead;
        });
    }
}
