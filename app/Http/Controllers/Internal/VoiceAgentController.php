<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\VoiceAiCall;
use App\Services\Ops\OpsAlertService;
use App\Services\VoiceAgent\VoiceAgentIntakeService;
use App\Services\VoiceAgent\VoiceAgentKnowledgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class VoiceAgentController extends Controller
{
    public function knowledge(VoiceAgentKnowledgeService $knowledge): JsonResponse
    {
        return response()->json($knowledge->payload());
    }

    public function createLead(Request $request, VoiceAgentIntakeService $intake): JsonResponse
    {
        $payload = $request->validate([
            'lead_type' => ['required', 'string', Rule::in(['family', 'caregiver', 'agency', 'general'])],
            'intent' => ['required', 'string', Rule::in(['information', 'callback_request', 'signup_link', 'general'])],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'company' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'call_sid' => ['nullable', 'string', 'max:64'],
            'transcript_excerpt' => ['nullable', 'string', 'max:4000'],
            'source_url' => ['nullable', 'string', 'max:255'],
            'referrer_url' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);

        $lead = $intake->createLead($payload, $request);

        return response()->json([
            'lead_id' => $lead->id,
            'status' => $lead->status,
        ], 201);
    }

    public function requestCallback(Request $request, VoiceAgentIntakeService $intake): JsonResponse
    {
        $payload = $request->validate([
            'lead_type' => ['nullable', 'string', Rule::in(['family', 'caregiver', 'agency', 'general'])],
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'callback_time' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'call_sid' => ['nullable', 'string', 'max:64'],
            'transcript_excerpt' => ['nullable', 'string', 'max:4000'],
            'metadata' => ['nullable', 'array'],
        ]);

        $lead = $intake->createCallbackRequest($payload, $request);

        return response()->json([
            'lead_id' => $lead->id,
            'status' => $lead->status,
            'message' => 'Callback request captured.',
        ], 201);
    }

    public function signupLink(Request $request, VoiceAgentIntakeService $intake, VoiceAgentKnowledgeService $knowledge): JsonResponse
    {
        $payload = $request->validate([
            'lead_type' => ['required', 'string', Rule::in(['family', 'caregiver', 'agency', 'general'])],
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'call_sid' => ['nullable', 'string', 'max:64'],
            'consent_received' => ['required', 'boolean'],
            'transcript_excerpt' => ['nullable', 'string', 'max:4000'],
            'metadata' => ['nullable', 'array'],
        ]);

        $result = $intake->createSignupRequest($payload, $request, $knowledge->signupLinkForAudience($payload['lead_type']));

        return response()->json($result, 201);
    }

    public function report(Request $request, OpsAlertService $opsAlerts): JsonResponse
    {
        $payload = $request->validate([
            'call_sid' => ['nullable', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'max:30'],
            'name' => ['nullable', 'string', 'max:255'],
            'relationship' => ['nullable', 'string', 'max:255'],
            'care_recipient' => ['nullable', 'string', 'max:255'],
            'care_needs' => ['nullable', 'string', 'max:2000'],
            'urgency' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:20'],
            'callback_time' => ['nullable', 'string', 'max:255'],
            'lead_type' => ['nullable', 'string', Rule::in(['family', 'caregiver', 'agency', 'general'])],
            'intent' => ['nullable', 'string', Rule::in(['information', 'callback_request', 'signup_link', 'general', 'unknown'])],
            'outcome' => ['required', 'string', 'max:100'],
            'call_status' => ['required', 'string', 'max:100'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'transcript' => ['nullable', 'string', 'max:20000'],
            'callback_requested' => ['nullable', 'boolean'],
            'signup_link_sent' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]);

        $this->syncVoiceAiCallReport($payload);
        $opsAlerts->notifyVoiceCallReported($payload);

        return response()->json([
            'message' => 'Voice call report sent.',
        ], 201);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncVoiceAiCallReport(array $payload): void
    {
        $call = $this->voiceAiCallForReport($payload);
        if (! $call) {
            return;
        }

        $rawPayload = is_array($call->raw_payload) ? $call->raw_payload : [];
        $rawPayload['voice_agent_report'] = $payload;

        $metadata = is_array($call->metadata) ? $call->metadata : [];
        $metadata['voice_agent_reported_at'] = now()->toISOString();
        $metadata['voice_agent_outcome'] = (string) ($payload['outcome'] ?? '');
        $metadata['voice_agent_profile'] = (string) data_get($payload, 'metadata.voice_agent_profile', $metadata['voice_agent_profile'] ?? '');

        $updates = [
            'status' => $this->voiceAiStatusFromReport((string) ($payload['call_status'] ?? '')),
            'current_step' => 'reported',
            'summary' => filled($payload['summary'] ?? null) ? (string) $payload['summary'] : $call->summary,
            'raw_payload' => $rawPayload,
            'metadata' => $metadata,
            'callback_requested' => (bool) ($payload['callback_requested'] ?? $call->callback_requested),
            'signup_link_requested' => (bool) ($payload['signup_link_sent'] ?? $call->signup_link_requested),
        ];

        foreach ([
            'name' => 'gathered_name',
            'relationship' => 'gathered_relationship',
            'phone' => 'gathered_phone',
            'urgency' => 'gathered_urgency',
            'callback_time' => 'gathered_callback_time',
            'care_needs' => 'gathered_care_needs',
        ] as $source => $target) {
            if (filled($payload[$source] ?? null)) {
                $updates[$target] = (string) $payload[$source];
            }
        }

        $location = collect([
            $payload['address'] ?? null,
            $payload['city'] ?? null,
            $payload['zip'] ?? null,
        ])->filter(fn ($value): bool => filled($value))->implode(', ');

        if ($location !== '') {
            $updates['gathered_location'] = $location;
        }

        if (filled($payload['duration_seconds'] ?? null)) {
            $updates['duration_seconds'] = (int) $payload['duration_seconds'];
        }

        if (filled($payload['started_at'] ?? null)) {
            $updates['started_at'] = $this->parseReportDate((string) $payload['started_at']) ?: $call->started_at;
        }

        if (filled($payload['ended_at'] ?? null)) {
            $updates['ended_at'] = $this->parseReportDate((string) $payload['ended_at']) ?: $call->ended_at;
        }

        if (! $call->answered_at && isset($updates['started_at'])) {
            $updates['answered_at'] = $updates['started_at'];
        }

        if (filled($payload['transcript'] ?? null)) {
            $transcript = (string) $payload['transcript'];
            $updates['transcript_text'] = $transcript;
            $updates['transcript'] = $this->transcriptTurnsFromText($transcript);
        }

        $call->update($updates);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function voiceAiCallForReport(array $payload): ?VoiceAiCall
    {
        $id = data_get($payload, 'metadata.voice_ai_call_id');
        if (filled($id) && ctype_digit((string) $id)) {
            $call = VoiceAiCall::query()->find((int) $id);
            if ($call) {
                return $call;
            }
        }

        $callSid = (string) ($payload['call_sid'] ?? '');
        if ($callSid !== '') {
            return VoiceAiCall::query()->where('twilio_call_sid', $callSid)->first();
        }

        return null;
    }

    private function voiceAiStatusFromReport(string $status): string
    {
        return match (strtolower(trim($status))) {
            'error', 'failed' => VoiceAiCall::STATUS_FAILED,
            'busy' => VoiceAiCall::STATUS_BUSY,
            'no-answer', 'no_answer' => VoiceAiCall::STATUS_NO_ANSWER,
            'canceled', 'cancelled' => VoiceAiCall::STATUS_CANCELLED,
            default => VoiceAiCall::STATUS_COMPLETED,
        };
    }

    private function parseReportDate(string $value): ?Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<array{speaker: string, text: string, at: string}>
     */
    private function transcriptTurnsFromText(string $transcript): array
    {
        $now = now()->toISOString();
        $turns = [];

        foreach (preg_split('/\R+/', trim($transcript)) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $speaker = 'assistant';
            $text = $line;

            if (preg_match('/^([^:]{1,40}):\s*(.+)$/', $line, $matches)) {
                $role = strtolower(trim($matches[1]));
                $speaker = str_contains($role, 'user') || str_contains($role, 'caller') ? 'caller' : 'assistant';
                $text = trim($matches[2]);
            }

            $turns[] = [
                'speaker' => $speaker,
                'text' => $text,
                'at' => $now,
            ];
        }

        return $turns;
    }
}
