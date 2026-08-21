<?php

namespace App\Services\AiSupport;

use App\Exceptions\Payments\PaymentException;
use App\Models\AiSupportGuidedTask;
use App\Models\CareBooking;
use App\Models\CarePlan;
use App\Models\CareRecipientProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Support\CareRequestProgress;
use App\Support\FamilyActionInboxBuilder;
use Illuminate\Support\Collection;

class FamilyGuidedAssistanceService
{
    public const INTENT_OVERVIEW = 'family_overview';

    public const INTENT_REQUESTS = 'family_requests';

    public const INTENT_VISITS = 'family_visits';

    public const INTENT_TIMESHEETS = 'family_timesheets';

    public const INTENT_PAYMENT_ATTENTION = 'family_payment_attention';

    public const INTENT_PROFILES = 'family_care_profiles';

    public const INTENT_MESSAGES = 'family_messages';

    public const INTENT_HISTORY = 'family_care_history';

    public const INTENT_REGULAR_CARE = 'family_regular_care';

    public function __construct(
        private readonly FamilyAccountContext $familyAccounts,
        private readonly FamilyActionInboxBuilder $actions,
        private readonly FamilyPaymentMethodStatusReader $paymentStatus,
        private readonly FamilyPaymentTimeStateReader $paymentTime,
        private readonly NavigationTargetRegistry $navigation,
        private readonly AiSupportGuidedTaskService $guidedTasks,
    ) {}

    public function intentFor(string $message): ?string
    {
        $message = mb_strtolower(trim($message));

        if (preg_match('/\b(?:inbox|unread|reply|conversation)\b|\b(?:message|messages)\b.{0,24}\b(?:caregiver|inbox|unread|read|reply|send|sent)\b|\b(?:open|show|check|read|reply\s+to|send)\b.{0,24}\bmessages?\b/iu', $message)) {
            return self::INTENT_MESSAGES;
        }

        if (preg_match('/\b(?:care\s+history|visit\s+history|past\s+visits?|previous\s+visits?|billing\s+history|payment\s+history|receipts?|past\s+charges?|previous\b.{0,32}\bcharges?|refunded|refund(?:ed)?\s+(?:status|amount)|authorized\s+amount|captured\s+amount|net\s+(?:paid|charge))\b/iu', $message)) {
            return self::INTENT_HISTORY;
        }

        if (preg_match('/\b(?:care\s*(?:receiver|recipient)\s+profiles?|care\s+profiles?|recipient\s+profiles?|receiver\s+profiles?)\b/iu', $message)) {
            return self::INTENT_PROFILES;
        }

        if (preg_match('/\b(?:payment|charge|authorization)\b.{0,32}\b(?:error|fail|failed|failure|declined|problem|issue|attention|required|pending)\b|\b(?:error|fail|failed|declined)\b.{0,24}\b(?:payment|charge|authorization)\b|\bpending\s+charge\b/iu', $message)) {
            return self::INTENT_PAYMENT_ATTENTION;
        }

        if (preg_match('/\b(?:timesheet|time\s*sheet|submitted\s+hours?|worked\s+hours?|approve\s+(?:the\s+)?hours?|review\s+(?:the\s+)?hours?|reported\s+hours?|time\s+correction)\b|\bcaregiver\b.{0,24}\b(?:submit|submitted)\b.{0,24}\b(?:hours?|time|duration|start|end)\b|\b(?:hours?|time|duration|start|end)\b.{0,40}\bcaregiver\b.{0,24}\b(?:submit|submitted)\b/iu', $message)) {
            return self::INTENT_TIMESHEETS;
        }

        if (preg_match('/\b(?:corrected\s+hours?|completed\s+extra\s+visit|extra\s+visit\s+report)\b/iu', $message)) {
            return self::INTENT_TIMESHEETS;
        }

        if (preg_match('/\b(?:next|upcoming)\b.{0,28}\b(?:regular|weekly|recurring)\s+care\b|\b(?:when|where|show|open|view|check|manage|review|do\s+i\s+have)\b.{0,36}\b(?:regular|weekly|recurring)\s+care(?:\s+(?:plan|schedule|visit))?\b|\b(?:regular|weekly|recurring)\s+care\s+(?:plan|schedule|status)\b/iu', $message)) {
            return self::INTENT_REGULAR_CARE;
        }

        if (preg_match('/\b(?:next|upcoming|current|today(?:\'s)?|scheduled|live)\b.{0,28}\b(?:visit|caregiver)\b|\b(?:visit|caregiver)\b.{0,28}\b(?:next|upcoming|current|today|scheduled|coming|arriving|status|happening)\b|\b(?:accept|approve|reject|decline|review|open|show)\b.{0,40}\b(?:visit\s+change|change\s+request|reschedule|cancellation)\b|\bcaregiver\b.{0,24}\b(?:change\s+request|reschedule|cancellation)\b|\bcare\s+scheduled\b/iu', $message)) {
            return self::INTENT_VISITS;
        }

        if (preg_match('/\b(?:applicant|applicants|application|applications|caregiver\s+responses?|caregivers?\s+(?:apply|applied|interested|respond|responded)|caregivers?\b.{0,12}\bapplied|caregivers\b.{0,24}\bwaiting\b|waiting\b.{0,24}\bcaregivers)\b/iu', $message)) {
            return self::INTENT_REQUESTS;
        }

        if (preg_match('/\b(?:request\s+status|status\s+of\s+(?:my\s+)?(?:care\s+)?request|open\s+(?:care\s+)?requests?|show\s+(?:my\s+)?(?:care\s+)?requests?|care\s+request\s+stand)\b/iu', $message)) {
            return self::INTENT_REQUESTS;
        }

        // Account-wide wording is intentionally evaluated after specific domains so a
        // question such as "why did my payment fail and what should I do?" stays about
        // that exact payment instead of being widened to the whole-account overview.
        if (preg_match('/\b(?:what|anything|everything|account)\b.{0,45}\b(?:needs?\s+(?:my\s+)?attention|need\s+to\s+do|pending|okay|ok|action)\b|\bwhat\s+should\s+i\s+do\b|\bcheck\s+(?:my\s+)?account\b/iu', $message)) {
            return self::INTENT_OVERVIEW;
        }

        return null;
    }

    public function respond(User $actor, SupportTicket $ticket, string $intent, ?string $stableIntentId = null): void
    {
        $account = $this->familyAccounts->account($actor);
        $actionItems = $this->actions->buildForAccount($account);

        [$message, $guides, $resultCode] = match ($intent) {
            self::INTENT_OVERVIEW => $this->overview($actor, $actionItems),
            self::INTENT_REQUESTS => in_array($stableIntentId, ['FAM-REQUEST-035', 'FAM-MATCH-013', 'FAM-MATCH-014'], true)
                ? $this->applicants($actionItems)
                : $this->requests($actor, $actionItems),
            self::INTENT_VISITS => $this->visits($actor, $actionItems),
            self::INTENT_TIMESHEETS => $this->timesheets($actor, $actionItems),
            self::INTENT_PAYMENT_ATTENTION => $this->paymentAttention($actor, $actionItems),
            self::INTENT_PROFILES => $this->profiles($actor),
            self::INTENT_MESSAGES => $this->messages($actor),
            self::INTENT_HISTORY => $this->history($actor, $stableIntentId),
            self::INTENT_REGULAR_CARE => $this->regularCare($actor),
            default => throw new \InvalidArgumentException('Unsupported Family assistance intent.'),
        };

        $guides = collect($guides)
            ->filter(fn (array $guide): bool => $this->navigation->allowedFor($actor, $guide['target_id'], [
                'resource_type' => $guide['resource_type'] ?? null,
                'resource_id' => $guide['resource_id'] ?? null,
            ]))
            ->unique(fn (array $guide): string => implode(':', [
                $guide['target_id'],
                $guide['resource_type'] ?? '',
                $guide['resource_id'] ?? '',
            ]))
            ->take(6)
            ->values()
            ->all();

        $this->guidedTasks->offerFamilyReadResult(
            $actor,
            $ticket,
            $message,
            $stableIntentId ?? $intent,
            $resultCode,
            $guides,
            $intent,
        );
    }

    /** @return array{string,list<array<string,mixed>>,string} */
    private function overview(User $actor, Collection $actionItems): array
    {
        $items = collect();
        try {
            $payment = $this->paymentStatus->read($actor);
        } catch (PaymentException) {
            $payment = [
                'can_manage' => $this->familyAccounts->isOwner($actor),
                'attention' => 'unavailable',
                'ready' => false,
                'card' => null,
            ];
        }
        if ($payment['can_manage'] && $payment['attention'] !== 'ready') {
            $summary = match ($payment['attention']) {
                'missing' => 'Add a payment method to the Family account.',
                'expired' => 'The saved payment method ending in '.$payment['card']['last4'].' is expired.',
                'expiring_soon' => 'The saved payment method ending in '.$payment['card']['last4'].' expires soon.',
                default => 'The saved payment method could not be verified right now.',
            };
            $items->push([
                'priority' => 5,
                'summary' => $summary,
                'guide' => $this->guide(
                    AiSupportGuidedTask::TYPE_PAYMENT_METHOD,
                    'family.billing.payment_method',
                    $payment['ready'] ? 'Update payment method' : 'Add payment method',
                ),
            ]);
        }

        foreach ($actionItems as $item) {
            $items->push([
                'priority' => (int) $item['priority'],
                'summary' => $this->actionSummary($item),
                'guide' => $this->guideForAction($item),
            ]);
        }

        $draftProfiles = $this->profilesFor($actor)
            ->where('status', CareRecipientProfile::STATUS_DRAFT);
        foreach ($draftProfiles as $profile) {
            $missing = $this->profileMissingInformation($profile);
            $items->push([
                'priority' => 35,
                'summary' => 'Finish '.$profile->displayName().'\'s care receiver profile ('.$missing['label'].').',
                'guide' => $this->guide(
                    AiSupportGuidedTask::TYPE_FAMILY_CARE_PROFILE,
                    $missing['target_id'],
                    'Finish '.$profile->displayName().'\'s profile',
                    'care_profile',
                    (int) $profile->id,
                ),
            ]);
        }

        $unread = $this->unreadConversations($actor);
        foreach ($unread as $conversation) {
            $items->push([
                'priority' => 40,
                'summary' => 'A message from '.$this->caregiverName($conversation).' is unread.',
                'guide' => $this->guide(
                    AiSupportGuidedTask::TYPE_FAMILY_MESSAGE,
                    'family.message',
                    'Read '.$this->caregiverName($conversation).'\'s message',
                    'conversation',
                    (int) $conversation->id,
                ),
            ]);
        }

        $items = $items->sortBy('priority')->values();
        if ($items->isEmpty()) {
            $next = $this->nextRelevantBooking($actor);
            $message = 'I checked your care requests, visits, submitted hours, care receiver profiles, messages, and care-payment actions. Nothing in those supported areas needs your attention right now.';
            $guides = [];
            if ($next) {
                $message .= ' Your next visit is '.$this->bookingTime($next).'.';
                $guides[] = $this->guideForBooking($next, 'Open next visit');
            }

            return [$message, $guides, 'clear'];
        }

        $visible = $items->take(6);
        $lines = $visible->values()->map(
            fn (array $item, int $index): string => ($index + 1).'. '.$item['summary'],
        )->implode("\n");
        $message = 'I checked your care requests, visits, submitted hours, care receiver profiles, messages, and care-payment actions. I found '.$items->count().' item'.($items->count() === 1 ? ' that needs' : 's that need').' attention:'."\n".$lines;
        if ($items->count() > $visible->count()) {
            $message .= "\n".'I am showing the first six. Ask me to check again after you finish one.';
        }

        return [$message, $visible->pluck('guide')->all(), 'attention_found'];
    }

    /** @return array{string,list<array<string,mixed>>,string} */
    private function requests(User $actor, Collection $actionItems): array
    {
        $requestActions = $actionItems->whereIn('type', ['applicants'])->values();
        if ($requestActions->isNotEmpty()) {
            $lines = $requestActions->take(4)->map(fn (array $item): string => '- '.$this->actionSummary($item))->implode("\n");

            return [
                'Your open care requests have caregiver responses waiting for review:'."\n".$lines,
                $requestActions->take(4)->map(fn (array $item): array => $this->guideForAction($item))->all(),
                'applicants_waiting',
            ];
        }

        $request = $this->requestsFor($actor)->first();
        if (! $request) {
            return [
                'You do not have an existing care request yet. The Care page is where your requests will appear.',
                [$this->guide(AiSupportGuidedTask::TYPE_FAMILY_REQUEST, 'family.care_requests', 'Open Care')],
                'no_request',
            ];
        }

        $lifecycle = CareRequestProgress::familyLifecycleStage($request);
        $target = $this->requestTarget($request);
        $pending = $this->pendingApplicants($request);
        $message = $request->title.': '.$this->sentence((string) $lifecycle['title']).' '.ltrim((string) $lifecycle['body']);
        if ($pending > 0) {
            $message .= ' '.$pending.' caregiver'.($pending === 1 ? ' is' : 's are').' waiting for review.';
        }

        return [
            $message,
            [$this->guide(
                AiSupportGuidedTask::TYPE_FAMILY_REQUEST,
                $target,
                $target === 'family.request.applicants' ? 'Review caregivers' : 'Open request',
                'care_request',
                (int) $request->id,
            )],
            (string) $lifecycle['key'],
        ];
    }

    /** @return array{string,list<array<string,mixed>>,string} */
    private function applicants(Collection $actionItems): array
    {
        $waiting = $actionItems->where('type', 'applicants')->values();
        if ($waiting->isEmpty()) {
            return [
                'No caregivers are waiting for you to review or hire right now.',
                [$this->guide(AiSupportGuidedTask::TYPE_FAMILY_REQUEST, 'family.care_requests', 'Open care requests')],
                'no_applicants_waiting',
            ];
        }

        $lines = $waiting->take(6)->map(fn (array $item): string => '- '.$this->actionSummary($item))->implode("\n");

        return [
            'You have '.$waiting->count().' caregiver review item'.($waiting->count() === 1 ? '' : 's').' waiting:'."\n".$lines,
            $waiting->take(6)->map(fn (array $item): array => $this->guideForAction($item))->all(),
            'applicants_waiting',
        ];
    }

    /** @return array{string,list<array<string,mixed>>,string} */
    private function visits(User $actor, Collection $actionItems): array
    {
        $issue = $actionItems->firstWhere('type', 'visit_change');
        if ($issue) {
            return [
                $this->actionSummary($issue).' The current schedule stays in place until you decide.',
                [$this->guideForAction($issue)],
                'visit_change_pending',
            ];
        }

        $live = $actionItems->firstWhere('type', 'live_visit');
        if ($live) {
            return [
                $this->actionSummary($live).' Open it for the current visit details and help options.',
                [$this->guideForAction($live)],
                'visit_live',
            ];
        }

        $booking = $this->nextRelevantBooking($actor);
        if (! $booking) {
            return [
                'I did not find a current or upcoming visit on your Family account.',
                [$this->guide(AiSupportGuidedTask::TYPE_FAMILY_VISIT, 'family.care_requests', 'Open Care')],
                'no_upcoming_visit',
            ];
        }

        $caregiver = trim((string) $booking->caregiver?->name) ?: 'Your caregiver';
        $status = str_replace('_', ' ', (string) $booking->status);

        return [
            $caregiver.' has a '.strtolower($status).' visit '.$this->bookingTime($booking).'.',
            [$this->guideForBooking($booking, 'Open visit')],
            'visit_'.$booking->status,
        ];
    }

    /** @return array{string,list<array<string,mixed>>,string} */
    private function timesheets(User $actor, Collection $actionItems): array
    {
        $waiting = $actionItems->whereIn('type', ['time_correction', 'timesheet', 'completed_extra_visit'])->values();
        if ($waiting->count() > 1) {
            $lines = $waiting->take(6)->map(fn (array $item): string => '- '.$this->actionSummary($item))->implode("\n");

            return [
                'You have '.$waiting->count().' submitted-hours review items waiting:'."\n".$lines,
                $waiting->take(6)->map(fn (array $item): array => $this->guideForAction($item))->all(),
                'hours_need_attention',
            ];
        }

        $state = $this->paymentTime->latestSubmittedHours($actor);
        if ($state) {
            $message = $state['caregiver_name'].' submitted hours for the visit: '.$this->duration((int) $state['worked_minutes']).' for '.$state['subject'].'.';
            if ($state['started_at'] && $state['completed_at']) {
                $message .= ' Recorded time: '.$this->clockTime($state['started_at']).' to '.$this->clockTime($state['completed_at']).'.';
            }
            if ((int) $state['expected_minutes'] > 0 && (int) $state['difference_minutes'] !== 0) {
                $difference = abs((int) $state['difference_minutes']);
                $message .= ' That is '.$this->duration($difference).' '.((int) $state['difference_minutes'] > 0 ? 'longer' : 'shorter').' than scheduled.';
            }
            $tasks = collect((array) $state['tasks']);
            if ($tasks->isNotEmpty()) {
                $completed = $tasks->where('completed', true)->count();
                $message .= ' Tasks marked complete: '.$completed.' of '.$tasks->count().'.';
                $labels = $tasks->take(3)->map(fn (array $task): string => $task['label'].($task['completed'] ? ' — done' : ' — not marked done'))->implode('; ');
                if ($labels !== '') {
                    $message .= ' '.$labels.'.';
                }
            }
            if ($state['correction']) {
                $correction = $state['correction'];
                $message .= ' Latest correction: '.$correction['status_label'].'. Proposed time is '.$this->duration((int) $correction['proposed_worked_minutes']);
                if ((int) $correction['difference_minutes'] !== 0) {
                    $message .= ', '.$this->duration(abs((int) $correction['difference_minutes'])).((int) $correction['difference_minutes'] > 0 ? ' more' : ' less').' than the original';
                }
                if ((int) $correction['family_charge_cents'] > 0) {
                    $message .= ', with a Family charge of '.$this->money((int) $correction['family_charge_cents']);
                }
                $message .= '.';
            }
            $message .= $state['family_confirmed']
                ? ' These hours were already confirmed.'
                : ' Review the exact record before approving or requesting a change.';
            $target = $state['target'];

            return [
                $message,
                [$this->guide(
                    AiSupportGuidedTask::TYPE_FAMILY_TIMESHEET,
                    $target['target_id'],
                    $target['label'],
                    $target['resource_type'],
                    (int) $target['resource_id'],
                    ($target['target_id'] === 'family.request.payment_attention'
                        || (string) data_get($state, 'correction.status') === 'payment_action_required')
                            ? 'family_payment_attention_v1'
                            : null,
                )],
                $state['correction'] ? 'correction_read' : ($state['family_confirmed'] ? 'hours_confirmed' : 'hours_need_attention'),
            ];
        }

        $item = $actionItems->first(fn (array $candidate): bool => in_array(
            $candidate['type'],
            ['time_correction', 'timesheet', 'completed_extra_visit'],
            true,
        ));
        $item ??= $actionItems->firstWhere('type', 'payment');
        if ($item) {
            $instruction = $item['type'] === 'payment'
                ? 'Open the exact care page to recover the payment before approving or retrying anything.'
                : 'Review the exact submitted hours in the app before approving or requesting help.';

            return [
                $this->sentence($this->actionSummary($item)).' '.$instruction,
                [$this->guideForAction($item)],
                'hours_need_attention',
            ];
        }

        $booking = CareBooking::query()
            ->forFamilyAccount($this->familyAccounts->account($actor))
            ->with(['careRequest:id,title,is_system_generated', 'caregiver:id,name'])
            ->whereNotNull('timesheet_submitted_at')
            ->latest('timesheet_submitted_at')
            ->first();
        if (! $booking) {
            return [
                'I did not find submitted caregiver hours waiting for your review.',
                [$this->guide(AiSupportGuidedTask::TYPE_FAMILY_HISTORY, 'family.care_history', 'Open care history')],
                'no_submitted_hours',
            ];
        }

        $minutes = max(0, (int) $booking->worked_minutes);
        $hours = intdiv($minutes, 60).'h '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT).'m';
        $confirmed = $booking->family_confirmed_at !== null;
        $message = ($booking->caregiver?->name ?: 'The caregiver').' submitted '.$hours.' for '.$booking->careRequest?->title.'. ';
        $message .= $confirmed ? 'Those hours were already confirmed.' : 'Those hours still need your review.';

        return [
            $message,
            [$this->guideForBooking($booking, $confirmed ? 'Open visit record' : 'Review hours', ! $confirmed)],
            $confirmed ? 'hours_confirmed' : 'hours_need_attention',
        ];
    }

    /** @return array{string,list<array<string,mixed>>,string} */
    private function paymentAttention(User $actor, Collection $actionItems): array
    {
        $state = $this->paymentTime->latestPaymentAttention($actor);
        if ($state) {
            $amounts = (array) $state['amounts'];
            $amount = match (true) {
                (int) $amounts['additional_pending_cents'] > 0 => ' The additional amount needing attention is '.$this->money((int) $amounts['additional_pending_cents']).'.',
                (int) $amounts['authorized_cents'] > 0 => ' The authorized amount is '.$this->money((int) $amounts['authorized_cents']).'.',
                (int) $amounts['captured_cents'] > 0 => ' The captured amount is '.$this->money((int) $amounts['captured_cents']).'.',
                default => '',
            };
            $target = $state['target'];

            return [
                $state['subject'].': This care payment needs attention. '.$state['reason'].$amount.' '.$state['recovery'],
                [$this->guide(
                    AiSupportGuidedTask::TYPE_FAMILY_TIMESHEET,
                    $target['target_id'],
                    $target['label'],
                    $target['resource_type'],
                    (int) $target['resource_id'],
                    'family_payment_attention_v1',
                )],
                'payment_'.$state['reason_code'],
            ];
        }

        $items = $actionItems->filter(fn (array $item): bool => $item['type'] === 'payment'
            || $item['navigation_target_id'] === 'family.request.payment_attention'
            || str_contains(mb_strtolower((string) $item['title']), 'payment'))
            ->values();
        if ($items->isEmpty()) {
            return [
                'I did not find a care visit or regular-care payment that currently requires Family action.',
                [$this->guide(AiSupportGuidedTask::TYPE_FAMILY_HISTORY, 'family.care_history', 'Review payment history')],
                'no_payment_attention',
            ];
        }

        $lines = $items->take(4)->map(fn (array $item): string => '- '.$this->actionSummary($item))->implode("\n");

        return [
            'I found '.count($items).' care payment'.(count($items) === 1 ? '' : 's').' that need attention:'."\n".$lines."\n".'The exact care page will show the safe recovery action.',
            $items->take(4)->map(fn (array $item): array => $this->guideForAction($item))->all(),
            'payment_attention_found',
        ];
    }

    /** @return array{string,list<array<string,mixed>>,string} */
    private function profiles(User $actor): array
    {
        $profiles = $this->profilesFor($actor)->where('status', '!=', CareRecipientProfile::STATUS_ARCHIVED)->values();
        if ($profiles->isEmpty()) {
            return [
                'There is no active care receiver profile on your Family account yet. You can create one now and save it as a draft if you need more time.',
                [$this->guide(AiSupportGuidedTask::TYPE_FAMILY_CARE_PROFILE, 'family.care_profile.create', 'Create care receiver profile')],
                'no_profile',
            ];
        }

        $drafts = $profiles->where('status', CareRecipientProfile::STATUS_DRAFT)->values();
        if ($drafts->isNotEmpty()) {
            $profile = $drafts->first();
            $count = $drafts->count();
            $missing = $this->profileMissingInformation($profile);

            return [
                $count.' care receiver profile'.($count === 1 ? ' is' : 's are').' still saved as a draft. '.$profile->displayName().'\'s profile still needs '.$missing['label'].' before it is ready to attach to new care.',
                [$this->guide(
                    AiSupportGuidedTask::TYPE_FAMILY_CARE_PROFILE,
                    $missing['target_id'],
                    'Finish '.$profile->displayName().'\'s profile',
                    'care_profile',
                    (int) $profile->id,
                )],
                'profile_draft',
            ];
        }

        return [
            'Your '.$profiles->count().' active care receiver profile'.($profiles->count() === 1 ? ' is' : 's are').' ready to use.',
            [$this->guide(AiSupportGuidedTask::TYPE_FAMILY_CARE_PROFILE, 'family.care_profiles', 'Open care receiver profiles')],
            'profiles_ready',
        ];
    }

    /** @return array{string,list<array<string,mixed>>,string} */
    private function messages(User $actor): array
    {
        $unread = $this->unreadConversations($actor);
        if ($unread->isNotEmpty()) {
            $conversation = $unread->first();

            return [
                'You have '.$unread->count().' unread conversation'.($unread->count() === 1 ? '' : 's').'. The newest is from '.$this->caregiverName($conversation).' about '.$conversation->careRequest?->title.'.',
                [$this->guide(
                    AiSupportGuidedTask::TYPE_FAMILY_MESSAGE,
                    'family.message',
                    'Read newest message',
                    'conversation',
                    (int) $conversation->id,
                )],
                'unread_messages',
            ];
        }

        $conversation = $this->conversationsFor($actor)->first();
        if ($conversation) {
            return [
                'You have no unread conversations. Your most recent conversation is with '.$this->caregiverName($conversation).'.',
                [$this->guide(
                    AiSupportGuidedTask::TYPE_FAMILY_MESSAGE,
                    'family.message',
                    'Open recent conversation',
                    'conversation',
                    (int) $conversation->id,
                )],
                'messages_read',
            ];
        }

        return [
            'You do not have any caregiver conversations yet.',
            [$this->guide(AiSupportGuidedTask::TYPE_FAMILY_MESSAGE, 'family.messages', 'Open messages')],
            'no_messages',
        ];
    }

    /** @return array{string,list<array<string,mixed>>,string} */
    private function history(User $actor, ?string $stableIntentId = null): array
    {
        if (in_array($stableIntentId, ['FAM-PAY-020', 'FAM-PAY-023', 'FAM-PAY-026'], true)) {
            $payment = $this->paymentTime->latestPaymentRecord($actor);
            if (! $payment) {
                return [
                    'I did not find a Family-visible care payment record yet.',
                    [$this->guide(AiSupportGuidedTask::TYPE_FAMILY_HISTORY, 'family.care_history', 'Open care history')],
                    'payment_history_empty',
                ];
            }
            $message = $payment['subject'].': payment status is '.str_replace('_', ' ', $payment['status']).'. '
                .'Authorized '.$this->money((int) $payment['authorized_cents']).'; captured '.$this->money((int) $payment['captured_cents'])
                .'; refunded '.$this->money((int) $payment['refunded_cents']).'; net paid '.$this->money((int) $payment['net_paid_cents']).'.';
            if ($stableIntentId === 'FAM-PAY-026') {
                $message .= ' Open Care history for the exact visit and receipt information currently available.';
            }

            return [
                $message,
                [$this->guide(AiSupportGuidedTask::TYPE_FAMILY_HISTORY, 'family.care_history', 'Open care history')],
                'payment_history_read',
            ];
        }

        $query = CareBooking::query()
            ->forFamilyAccount($this->familyAccounts->account($actor))
            ->whereIn('status', [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED]);
        $count = (clone $query)->count();
        $latest = $query->with(['careRequest:id,title', 'caregiver:id,name'])->latest('completed_at')->first();

        $message = $count === 0
            ? 'Your care history does not have a completed visit yet.'
            : 'Your care history has '.$count.' completed visit'.($count === 1 ? '' : 's').'. The latest is '.($latest?->careRequest?->title ?: 'a care visit').' with '.($latest?->caregiver?->name ?: 'your caregiver').'.';

        return [
            $message.' Care history shows visit records, submitted hours, and payment status together.',
            [$this->guide(AiSupportGuidedTask::TYPE_FAMILY_HISTORY, 'family.care_history', 'Open care history')],
            $count === 0 ? 'history_empty' : 'history_available',
        ];
    }

    /** @return array{string,list<array<string,mixed>>,string} */
    private function regularCare(User $actor): array
    {
        $plans = CarePlan::query()
            ->forFamilyAccount($this->familyAccounts->account($actor))
            ->with(['caregiver:id,name', 'nextBooking:id,care_plan_id,status,scheduled_start_at,scheduled_end_at'])
            ->orderByRaw("CASE WHEN status IN ('active', 'payment_attention', 'paused', 'pending_caregiver', 'countered') THEN 0 ELSE 1 END")
            ->latest('updated_at')
            ->get();

        if ($plans->isEmpty()) {
            return [
                'You do not have a regular care plan yet. After you complete and approve a visit, the Regular care page shows caregivers you can book on a repeating schedule.',
                [$this->guide(AiSupportGuidedTask::TYPE_FAMILY_VISIT, 'family.regular_care', 'Open regular care')],
                'regular_care_empty',
            ];
        }

        $plan = $plans->first();
        $booking = $plans
            ->pluck('nextBooking')
            ->filter()
            ->filter(fn (CareBooking $candidate): bool => in_array($candidate->status, [
                CareBooking::STATUS_IN_PROGRESS,
                CareBooking::STATUS_PAUSED,
            ], true) || ($candidate->status === CareBooking::STATUS_SCHEDULED
                && $candidate->scheduled_start_at?->gte(now()->subHours(2))))
            ->sortBy('scheduled_start_at')
            ->first();
        if ($booking) {
            $plan = $plans->firstWhere('id', $booking->care_plan_id) ?: $plan;
            $caregiver = trim((string) $plan->caregiver?->name) ?: 'Your caregiver';

            return [
                $caregiver.' has your next regular care visit '.$this->bookingTime($booking).'.',
                [$this->guide(
                    AiSupportGuidedTask::TYPE_FAMILY_VISIT,
                    'family.regular_care.attention',
                    'Open regular care plan',
                    'care_plan',
                    (int) $plan->id,
                )],
                'regular_care_upcoming',
            ];
        }

        $status = str_replace('_', ' ', (string) $plan->status);

        return [
            $plan->title.' is '.$status.'. I did not find an upcoming regular care visit on this plan right now.',
            [$this->guide(
                AiSupportGuidedTask::TYPE_FAMILY_VISIT,
                'family.regular_care.attention',
                'Open regular care plan',
                'care_plan',
                (int) $plan->id,
            )],
            'regular_care_'.$plan->status,
        ];
    }

    private function requestsFor(User $actor): Collection
    {
        return CareRequest::query()
            ->forFamilyAccount($this->familyAccounts->account($actor))
            ->where('is_system_generated', false)
            ->with([
                'applications.caregiver:id,name',
                'booking.payment',
                'booking.caregiver:id,name',
                'invitations:id,care_request_id',
            ])
            ->withCount('applications')
            ->orderByRaw("CASE WHEN status IN ('open', 'filled') THEN 0 ELSE 1 END")
            ->latest('updated_at')
            ->limit(20)
            ->get();
    }

    private function profilesFor(User $actor): Collection
    {
        return CareRecipientProfile::query()
            ->forFamilyAccount($this->familyAccounts->account($actor))
            ->orderByRaw("CASE WHEN status = 'draft' THEN 0 WHEN status = 'ready' THEN 1 ELSE 2 END")
            ->orderBy('preferred_name')
            ->get();
    }

    private function conversationsFor(User $actor): Collection
    {
        return CareRequestConversation::query()
            ->forUser($actor)
            ->with([
                'careRequest:id,title',
                'caregiver:id,name',
                'familyReads' => fn ($query) => $query->where('user_id', $actor->id),
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();
    }

    private function unreadConversations(User $actor): Collection
    {
        return $this->conversationsFor($actor)
            ->filter(function (CareRequestConversation $conversation) use ($actor): bool {
                $readAt = $conversation->lastReadAtFor($actor);

                return $conversation->last_message_at
                    && (int) $conversation->last_message_sender_id !== (int) $actor->id
                    && (! $readAt || $conversation->last_message_at->gt($readAt));
            })
            ->values();
    }

    private function nextRelevantBooking(User $actor): ?CareBooking
    {
        $query = CareBooking::query()
            ->forFamilyAccount($this->familyAccounts->account($actor))
            ->with(['careRequest:id,title,is_system_generated', 'carePlan:id,title', 'caregiver:id,name']);

        $live = (clone $query)
            ->whereIn('status', [CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED])
            ->orderBy('scheduled_start_at')
            ->first();

        return $live ?: $query
            ->where('status', CareBooking::STATUS_SCHEDULED)
            ->where('scheduled_start_at', '>=', now()->subHours(2))
            ->orderBy('scheduled_start_at')
            ->first();
    }

    private function requestTarget(CareRequest $request): string
    {
        if ($request->booking?->payment?->requiresFamilyAction()) {
            return 'family.request.payment_attention';
        }
        if ($this->pendingApplicants($request) > 0) {
            return 'family.request.applicants';
        }
        if ($request->booking && in_array($request->booking->status, [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED], true) && ! $request->booking->family_confirmed_at) {
            return 'family.request.timesheet';
        }
        if ($request->booking) {
            return 'family.request.visit';
        }

        return 'family.request.overview';
    }

    private function pendingApplicants(CareRequest $request): int
    {
        return $request->applications->whereIn('status', [
            CareRequestApplication::STATUS_APPLIED,
            CareRequestApplication::STATUS_SHORTLISTED,
        ])->count();
    }

    /** @param array<string,mixed> $item */
    private function actionSummary(array $item): string
    {
        $summary = trim((string) $item['title']);
        $subject = trim((string) ($item['subject'] ?? ''));

        return $subject !== '' ? $summary.' — '.$subject : $summary;
    }

    private function sentence(string $value): string
    {
        $value = trim($value);

        return preg_match('/[.!?]\z/u', $value) === 1 ? $value : $value.'.';
    }

    /** @param array<string,mixed> $item @return array<string,mixed> */
    private function guideForAction(array $item): array
    {
        $taskType = match ((string) $item['type']) {
            'applicants' => AiSupportGuidedTask::TYPE_FAMILY_REQUEST,
            'live_visit', 'visit_change' => AiSupportGuidedTask::TYPE_FAMILY_VISIT,
            'time_correction', 'timesheet', 'completed_extra_visit', 'payment' => AiSupportGuidedTask::TYPE_FAMILY_TIMESHEET,
            default => AiSupportGuidedTask::TYPE_FAMILY_REQUEST,
        };

        $targetId = (string) $item['navigation_target_id'];
        $isPaymentAction = (string) $item['type'] === 'payment'
            || $targetId === 'family.request.payment_attention'
            || ($targetId === 'family.regular_care.attention'
                && str_contains(mb_strtolower((string) ($item['title'] ?? '')), 'payment'));

        return $this->guide(
            $taskType,
            $targetId,
            (string) $item['label'],
            $item['resource_type'] ?? null,
            isset($item['resource_id']) ? (int) $item['resource_id'] : null,
            $isPaymentAction ? 'family_payment_attention_v1' : null,
        );
    }

    /** @return array<string,mixed> */
    private function guideForBooking(CareBooking $booking, string $label, bool $timesheet = false): array
    {
        if ($booking->care_request_id && ! $booking->careRequest?->is_system_generated) {
            return $this->guide(
                $timesheet ? AiSupportGuidedTask::TYPE_FAMILY_TIMESHEET : AiSupportGuidedTask::TYPE_FAMILY_VISIT,
                $timesheet ? 'family.request.timesheet' : 'family.request.visit',
                $label,
                'care_request',
                (int) $booking->care_request_id,
            );
        }

        return $this->guide(
            AiSupportGuidedTask::TYPE_FAMILY_VISIT,
            'family.regular_care.attention',
            $label,
            'care_plan',
            (int) $booking->care_plan_id,
        );
    }

    /** @return array<string,mixed> */
    private function guide(
        string $taskType,
        string $targetId,
        string $label,
        ?string $resourceType = null,
        ?int $resourceId = null,
        ?string $verifierId = null,
    ): array {
        return [
            'task_type' => $taskType,
            'target_id' => $targetId,
            'label' => $label,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'verifier_id' => $verifierId,
        ];
    }

    private function bookingTime(CareBooking $booking): string
    {
        if (! $booking->scheduled_start_at) {
            return 'with the time still being finalized';
        }

        return 'on '.$booking->scheduled_start_at->format('l, F j').' at '.$booking->scheduled_start_at->format('g:i A');
    }

    private function duration(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return collect([
            $hours > 0 ? $hours.' hr'.($hours === 1 ? '' : 's') : null,
            $remaining > 0 ? $remaining.' min' : null,
        ])->filter()->implode(' ') ?: '0 min';
    }

    private function clockTime(string $isoTime): string
    {
        return \Illuminate\Support\Carbon::parse($isoTime)->timezone('America/New_York')->format('g:i A');
    }

    private function money(int $cents): string
    {
        return '$'.number_format(max(0, $cents) / 100, 2);
    }

    private function caregiverName(CareRequestConversation $conversation): string
    {
        return trim((string) $conversation->caregiver?->name) ?: 'a caregiver';
    }

    /** @return array{label:string,target_id:string} */
    private function profileMissingInformation(CareRecipientProfile $profile): array
    {
        if (trim((string) $profile->preferred_name) === '') {
            return ['label' => 'a preferred name', 'target_id' => 'family.care_profile.identity'];
        }

        if ($profile->include_additional_contact && trim((string) $profile->additional_contact_name) === '') {
            return ['label' => 'the additional contact name', 'target_id' => 'family.care_profile.contact'];
        }

        if (! $profile->sharing_acknowledged_at) {
            return ['label' => 'the sharing review and confirmation', 'target_id' => 'family.care_profile.review'];
        }

        return ['label' => 'the final review and save', 'target_id' => 'family.care_profile.review'];
    }
}
