<?php

namespace App\Services\CustomerCare;

use App\Jobs\GenerateCaregiverRoster;
use App\Jobs\SendCaregiverRosterEntry;
use App\Models\CaregiverRoster;
use App\Models\CaregiverRosterEntry;
use App\Models\CareRequest;
use App\Models\User;
use App\Services\Marketplace\CareRequestInvitationService;
use App\Services\Messaging\LeadSmsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CareRosterService
{
    public function __construct(private CareRosterEvidence $evidence, private CareRosterAi $ai) {}

    public function start(User $actor, CareRequest $request, string $context, string $urgency): CaregiverRoster
    {
        CareRosterAccess::authorize($actor, $request);
        Validator::make(compact('context', 'urgency'), ['context' => ['string', 'max:6000'], 'urgency' => ['required', 'in:normal,soon,urgent']])->validate();
        $this->ensure($request->fresh()->status === 'open' && $request->isAcceptingApplications(), 'Choose an open request accepting caregivers.');

        return Cache::lock('care-roster:create:'.$request->id, 10)->block(3, function () use ($actor, $request, $context, $urgency) {
            $pending = CaregiverRoster::where('care_request_id', $request->id)->whereIn('status', ['queued', 'generating'])
                ->where('prompt_version', CareRosterAi::VERSION)->where('updated_at', '>', now()->subMinutes(35))->first();
            if ($pending) {
                return $pending;
            }
            $roster = CaregiverRoster::create([
                'care_request_id' => $request->id, 'created_by_user_id' => $actor->id,
                'context' => trim($context), 'urgency' => $urgency, 'status' => 'queued',
                'request_fingerprint' => $this->evidence->requestFingerprint($request),
                'model' => config('care_roster.model'), 'prompt_version' => CareRosterAi::VERSION,
                'coverage' => ['ai' => CareRosterAi::settings()],
            ]);
            GenerateCaregiverRoster::dispatch($roster->id)->afterCommit();

            return $roster;
        });
    }

    /** One bounded queue step: collect, assess one batch, or rank and finish. */
    public function generate(int $id): bool
    {
        return Cache::lock('care-roster:generate:'.$id, CareRosterAi::requestTimeout() + 30)->block(3, function () use ($id): bool {
            $roster = CaregiverRoster::findOrFail($id);
            if (! in_array($roster->status, ['queued', 'generating'])) {
                return false;
            }
            try {
                $actor = User::findOrFail($roster->created_by_user_id);
                $request = $roster->careRequest;
                CareRosterAccess::authorize($actor, $request);
                $this->ensure($roster->prompt_version === CareRosterAi::VERSION, 'The roster assistant was upgraded. Generate a fresh roster.');
                $this->ensure($roster->request_fingerprint === $this->evidence->requestFingerprint($request), 'Customer context changed. Generate a fresh roster.');
                $settings = data_get($roster->coverage, 'ai', CareRosterAi::settings());
                $settings['model'] = $roster->model;
                $data = $roster->generation_data;
                if (! $data) {
                    $data = ['customer' => $this->evidence->customer($request), 'pool' => $this->evidence->pool($request),
                        'assessments' => [], 'usage' => ['input_tokens' => 0, 'cached_input_tokens' => 0, 'output_tokens' => 0]];
                    $data['pool']['coverage']['ai'] = $settings;
                    $roster->update(['status' => 'generating', 'generation_data' => $data, 'coverage' => $data['pool']['coverage']]);

                    return true;
                }
                $customer = $data['customer'];
                $pool = $data['pool'];
                $assessments = $data['assessments'];
                $usage = $data['usage'];
                $batch = array_slice($pool['candidates'], count($assessments), (int) config('care_roster.batch_size'));
                if ($batch) {
                    $result = $this->ai->assess($actor, $customer, $batch, $roster->context ?? '', $roster->urgency, $settings);
                    $data['assessments'] = array_merge($assessments, $result['assessments']);
                    foreach ($usage as $key => $value) {
                        $data['usage'][$key] += $result['usage'][$key] ?? 0;
                    }
                    $roster->update(['generation_data' => $data]);

                    return true;
                }
                $ranking = ['summary' => 'No eligible regional caregivers were found. Review the request and caregiver coverage.', 'customer_questions' => [], 'caregiver_user_ids' => []];
                if ($assessments) {
                    $ranked = $this->ai->rank($actor, $customer, $assessments, $roster->context ?? '', $roster->urgency, $pool['candidates'], $settings);
                    $ranking = $ranked['result'];
                    foreach ($usage as $key => $value) {
                        $usage[$key] += $ranked['usage'][$key] ?? 0;
                    }
                }
                DB::transaction(function () use ($roster, $actor, $request, $pool, $customer, $assessments, $ranking, $usage): void {
                    CareRosterAccess::authorize($actor, $request);
                    $this->ensure($roster->request_fingerprint === $this->evidence->requestFingerprint($request), 'The request changed during generation. Generate a fresh roster.');
                    $packets = collect($pool['candidates'])->keyBy('caregiver_user_id');
                    $assessments = collect($assessments)->keyBy('caregiver_user_id');
                    foreach ($ranking['caregiver_user_ids'] as $rank => $userId) {
                        $packet = $packets[$userId];
                        $assessment = $assessments[$userId];
                        $roster->entries()->create([
                            'caregiver_user_id' => $userId, 'rank' => $rank + 1,
                            'action' => $packet['operational']['action'], 'assessment' => $assessment,
                            'evidence' => ['caregiver' => $packet, 'customer' => $this->evidence->sanitize($customer)],
                            'invitation_body' => $assessment['invitation_body'], 'sms_body' => $assessment['sms_body'],
                            'sms_request_key' => (string) Str::uuid(),
                        ]);
                    }
                    $roster->update(['status' => 'ready', 'summary' => $ranking['summary'], 'questions' => $ranking['customer_questions'],
                        'coverage' => $pool['coverage'], 'usage' => $usage, 'generated_at' => now(), 'error' => null, 'generation_data' => null]);
                });

                return false;
            } catch (\Throwable $exception) {
                // Do not log source packets, prompts or provider exception chains.
                $roster->update(['status' => 'failed', 'generation_data' => null,
                    'error' => $exception instanceof ValidationException
                        ? 'The draft could not be validated. Please generate a fresh roster.'
                        : (get_class($exception) === \RuntimeException::class ? mb_substr($exception->getMessage(), 0, 300) : 'The roster could not be generated. Please try again.')]);

                return false;
            }
        });
    }

    public function prepare(User $actor, CaregiverRoster $roster, array $drafts): string
    {
        CareRosterAccess::authorize($actor, $roster->careRequest);
        Validator::make(['drafts' => $drafts], ['drafts' => ['required', 'array', 'min:1', 'max:12'],
            'drafts.*.id' => ['required', 'integer', 'distinct'], 'drafts.*.send_sms' => ['required', 'boolean'],
            'drafts.*.invitation_body' => ['present', 'string', 'max:1000'], 'drafts.*.sms_body' => ['present', 'string', 'max:1600'],
        ])->validate();

        return DB::transaction(function () use ($actor, $roster, $drafts): string {
            $roster = CaregiverRoster::lockForUpdate()->findOrFail($roster->id);
            $this->assertDraft($roster);
            $entries = $roster->entries()->get()->keyBy('id');
            $roster->entries()->update(['selected' => false, 'send_sms' => false]);
            foreach ($drafts as $draft) {
                $entry = $entries->get($draft['id']);
                $this->ensure((bool) $entry, 'The selected caregiver is not part of this roster.');
                $state = $this->evidence->state($roster->careRequest->fresh(), $entry->caregiver->fresh());
                $this->assertAction($entry, $state, (bool) $draft['send_sms']);
                $this->ensure($this->evidence->stateFingerprint($state) === $this->evidence->stateFingerprint($entry->evidence['caregiver']['operational']),
                    'Caregiver evidence changed. Generate a fresh roster before reviewing outreach.');
                $this->ensure($entry->action !== 'invite' || trim($draft['invitation_body']) !== '', 'Review the invitation text for each new invitation.');
                $this->ensure(! $draft['send_sms'] || trim($draft['sms_body']) !== '', 'Review the SMS text for every selected SMS.');
                $entry->update(['selected' => true, 'send_sms' => (bool) $draft['send_sms'],
                    'invitation_body' => trim($draft['invitation_body']), 'sms_body' => trim($draft['sms_body']),
                    'review_fingerprint' => $this->evidence->stateFingerprint($state)]);
            }
            $token = (string) Str::uuid();
            $roster->update(['review_token' => $token, 'reviewed_at' => now(), 'reviewed_by_user_id' => $actor->id]);

            return $token;
        });
    }

    public function approve(User $actor, CaregiverRoster $roster, string $token): void
    {
        CareRosterAccess::authorize($actor, $roster->careRequest);
        DB::transaction(function () use ($actor, $roster, $token): void {
            $roster = CaregiverRoster::lockForUpdate()->findOrFail($roster->id);
            $this->ensure($token !== '' && hash_equals((string) $roster->review_token, $token)
                && (int) $roster->reviewed_by_user_id === $actor->id, 'Review the current recipients and messages before approving.');
            if ($roster->approved_at) {
                return;
            }
            $this->assertDraft($roster);
            $this->ensure($roster->reviewed_at && $roster->reviewed_at->gt(now()->subMinutes(config('care_roster.review_minutes'))), 'This preview expired. Review the outreach again.');
            $entries = $roster->entries()->where('selected', true)->get();
            $this->ensure($entries->isNotEmpty(), 'Select at least one caregiver.');
            foreach ($entries as $entry) {
                $state = $this->evidence->state($roster->careRequest->fresh(), $entry->caregiver->fresh());
                $this->assertAction($entry, $state, $entry->send_sms);
                $this->ensure(hash_equals($entry->review_fingerprint, $this->evidence->stateFingerprint($state)), 'Caregiver availability, replies or eligibility changed. Generate a fresh roster.');
            }
            $roster->update(['status' => 'approved', 'approved_at' => now(), 'approved_by_user_id' => $actor->id]);
            foreach ($entries as $entry) {
                $entry->update(['dispatch_status' => 'queued']);
                SendCaregiverRosterEntry::dispatch($entry->id)->afterCommit();
            }
            app(ManagedCareService::class)->audit($actor, $roster->careRequest, 'roster_approved', metadata: ['roster_id' => $roster->id, 'entry_ids' => $entries->pluck('id')->all()]);
        });
    }

    public function dispatch(int $entryId): void
    {
        Cache::lock('care-roster:dispatch:'.$entryId, 180)->block(5, function () use ($entryId): void {
            $entry = CaregiverRosterEntry::with('roster.careRequest', 'caregiver')->findOrFail($entryId);
            if (! $entry->selected || ! $entry->roster->approved_at || ! in_array($entry->dispatch_status, ['queued', 'sending'])) {
                return;
            }
            // A worker can die after the existing SMS service committed its durable attempt.
            // Recover the result without treating an uncertain send as permission to send again.
            $recordedSms = \App\Models\SmsMessage::where('request_key', $entry->sms_request_key)->first();
            if ($recordedSms) {
                $entry->update(['sms_message_id' => $recordedSms->id, 'dispatch_status' => 'completed',
                    'dispatch_note' => 'SMS attempt already recorded: '.$recordedSms->status.'. Check delivery status before further outreach.']);

                return;
            }
            try {
                $actor = User::findOrFail($entry->roster->approved_by_user_id);
                $request = $entry->roster->careRequest;
                CareRosterAccess::authorize($actor, $request);
                $this->ensure($entry->roster->request_fingerprint === $this->evidence->requestFingerprint($request), 'The request changed after approval. Review a new roster.');
                $state = $this->evidence->state($request, $entry->caregiver);
                $this->ensure(! $state['blocked_reason'], $state['blocked_reason'] ?? 'Caregiver unavailable.');
                if (! $entry->care_request_invitation_id) {
                    $this->assertAction($entry, $state, $entry->send_sms);
                    $this->ensure(hash_equals($entry->review_fingerprint, $this->evidence->stateFingerprint($state)), 'Caregiver evidence changed after approval. Review a fresh roster.');
                } else {
                    $this->ensure($state['invitation_id'] === $entry->care_request_invitation_id && $state['action'] === 'follow_up', 'The caregiver already responded or the invitation changed.');
                    $this->ensure(hash_equals($entry->review_fingerprint, $this->evidence->stateFingerprint($state)), 'Caregiver evidence changed since the invitation. Review before sending SMS.');
                }
                $entry->update(['dispatch_status' => 'sending']);
                if ($entry->action === 'invite' && ! $entry->care_request_invitation_id) {
                    $result = app(CareRequestInvitationService::class)->send($request->family, $request, $entry->caregiver,
                        $entry->invitation_body, source: 'cs_ai_roster', staffActor: $actor);
                    $this->ensure($result->sentNow && $result->invitation, $result->message);
                    $entry->update(['care_request_invitation_id' => $result->invitation->id]);
                    $afterInvite = $this->evidence->state($request->fresh(), $entry->caregiver->fresh());
                    $expected = array_replace($state, ['action' => 'follow_up', 'invitation_id' => $result->invitation->id, 'invitation_status' => 'pending']);
                    $this->ensure($this->evidence->stateFingerprint($expected) === $this->evidence->stateFingerprint($afterInvite), 'New evidence arrived while sending the invitation. Review before sending SMS.');
                    $entry->update(['review_fingerprint' => $this->evidence->stateFingerprint($afterInvite)]);
                } elseif ($entry->action === 'follow_up') {
                    $entry->update(['care_request_invitation_id' => $state['invitation_id']]);
                }
                if ($entry->send_sms) {
                    $message = app(LeadSmsService::class)->sendForCareRoster($entry->caregiver, $actor, $request, $entry->sms_body, $entry->sms_request_key);
                    $entry->update(['sms_message_id' => $message->id, 'dispatch_status' => 'completed',
                        'dispatch_note' => 'SMS '.$message->status.'. Check delivery status before any further outreach.']);
                } else {
                    $entry->update(['dispatch_status' => 'completed', 'dispatch_note' => 'Invitation sent. SMS was not selected.']);
                }
            } catch (\Throwable $exception) {
                $message = \App\Models\SmsMessage::where('request_key', $entry->sms_request_key)->first();
                $entry->update(['dispatch_status' => $message ? 'completed' : 'blocked', 'sms_message_id' => $message?->id,
                    'dispatch_note' => $message ? 'SMS attempt recorded. Check delivery status; do not resend automatically.'
                        : ($exception instanceof ValidationException ? collect($exception->errors())->flatten()->first() : 'Outreach stopped. Review current access, invitation and caregiver status before another attempt.')]);
            }
        });
    }

    private function assertDraft(CaregiverRoster $roster): void
    {
        $this->ensure($roster->status === 'ready' && ! $roster->approved_at, 'Only a completed, unapproved draft can be reviewed.');
        $this->ensure($roster->generated_at && $roster->generated_at->gt(now()->subHours(config('care_roster.draft_hours'))), 'This roster is more than 24 hours old. Generate a fresh roster.');
        $this->ensure($roster->request_fingerprint === $this->evidence->requestFingerprint($roster->careRequest), 'The request changed. Generate a fresh roster.');
    }

    private function assertAction(CaregiverRosterEntry $entry, array $state, bool $sms): void
    {
        $this->ensure(! $entry->assessment['avoid_contact'] && $entry->assessment['fit'] !== 'unsuitable', 'This caregiver should not be contacted from this roster.');
        $this->ensure(! $state['blocked_reason'], $state['blocked_reason'] ?? 'Caregiver unavailable.');
        $this->ensure($state['action'] === $entry->action && $entry->action !== 'review_only', 'The invitation/application changed. Review the caregiver response instead.');
        $this->ensure($entry->action !== 'follow_up' || $sms, 'An existing invitation needs no new invite. Select SMS to follow up.');
        $this->ensure(! $sms || $state['sms_allowed'], $state['sms_note'] ?? 'SMS is unavailable.');
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['roster' => $message]);
        }
    }
}
