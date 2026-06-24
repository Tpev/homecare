<?php

namespace App\Livewire\Admin;

use App\Models\Lead;
use App\Models\VoiceAiCall;
use App\Services\Messaging\TwilioSmsClient;
use App\Services\VoiceAgent\TwilioVoiceClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
class ProviderOutreachAi extends Component
{
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
}
