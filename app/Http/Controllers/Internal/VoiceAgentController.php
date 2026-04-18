<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Services\Ops\OpsAlertService;
use App\Services\VoiceAgent\VoiceAgentIntakeService;
use App\Services\VoiceAgent\VoiceAgentKnowledgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $opsAlerts->notifyVoiceCallReported($payload);

        return response()->json([
            'message' => 'Voice call report sent.',
        ], 201);
    }
}
