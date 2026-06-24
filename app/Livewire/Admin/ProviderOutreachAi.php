<?php

namespace App\Livewire\Admin;

use App\Models\Lead;
use App\Models\VoiceAiCall;
use App\Services\Messaging\TwilioSmsClient;
use App\Services\VoiceAgent\TwilioVoiceClient;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

#[Layout('layouts.app')]
class ProviderOutreachAi extends Component
{
    use WithFileUploads;

    public $csvFile = null;

    public string $batchLabel = '';

    public ?string $activeBatchId = null;

    /** @var array<string, string> */
    public array $targetForm = [
        'practice_name' => '',
        'contact_name' => '',
        'contact_role' => '',
        'phone' => '',
        'email' => '',
        'fax' => '',
        'location' => '',
        'notes' => '',
    ];

    public function startCall(): void
    {
        $this->validate([
            'targetForm.practice_name' => ['required', 'string', 'max:180'],
            'targetForm.contact_name' => ['nullable', 'string', 'max:160'],
            'targetForm.contact_role' => ['nullable', 'string', 'max:120'],
            'targetForm.phone' => ['required', 'string', 'max:40'],
            'targetForm.email' => ['nullable', 'email', 'max:160'],
            'targetForm.fax' => ['nullable', 'string', 'max:40'],
            'targetForm.location' => ['nullable', 'string', 'max:160'],
            'targetForm.notes' => ['nullable', 'string', 'max:1200'],
        ]);

        $normalized = TwilioSmsClient::normalizePhone($this->targetForm['phone']);
        if ($normalized === '') {
            $this->addError('targetForm.phone', 'Enter a valid provider phone number.');

            return;
        }

        try {
            $call = app(TwilioVoiceClient::class)->startProviderOutreachCall($this->targetForm, auth()->user());
        } catch (Throwable $e) {
            $this->addError('call', $e->getMessage());

            return;
        }

        $practice = $this->targetForm['practice_name'];
        $this->resetTargetForm();
        session()->flash('status', 'Julie AI outreach call queued to '.$practice.' at '.$call->to_phone.'.');
    }

    public function uploadCsvBatch(): void
    {
        $this->validate([
            'csvFile' => ['required', 'file', 'max:5120'],
            'batchLabel' => ['nullable', 'string', 'max:120'],
        ]);

        $path = method_exists($this->csvFile, 'getRealPath') ? $this->csvFile->getRealPath() : null;
        if (! $path) {
            $this->addError('csvFile', 'Upload a readable CSV file.');

            return;
        }

        $rows = $this->parseProviderCsv($path);
        if ($rows === []) {
            $this->addError('csvFile', 'No usable provider rows found. Include at least practice/name and phone columns.');

            return;
        }

        $batchId = (string) Str::ulid();
        $label = trim($this->batchLabel) !== ''
            ? trim($this->batchLabel)
            : 'Provider outreach '.now()->format('M d, H:i');
        $queued = 0;
        $skipped = 0;

        foreach ($rows as $position => $target) {
            try {
                app(TwilioVoiceClient::class)->queueProviderOutreachDraft(
                    $target,
                    auth()->user(),
                    $batchId,
                    $position + 1,
                    $label,
                );
                $queued++;
            } catch (Throwable) {
                $skipped++;
            }
        }

        if ($queued === 0) {
            $this->addError('csvFile', 'No rows could be queued. Check the phone numbers and do-not-call records.');

            return;
        }

        $this->activeBatchId = $batchId;
        $this->reset('csvFile', 'batchLabel');

        session()->flash('status', "CSV batch ready: {$queued} provider source(s) queued".($skipped > 0 ? ", {$skipped} skipped" : '').'.');
    }

    public function startNextBatchCall(): void
    {
        $batchId = $this->currentBatchId();
        if ($batchId === '') {
            $this->addError('batch', 'Upload or select a CSV batch first.');

            return;
        }

        $activeCall = VoiceAiCall::query()
            ->where('metadata->voice_agent_profile', VoiceAiCall::PROFILE_PROVIDER_OUTREACH)
            ->where('metadata->provider_outreach_batch_id', $batchId)
            ->whereIn('status', [VoiceAiCall::STATUS_QUEUED, VoiceAiCall::STATUS_RINGING, VoiceAiCall::STATUS_IN_PROGRESS])
            ->first();

        if ($activeCall) {
            $this->addError('batch', 'There is already a live or queued call in this batch. Wait for it to finish, then start the next one.');

            return;
        }

        $nextCall = VoiceAiCall::query()
            ->where('metadata->voice_agent_profile', VoiceAiCall::PROFILE_PROVIDER_OUTREACH)
            ->where('metadata->provider_outreach_batch_id', $batchId)
            ->where('status', VoiceAiCall::STATUS_DRAFT)
            ->oldest()
            ->first();

        if (! $nextCall) {
            session()->flash('status', 'This CSV batch is complete. No waiting calls remain.');

            return;
        }

        try {
            $call = app(TwilioVoiceClient::class)->startQueuedProviderOutreachCall($nextCall, auth()->user());
        } catch (Throwable $e) {
            $this->addError('batch', $e->getMessage());

            return;
        }

        $this->activeBatchId = $batchId;
        session()->flash('status', 'Started next Julie call to '.(data_get($call->metadata, 'target_organization') ?: $call->to_phone).'.');
    }

    public function fillFromLead(int $leadId): void
    {
        $lead = Lead::query()
            ->where('lead_type', Lead::TYPE_REFERRAL)
            ->findOrFail($leadId);

        $this->targetForm = [
            'practice_name' => (string) ($lead->company ?: $lead->name),
            'contact_name' => (string) ($lead->company ? $lead->name : ''),
            'contact_role' => (string) $lead->contact_role,
            'phone' => (string) $lead->phone,
            'email' => (string) $lead->email,
            'fax' => (string) data_get($lead->data, 'provider_outreach.target.fax', ''),
            'location' => (string) $lead->location,
            'notes' => (string) data_get($lead->data, 'provider_outreach.last_summary', data_get($lead->data, 'notes', '')),
        ];
    }

    public function resetTargetForm(): void
    {
        $this->targetForm = [
            'practice_name' => '',
            'contact_name' => '',
            'contact_role' => '',
            'phone' => '',
            'email' => '',
            'fax' => '',
            'location' => '',
            'notes' => '',
        ];
    }

    public function render(): View
    {
        $batchId = $this->currentBatchId();
        $batchCalls = $batchId !== ''
            ? VoiceAiCall::query()
                ->where('metadata->voice_agent_profile', VoiceAiCall::PROFILE_PROVIDER_OUTREACH)
                ->where('metadata->provider_outreach_batch_id', $batchId)
                ->oldest()
                ->get()
            : collect();

        $calls = VoiceAiCall::query()
            ->with('admin:id,name,email')
            ->where('metadata->voice_agent_profile', VoiceAiCall::PROFILE_PROVIDER_OUTREACH)
            ->latest()
            ->limit(25)
            ->get();

        $referralLeads = Lead::query()
            ->where('lead_type', Lead::TYPE_REFERRAL)
            ->latest('updated_at')
            ->limit(12)
            ->get();

        return view('livewire.admin.provider-outreach-ai', [
            'calls' => $calls,
            'referralLeads' => $referralLeads,
            'summary' => [
                'draft' => VoiceAiCall::query()
                    ->where('metadata->voice_agent_profile', VoiceAiCall::PROFILE_PROVIDER_OUTREACH)
                    ->where('status', VoiceAiCall::STATUS_DRAFT)
                    ->count(),
                'queued' => VoiceAiCall::query()
                    ->where('metadata->voice_agent_profile', VoiceAiCall::PROFILE_PROVIDER_OUTREACH)
                    ->whereIn('status', [VoiceAiCall::STATUS_QUEUED, VoiceAiCall::STATUS_RINGING, VoiceAiCall::STATUS_IN_PROGRESS])
                    ->count(),
                'completed' => VoiceAiCall::query()
                    ->where('metadata->voice_agent_profile', VoiceAiCall::PROFILE_PROVIDER_OUTREACH)
                    ->where('status', VoiceAiCall::STATUS_COMPLETED)
                    ->count(),
                'resource_requested' => Lead::query()
                    ->where('lead_type', Lead::TYPE_REFERRAL)
                    ->where('source', 'ai_provider_outreach')
                    ->where('data->provider_outreach->resource_requested', true)
                    ->count(),
                'do_not_call' => Lead::query()
                    ->where('lead_type', Lead::TYPE_REFERRAL)
                    ->where('data->provider_outreach->do_not_call', true)
                    ->count(),
            ],
            'batchCalls' => $batchCalls,
            'batchId' => $batchId,
            'batchLabelForView' => (string) data_get($batchCalls->first()?->metadata, 'provider_outreach_batch_label', ''),
            'batchSummary' => [
                'waiting' => $batchCalls->where('status', VoiceAiCall::STATUS_DRAFT)->count(),
                'active' => $batchCalls->whereIn('status', [VoiceAiCall::STATUS_QUEUED, VoiceAiCall::STATUS_RINGING, VoiceAiCall::STATUS_IN_PROGRESS])->count(),
                'done' => $batchCalls->whereIn('status', [VoiceAiCall::STATUS_COMPLETED, VoiceAiCall::STATUS_FAILED, VoiceAiCall::STATUS_BUSY, VoiceAiCall::STATUS_NO_ANSWER, VoiceAiCall::STATUS_CANCELLED])->count(),
                'good' => $batchCalls->filter(fn (VoiceAiCall $call): bool => data_get($call->metadata, 'provider_outreach_interaction_rating') === 'good')->count(),
                'bad' => $batchCalls->filter(fn (VoiceAiCall $call): bool => data_get($call->metadata, 'provider_outreach_interaction_rating') === 'bad')->count(),
                'error' => $batchCalls->filter(fn (VoiceAiCall $call): bool => data_get($call->metadata, 'provider_outreach_interaction_rating') === 'error')->count(),
                'ivr_vm' => $batchCalls->filter(fn (VoiceAiCall $call): bool => data_get($call->metadata, 'provider_outreach_interaction_rating') === 'ivr_vm')->count(),
            ],
            'roleOptions' => [
                '' => 'Unknown / front desk',
                'office_manager' => 'Office manager',
                'social_worker' => 'Social worker',
                'case_manager' => 'Case manager',
                'discharge_planner' => 'Discharge planner',
                'care_coordinator' => 'Care coordinator',
                'pcp' => 'PCP / clinician',
                'senior_center' => 'Senior center staff',
            ],
            'voiceFrom' => TwilioSmsClient::normalizePhone((string) config('services.twilio.voice_from')),
            'voiceAgentCallbackUrl' => trim((string) config('services.twilio.voice_agent_callback_url')),
            'twilioBypass' => (bool) config('services.twilio.bypass', false),
        ]);
    }

    private function currentBatchId(): string
    {
        if (filled($this->activeBatchId)) {
            return (string) $this->activeBatchId;
        }

        $latestBatchCall = VoiceAiCall::query()
            ->where('metadata->voice_agent_profile', VoiceAiCall::PROFILE_PROVIDER_OUTREACH)
            ->latest()
            ->limit(100)
            ->get()
            ->first(fn (VoiceAiCall $call): bool => filled(data_get($call->metadata, 'provider_outreach_batch_id')));

        return (string) data_get($latestBatchCall?->metadata, 'provider_outreach_batch_id', '');
    }

    /**
     * @return list<array<string, string>>
     */
    private function parseProviderCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return [];
        }

        $headers = [];
        while (($row = fgetcsv($handle)) !== false) {
            if ($this->csvRowIsBlank($row)) {
                continue;
            }

            $headers = array_map(fn ($header): string => $this->csvHeaderKey((string) $header), $row);
            break;
        }

        if ($headers === []) {
            fclose($handle);

            return [];
        }

        $targets = [];
        while (($row = fgetcsv($handle)) !== false) {
            if ($this->csvRowIsBlank($row)) {
                continue;
            }

            $data = [];
            foreach ($row as $index => $value) {
                $key = $headers[$index] ?? '';
                if ($key !== '') {
                    $data[$key] = trim((string) $value);
                }
            }

            $phone = $this->csvFirst($data, ['phone']);
            if ($phone === '') {
                continue;
            }

            $practiceName = $this->csvFirst($data, ['practice_name']);
            $contactName = $this->csvFirst($data, ['contact_name']);
            if ($practiceName === '' && $contactName !== '') {
                $practiceName = $contactName;
            }

            if ($practiceName === '') {
                $practiceName = 'Provider source '.$phone;
            }

            $targets[] = [
                'practice_name' => $practiceName,
                'contact_name' => $contactName,
                'contact_role' => $this->csvFirst($data, ['contact_role']),
                'phone' => $phone,
                'email' => $this->csvFirst($data, ['email']),
                'fax' => $this->csvFirst($data, ['fax']),
                'location' => $this->csvFirst($data, ['location']),
                'notes' => $this->csvFirst($data, ['notes']),
            ];
        }

        fclose($handle);

        return $targets;
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function csvRowIsBlank(array $row): bool
    {
        return collect($row)->every(fn ($value): bool => trim((string) $value) === '');
    }

    private function csvHeaderKey(string $header): string
    {
        $key = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($header)), '_');

        return match (true) {
            in_array($key, ['practice_name', 'practice', 'organization', 'organisation', 'company', 'clinic', 'facility', 'office', 'business', 'account', 'account_name', 'name'], true) => 'practice_name',
            in_array($key, ['contact_name', 'contact', 'person', 'person_name', 'full_name', 'provider', 'provider_name', 'physician', 'doctor', 'pcp', 'social_worker', 'case_manager'], true) => 'contact_name',
            in_array($key, ['contact_role', 'role', 'title', 'position', 'job_title'], true) => 'contact_role',
            in_array($key, ['phone', 'phone_number', 'telephone', 'mobile', 'office_phone', 'number'], true) => 'phone',
            in_array($key, ['email', 'email_address', 'mail'], true) => 'email',
            in_array($key, ['fax', 'fax_number'], true) => 'fax',
            in_array($key, ['location', 'city', 'address', 'street', 'county', 'area'], true) => 'location',
            in_array($key, ['notes', 'note', 'context', 'comments', 'comment'], true) => 'notes',
            default => $key,
        };
    }

    /**
     * @param  array<string, string>  $data
     * @param  list<string>  $keys
     */
    private function csvFirst(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            if (filled($data[$key] ?? null)) {
                return trim((string) $data[$key]);
            }
        }

        return '';
    }
}
