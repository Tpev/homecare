<?php

namespace App\Livewire\Admin;

use App\Models\VoiceAiCall;
use App\Services\Messaging\TwilioSmsClient;
use App\Services\VoiceAgent\TwilioVoiceClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
class VoiceAiTest extends Component
{
    public string $phoneNumber = '';

    public function startCall(): void
    {
        $this->validate([
            'phoneNumber' => ['required', 'string', 'max:40'],
        ]);

        $normalized = TwilioSmsClient::normalizePhone($this->phoneNumber);
        if ($normalized === '') {
            $this->addError('phoneNumber', 'Enter a valid phone number.');

            return;
        }

        try {
            $call = app(TwilioVoiceClient::class)->startTestCall($normalized, auth()->user());
        } catch (Throwable $e) {
            $this->addError('call', $e->getMessage());

            return;
        }

        $this->phoneNumber = '';
        session()->flash('status', 'Voice AI test call queued to '.$call->to_phone.'.');
    }

    public function render(): View
    {
        $calls = VoiceAiCall::query()
            ->with('admin:id,name,email')
            ->latest()
            ->limit(25)
            ->get();

        return view('livewire.admin.voice-ai-test', [
            'calls' => $calls,
            'summary' => [
                'total' => VoiceAiCall::query()->count(),
                'in_progress' => VoiceAiCall::query()->where('status', VoiceAiCall::STATUS_IN_PROGRESS)->count(),
                'completed' => VoiceAiCall::query()->where('status', VoiceAiCall::STATUS_COMPLETED)->count(),
                'failed' => VoiceAiCall::query()->whereIn('status', [
                    VoiceAiCall::STATUS_FAILED,
                    VoiceAiCall::STATUS_BUSY,
                    VoiceAiCall::STATUS_NO_ANSWER,
                    VoiceAiCall::STATUS_CANCELLED,
                ])->count(),
            ],
            'voiceFrom' => TwilioSmsClient::normalizePhone((string) config('services.twilio.voice_from')),
            'twilioBypass' => (bool) config('services.twilio.bypass', false),
        ]);
    }
}
