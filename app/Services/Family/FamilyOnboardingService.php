<?php

namespace App\Services\Family;

use App\Models\CareRequest;
use App\Models\FamilyAccount;
use App\Models\FamilyAccountMember;
use App\Models\FamilyHouseholdProfile;
use App\Models\FamilyOnboarding;
use App\Models\FamilyRecipientProfile;
use App\Models\FamilyWelcomeVisit;
use App\Models\User;
use App\Services\CareRecipientProfiles\CareRecipientProfileService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\FamilyAccounts\FamilyAccountProvisioner;
use App\Services\FamilyAcquisition\LeadWelcomeService;
use App\Support\FamilyQuickRequestDraft;
use App\Support\FunnelTracker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FamilyOnboardingService
{
    public const RANGES = [
        'morning' => ['label' => 'Morning', 'hours' => '9 am – 12 pm', 'start' => '09:00', 'end' => '12:00'],
        'noon' => ['label' => 'Noon', 'hours' => '12 pm – 2 pm', 'start' => '12:00', 'end' => '14:00'],
        'afternoon' => ['label' => 'Afternoon', 'hours' => '2 pm – 5 pm', 'start' => '14:00', 'end' => '17:00'],
    ];

    public const RELATIONSHIPS = ['Parent', 'Spouse or partner', 'Grandparent', 'Sibling', 'Child', 'Another family member'];

    public const FIELDS = [
        1 => ['care_for', 'recipient_name', 'relationship'],
        2 => ['address_line1', 'address_line2', 'city', 'state', 'zip'],
        3 => ['care_notes', 'care_preferences'],
        4 => ['welcome_visit', 'visit_date', 'visit_time', 'phone'],
    ];

    public function __construct(private readonly FamilyAccountContext $accounts) {}

    /** Only called by the normal registration entry point, within its transaction. */
    public function enrollRegistration(User $user): ?FamilyOnboarding
    {
        if (! config('family_onboarding.enrollment_enabled') || $user->isAdministrator()) {
            return null;
        }

        $account = app(FamilyAccountProvisioner::class)->provisionOwner($user, 'family_registration');
        $quick = FamilyQuickRequestDraft::get() ?? [];
        $lead = app(LeadWelcomeService::class)->sessionMessage();
        if ($lead && strtolower($lead->email) !== strtolower($user->email)) {
            $lead = null;
        }
        $context = $quick;
        $context['zip'] = $context['zip'] ?? (string) $lead?->lead?->zip;
        $context['additional_info'] = $context['additional_info'] ?? (string) data_get($lead?->lead?->data, 'care_preferences.help_needed', '');
        $draft = array_fill_keys(array_merge(...array_values(self::FIELDS)), '');
        $draft = array_replace($draft, Arr::only($context, self::FIELDS[2]));
        $draft['care_for'] = isset($quick['care_for']) ? ($quick['care_for'] === 'self' ? 'me' : 'family') : '';
        $draft['recipient_name'] = (string) ($quick['recipient_full_name'] ?? '');
        $draft['phone'] = (string) $user->phone;
        $onboarding = FamilyOnboarding::query()->firstOrCreate(['family_account_id' => $account->id], [
            'initiated_by_user_id' => $user->id,
            'source' => $quick ? 'homepage_request' : ($lead ? 'lead_welcome' : 'registration'),
            'draft' => $draft, 'request_context' => $context,
        ]);
        if ($onboarding->wasRecentlyCreated) {
            FunnelTracker::track('family_onboarding_enrolled', $user, $onboarding);
        }

        return $onboarding;
    }

    public function forOwner(User $user): ?FamilyOnboarding
    {
        if ($user->role !== 'family' || $user->isAdministrator()) {
            return null;
        }
        $member = $this->accounts->membershipFor($user, false);
        if (! $member?->isOwner() || (int) $member->familyAccount->owner_user_id !== (int) $user->id) {
            return null;
        }

        return FamilyOnboarding::query()->where('family_account_id', $member->family_account_id)
            ->where('initiated_by_user_id', $user->id)->first();
    }

    public function pending(User $user): bool
    {
        return config('family_onboarding.enforcement_enabled') && $this->forOwner($user)?->status === 'in_progress';
    }

    private function locked(User $user): FamilyOnboarding
    {
        $enrollment = $this->forOwner($user);
        abort_unless($enrollment, 403);
        $account = FamilyAccount::query()->lockForUpdate()->findOrFail($enrollment->family_account_id);
        $member = FamilyAccountMember::query()->where('family_account_id', $account->id)->where('user_id', $user->id)
            ->where('status', FamilyAccountMember::STATUS_ACTIVE)->lockForUpdate()->first();
        abort_unless($account->status === FamilyAccount::STATUS_ACTIVE && (int) $account->owner_user_id === (int) $user->id && $member?->isOwner(), 403);

        return FamilyOnboarding::query()->lockForUpdate()->findOrFail($enrollment->id);
    }

    public function saveStep(User $user, array $input, int $step, int $revision, bool $back = false): FamilyOnboarding
    {
        return DB::transaction(function () use ($user, $input, $step, $revision, $back) {
            $record = $this->locked($user);
            abort_unless($record->status === 'in_progress', 409);
            $this->ensureRevision($record, $revision);
            abort_unless($step === $record->current_step && $step >= 1 && $step <= 5, 409);
            $draft = $record->draft;
            foreach (self::FIELDS[$step] ?? [] as $field) {
                $value = $input[$field] ?? '';
                if (! is_string($value) || mb_strlen($value) > (in_array($field, self::FIELDS[3], true) ? 2000 : 255)) {
                    throw ValidationException::withMessages(['form.'.$field => 'Please enter a shorter text value.']);
                }
                $draft[$field] = trim($value);
            }
            $draft = $this->normalize($draft);
            if (! $back && $step < 5) {
                $this->validateDraft($draft, [$step]);
            }
            $record->forceFill([
                'draft' => $draft, 'revision' => $record->revision + 1,
                'current_step' => $back ? max(1, $step - 1) : min(5, $step + 1),
                'started_at' => $record->started_at ?? now(),
            ])->save();
            FunnelTracker::track('family_onboarding_step_saved', $user, $record, ['step' => $step, 'back' => $back]);

            return $record;
        });
    }

    public function complete(User $user, int $revision): FamilyOnboarding
    {
        return DB::transaction(function () use ($user, $revision) {
            $record = $this->locked($user);
            if ($record->status === 'completed') {
                return $record; // Idempotent, including a final click from an older tab.
            }
            abort_unless($record->status === 'in_progress', 409);
            $this->ensureRevision($record, $revision);
            $draft = $this->normalize($record->draft);
            $this->validateDraft($draft, [1, 2, 3, 4]);
            $self = $draft['care_for'] === 'me';
            $draft['recipient_name'] = $self ? $user->name : $draft['recipient_name'];
            $draft['relationship'] = $self ? 'Self' : $draft['relationship'];
            $ownership = ['family_account_id' => $record->family_account_id, 'family_user_id' => $user->id];
            FamilyHouseholdProfile::query()->updateOrCreate(['family_account_id' => $record->family_account_id], [
                ...$ownership, ...Arr::only($draft, self::FIELDS[2]),
            ]);
            $profile = app(CareRecipientProfileService::class)->saveDraft($user, null, [
                'recipient_is_requester' => $self, 'full_name' => $draft['recipient_name'],
                'preferred_name' => mb_substr($draft['recipient_name'], 0, 80), 'relationship_to_family' => $draft['relationship'],
                'about_them' => $draft['care_notes'], 'good_visit_notes' => $draft['care_preferences'],
            ]);
            // The request wizard still reads this legacy profile; sharing readiness is not changed.
            FamilyRecipientProfile::query()->updateOrCreate(['family_account_id' => $record->family_account_id], [
                ...$ownership, 'recipient_is_requester' => $self, 'full_name' => $draft['recipient_name'],
                'relationship_to_family' => $draft['relationship'], 'care_notes' => null,
            ]);
            $snapshot = [...$draft, 'name' => $user->name, 'email' => $user->email,
                'phone' => $draft['welcome_visit'] === 'yes' ? $draft['phone'] : $user->phone,
                'submitted_at' => now()->utc()->toIso8601String(), 'timezone' => $this->timezone()];
            if ($draft['welcome_visit'] === 'yes') {
                $range = self::RANGES[$draft['visit_time']];
                $snapshot['visit_range_label'] = $range['label'].' ('.$range['hours'].')';
                $user->forceFill(['phone' => $draft['phone']])->save();
                FamilyWelcomeVisit::query()->create([
                    'family_onboarding_id' => $record->id, 'preferred_date' => $draft['visit_date'],
                    'preferred_range' => $draft['visit_time'], 'timezone' => $this->timezone(),
                    'window_start_at' => $this->windowStart($draft)->utc(),
                    'window_end_at' => CarbonImmutable::parse($draft['visit_date'].' '.$range['end'], $this->timezone())->utc(),
                    'phone' => $draft['phone'],
                ]);
            }
            $record->forceFill([
                'status' => 'completed', 'completed_at' => now(), 'completed_by_user_id' => $user->id,
                'care_recipient_profile_id' => $profile->id, 'submitted_snapshot' => $snapshot,
                'draft' => [], 'current_step' => 5, 'revision' => $record->revision + 1,
            ])->save();
            app(FamilyOnboardingDeliveryService::class)->createIntents($record);
            FunnelTracker::track('family_onboarding_completed', $user, $record);

            return $record;
        });
    }

    private function ensureRevision(FamilyOnboarding $record, int $revision): void
    {
        if ($record->revision !== $revision) {
            throw ValidationException::withMessages(['conflict' => 'Your answers changed in another tab. Reload the saved answers before continuing.']);
        }
    }

    private function normalize(array $draft): array
    {
        if (($draft['care_for'] ?? '') === 'me') {
            $draft['recipient_name'] = $draft['relationship'] = '';
        }
        if (($draft['welcome_visit'] ?? '') !== 'yes') {
            $draft['visit_date'] = $draft['visit_time'] = '';
        }
        $draft['state'] = strtoupper($draft['state'] ?? '');
        $phone = preg_replace('/[\s().-]+/', '', $draft['phone'] ?? '');
        if (preg_match('/^\d{10}$/', $phone)) {
            $phone = '+1'.$phone;
        } elseif (preg_match('/^1\d{10}$/', $phone)) {
            $phone = '+'.$phone;
        }
        $draft['phone'] = $phone;

        return $draft;
    }

    public function validateDraft(array $draft, array $steps): void
    {
        $all = [
            'care_for' => ['required', Rule::in(['me', 'family'])],
            'recipient_name' => [Rule::requiredIf(($draft['care_for'] ?? '') === 'family'), 'nullable', 'string', 'max:100'],
            'relationship' => [Rule::requiredIf(($draft['care_for'] ?? '') === 'family'), 'nullable', Rule::in(self::RELATIONSHIPS)],
            'address_line1' => ['required', 'string', 'max:200'], 'address_line2' => ['nullable', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'], 'state' => ['required', 'regex:/^[A-Z]{2}$/'],
            'zip' => ['required', 'regex:/^\d{5}(-\d{4})?$/'],
            'care_notes' => ['nullable', 'string', 'max:2000'], 'care_preferences' => ['nullable', 'string', 'max:2000'],
            'welcome_visit' => ['required', Rule::in(['yes', 'no'])],
            'visit_date' => ['nullable', Rule::requiredIf(($draft['welcome_visit'] ?? '') === 'yes'), 'date_format:Y-m-d'],
            'visit_time' => ['nullable', Rule::requiredIf(($draft['welcome_visit'] ?? '') === 'yes'), Rule::in(array_keys(self::RANGES))],
            'phone' => ($draft['welcome_visit'] ?? '') === 'yes' ? ['required', 'regex:/^\+[1-9]\d{7,14}$/'] : ['nullable', 'string', 'max:30'],
        ];
        $rules = [];
        foreach ($steps as $step) {
            foreach (self::FIELDS[$step] as $field) {
                $rules['form.'.$field] = $all[$field];
            }
        }
        $labels = ['care_for' => 'care recipient', 'recipient_name' => 'full name', 'relationship' => 'relationship',
            'address_line1' => 'street address', 'address_line2' => 'apartment', 'city' => 'city', 'state' => 'state', 'zip' => 'ZIP code',
            'care_notes' => 'care notes', 'care_preferences' => 'care preferences', 'welcome_visit' => 'welcome visit preference',
            'visit_date' => 'preferred date', 'visit_time' => 'preferred time', 'phone' => 'phone number'];
        Validator::make(['form' => $draft], $rules, [
            'form.care_for.required' => 'Choose who is receiving care.',
            'form.welcome_visit.required' => 'Choose whether you would like a welcome visit.',
            'form.phone.regex' => 'Enter a valid phone number, including country code if outside the US.',
            'form.state.regex' => 'Enter the two-letter state abbreviation.',
        ], collect($labels)->mapWithKeys(fn ($label, $field) => ['form.'.$field => $label])->all())->validate();
        if (in_array(4, $steps, true) && ($draft['welcome_visit'] ?? '') === 'yes'
            && $this->windowStart($draft)->timestamp < now()->timestamp + 86400) {
            throw ValidationException::withMessages(['form.visit_date' => 'Choose a range that starts at least 24 hours from now ('.$this->timezone().').']);
        }
    }

    public function timezone(): string
    {
        return (string) config('family_onboarding.timezone', 'America/New_York');
    }

    private function windowStart(array $draft): CarbonImmutable
    {
        return CarbonImmutable::parse($draft['visit_date'].' '.self::RANGES[$draft['visit_time']]['start'], $this->timezone());
    }

    public static function combinedNotes(array $draft): string
    {
        return implode("\n\n", array_filter([
            trim($draft['care_notes'] ?? ''),
            trim($draft['care_preferences'] ?? '') !== '' ? 'What helps care go well: '.trim($draft['care_preferences']) : '',
        ]));
    }

    public function requestHandoff(User $user): ?FamilyOnboarding
    {
        $record = $this->forOwner($user);
        if ($record?->status !== 'completed' || $record->request_handoff_completed_at
            || CareRequest::query()->where('family_account_id', $record->family_account_id)->where('is_system_generated', false)->exists()) {
            return null;
        }

        return $record;
    }
}
