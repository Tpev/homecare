<?php

namespace App\Services\Partners;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\PartnerLead;
use App\Models\PartnerLeadCredit;
use App\Models\PartnerProfile;
use App\Models\User;
use App\Support\LeadPhone;
use App\Support\PartnerOptions;
use App\Support\StaffAccess;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PartnerLeadService
{
    public function assign(
        PartnerProfile $partner,
        Lead $lead,
        string $category,
        User $actor,
        bool $sharingConfirmed,
        bool $geographyConfirmed,
    ): PartnerLead {
        $actor = $actor->fresh();
        abort_unless($actor?->isAdministrator() && StaffAccess::allowsUser($actor), 403);
        if (! $sharingConfirmed || ! $geographyConfirmed) {
            throw ValidationException::withMessages([
                'assignment' => 'Confirm permission to share this request and the partner\'s coverage before sending it.',
            ]);
        }

        try {
            return DB::transaction(function () use ($partner, $lead, $category, $actor) {
                // Billing reconciliation and credit consumption use the same partner lock.
                $partner = PartnerProfile::query()->lockForUpdate()->findOrFail($partner->id);
                $lead = Lead::query()->lockForUpdate()->findOrFail($lead->id);
                $partnerUser = $partner->user;
                if ($partner->status !== PartnerProfile::STATUS_APPROVED
                    || $partnerUser?->role !== 'partner' || ! StaffAccess::allowsUser($partnerUser)) {
                    throw ValidationException::withMessages(['assignment' => 'Only an approved, active partner can receive leads.']);
                }
                if (! array_key_exists($category, PartnerOptions::categories())
                    || ! in_array($category, $partner->categories ?? [], true)
                    || ! in_array($category, $this->categoriesFor($lead), true)) {
                    throw ValidationException::withMessages(['assignment' => 'Choose a service requested by the lead and offered by this partner.']);
                }
                if ($reason = $this->unavailabilityReason($lead)) {
                    throw ValidationException::withMessages(['assignment' => $reason]);
                }

                $phone = LeadPhone::key($lead->phone);
                $email = $this->email($lead->email);
                $identities = $this->identities($lead);
                $claimed = DB::table('partner_lead_claims')->where('category', $category)
                    ->where(function ($query) use ($identities) {
                        foreach ($identities as $identity) {
                            $query->orWhere(fn ($query) => $query->where('identity_type', $identity['identity_type'])
                                ->where('identity_hash', $identity['identity_hash']));
                        }
                    })->exists();
                if ($claimed) {
                    throw ValidationException::withMessages(['assignment' => 'This contact has already been sent to a partner for this service.']);
                }

                $credit = PartnerLeadCredit::query()->available()->where('partner_profile_id', $partner->id)
                    ->whereNotNull('paid_at')->orderBy('paid_at')->orderBy('id')->lockForUpdate()->first();
                if (! $credit) {
                    throw ValidationException::withMessages(['assignment' => 'This partner has no available paid lead credits.']);
                }
                $now = now();
                $delivery = PartnerLead::create([
                    'partner_profile_id' => $partner->id,
                    'lead_id' => $lead->id,
                    'source_lead_id' => $lead->id,
                    'partner_lead_credit_id' => $credit->id,
                    'category' => $category,
                    'contact_name' => mb_substr(trim((string) $lead->name) ?: 'Contact', 0, 255),
                    'contact_email' => $email,
                    'contact_phone' => $phone,
                    'location' => filled($lead->location) ? mb_substr(trim($lead->location), 0, 255) : null,
                    'zip' => filled($lead->zip) ? mb_substr(trim($lead->zip), 0, 20) : null,
                    'service_context' => 'Requested information about '.PartnerOptions::categories()[$category].'.',
                    'assigned_by_user_id' => $actor->id,
                    'assigned_at' => $now,
                    'sharing_confirmed_at' => $now,
                    'geography_confirmed_at' => $now,
                ]);
                foreach ($identities as $identity) {
                    DB::table('partner_lead_claims')->insert($identity + [
                        'partner_lead_id' => $delivery->id, 'category' => $category, 'created_at' => $now,
                    ]);
                }
                $credit->increment('used_quantity');

                return $delivery;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            // A different admin may have claimed a matching identity in the meantime.
            throw ValidationException::withMessages(['assignment' => 'This contact has already been sent to a partner for this service.']);
        }
    }

    /** Lead-level blockers only; partner eligibility and credits are checked when sending. */
    public function unavailabilityReason(Lead $lead): ?string
    {
        if ($lead->lead_type !== Lead::TYPE_FAMILY || $lead->do_not_contact_at
            || data_get($lead->data, 'provider_outreach.do_not_call')) {
            return 'This lead cannot be shared. Check its type and contact preferences.';
        }
        if (! LeadPhone::key($lead->phone) && ! $this->email($lead->email)) {
            return 'Add a valid phone number or email before sending this lead.';
        }

        return null;
    }

    /**
     * A sent category stays claimed even when its delivery snapshot has been removed.
     *
     * @return array<string, array{partner_profile_id: ?int, partner_name: ?string, assigned_at: ?Carbon, delivery_id: ?int}>
     */
    public function sentCategoriesFor(Lead $lead): array
    {
        return $this->sentCategoriesForLeads([$lead])[$lead->id] ?? [];
    }

    /**
     * Read-only availability for an inventory page. Assignment rechecks under locks.
     *
     * @param  iterable<Lead>  $leads
     * @return array<int, array<string, array{partner_profile_id: ?int, partner_name: ?string, assigned_at: ?Carbon, delivery_id: ?int}>>
     */
    public function sentCategoriesForLeads(iterable $leads): array
    {
        $leads = collect($leads)->filter(fn (Lead $lead): bool => $lead->id !== null)->keyBy('id');
        $result = $leads->map(fn (): array => [])->all();
        if ($leads->isEmpty()) {
            return $result;
        }

        // Do not replace the caller's activities relation with partially selected notes.
        $notes = collect();
        foreach ($leads->keys()->chunk(500) as $leadIds) {
            $notes = $notes->concat(LeadActivity::query()->whereIn('lead_id', $leadIds)
                ->where('type', 'note')->get(['lead_id', 'metadata']));
        }
        $notesByLead = $notes->groupBy('lead_id');
        $identityOwners = [];
        $hashesByType = [];
        foreach ($leads as $lead) {
            foreach ($this->identities($lead, $notesByLead->get($lead->id, [])) as $identity) {
                $type = $identity['identity_type'];
                $hash = $identity['identity_hash'];
                $identityOwners[$type.':'.$hash][$lead->id] = true;
                $hashesByType[$type][$hash] = true;
            }
        }

        $firstClaim = [];
        foreach ($hashesByType as $type => $hashes) {
            foreach (array_chunk(array_keys($hashes), 500) as $chunk) {
                $claims = DB::table('partner_lead_claims as claims')
                    ->leftJoin('partner_leads as delivery', 'delivery.id', '=', 'claims.partner_lead_id')
                    ->leftJoin('partner_profiles as partner', 'partner.id', '=', 'delivery.partner_profile_id')
                    ->where('claims.identity_type', $type)->whereIn('claims.identity_hash', $chunk)
                    ->orderBy('claims.id')->get([
                        'claims.id', 'claims.identity_type', 'claims.identity_hash', 'claims.category',
                        'claims.created_at', 'delivery.id as delivery_id', 'delivery.partner_profile_id',
                        'delivery.assigned_at', 'partner.business_name as partner_name',
                    ]);
                foreach ($claims as $claim) {
                    foreach ($identityOwners[$claim->identity_type.':'.$claim->identity_hash] as $leadId => $_) {
                        // Multiple identities can point to the same delivery; merged records
                        // can have older claims too. Show the earliest claim consistently.
                        if (isset($firstClaim[$leadId][$claim->category])
                            && $firstClaim[$leadId][$claim->category] <= $claim->id) {
                            continue;
                        }
                        $firstClaim[$leadId][$claim->category] = $claim->id;
                        $assignedAt = $claim->assigned_at ?? $claim->created_at;
                        $result[$leadId][$claim->category] = [
                            'partner_profile_id' => $claim->partner_profile_id !== null ? (int) $claim->partner_profile_id : null,
                            'partner_name' => $claim->partner_name,
                            'assigned_at' => $assignedAt ? Carbon::parse($assignedAt) : null,
                            'delivery_id' => $claim->delivery_id !== null ? (int) $claim->delivery_id : null,
                        ];
                    }
                }
            }
        }

        return $result;
    }

    /** Map known answers without treating commas inside option labels as choices. */
    public function categoriesFor(Lead $lead): array
    {
        $aliases = [];
        foreach (PartnerOptions::categories() as $code => $label) {
            $aliases[$this->normalizedLabel($code)] = $code;
            $aliases[$this->normalizedLabel($label)] = $code;
        }
        $aliases[$this->normalizedLabel('Home safety improvements (ramps, grab bars, stair lifts)')] = 'home_safety';
        $answers = [];
        foreach ($lead->additionalServices() as $value) {
            $buffer = '';
            $depth = 0;
            foreach (str_split($value) as $character) {
                if ($character === '(') {
                    $depth++;
                } elseif ($character === ')') {
                    $depth = max(0, $depth - 1);
                }
                if ($character === ',' && $depth === 0) {
                    $answers[] = $buffer;
                    $buffer = '';
                } else {
                    $buffer .= $character;
                }
            }
            $answers[] = $buffer;
        }

        return array_values(array_unique(array_filter(array_map(
            fn (string $answer): ?string => $aliases[$this->normalizedLabel($answer)] ?? null,
            $answers,
        ))));
    }

    private function normalizedLabel(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower($value)));
    }

    private function email(?string $value): ?string
    {
        $email = mb_strtolower(trim((string) $value));

        return filter_var($email, FILTER_VALIDATE_EMAIL) && mb_strlen($email) <= 255 ? $email : null;
    }

    /** Preserve known identities from CRM merges as well as the current contact. */
    private function identities(Lead $lead, ?iterable $notes = null): array
    {
        $identities = [];
        $add = function (string $type, mixed $value) use (&$identities): void {
            if ($value !== null && $value !== '') {
                $identities[$type.':'.$value] = ['identity_type' => $type, 'identity_hash' => hash('sha256', (string) $value)];
            }
        };
        $fromSnapshot = function (array $snapshot) use ($add): void {
            $add('lead', isset($snapshot['id']) ? (string) $snapshot['id'] : null);
            $add('phone', LeadPhone::key($snapshot['phone'] ?? null));
            $add('email', $this->email($snapshot['email'] ?? null));
        };
        $fromSnapshot($lead->getAttributes());
        foreach ($notes ?? $lead->activities()->where('type', 'note')->get(['metadata']) as $activity) {
            if (data_get($activity->metadata, 'source') !== 'phone_deduplication') {
                continue;
            }
            foreach (['original_lead', 'surviving_lead_before_merge'] as $key) {
                $snapshot = data_get($activity->metadata, $key);
                if (is_array($snapshot)) {
                    $fromSnapshot($snapshot);
                }
            }
            $add('lead', data_get($activity->metadata, 'merged_lead_id'));
        }

        return array_values($identities);
    }
}
