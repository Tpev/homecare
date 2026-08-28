<?php

namespace App\Services\AiSupport;

use App\Models\AiSupportConfirmedActionEvidence;
use App\Models\AiSupportGuidedTask;
use App\Models\AiSupportMessageAction;
use App\Models\CaregiverProfile;
use App\Models\CarePlan;
use App\Models\ContinuousCoveragePlan;
use App\Models\ContinuousCoverageShift;
use App\Models\FamilyAccountInvitation;
use App\Models\FamilyAccountMember;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Services\Family\FamilyCareHistoryService;
use App\Services\FamilyAccounts\FamilyAccountAccessService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\FamilyAccounts\FamilyAccountInvitationService;
use App\Support\MarketplaceNotificationPresentation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FamilyAdministrationActionService
{
    public const CAPABILITY = 'family_administration_v1';

    private const TOOL_PREFIXES = ['account.', 'family-access.', 'notification.'];

    private const HUMAN_ACCOUNT = [11, 14, 16, 19, 20];

    private const HUMAN_ACCESS = [16, 17, 18];

    private const HUMAN_SUPPORT = [2, 3, 4, 7, 8, 9, 10, 11, 12, 15, 18, 19, 20];

    public function __construct(
        private readonly FamilyAccountContext $familyAccounts,
        private readonly FamilyAccountInvitationService $invitations,
        private readonly FamilyAccountAccessService $access,
        private readonly FamilyCareHistoryService $history,
        private readonly AiSupportEligibilityService $eligibility,
        private readonly AiSupportActionEvidenceService $evidence,
        private readonly AiSupportEventRecorder $events,
        private readonly AiSupportGuidedTaskService $guidedTasks,
        private readonly AiSupportHandoffService $handoff,
        private readonly NavigationTargetRegistry $navigation,
        private readonly FamilyCareOperationsActionService $careOperations,
    ) {}

    /** @param array<string,mixed> $record */
    public function respond(User $actor, SupportTicket $ticket, array $record, string $message): bool
    {
        $intentId = (string) ($record['intent_id'] ?? '');
        if (! preg_match('/^FAM-(ACCOUNT|ACCESS|COMMS|HISTORY|COVERAGE|SUPPORT)-([0-9]{3})$/', $intentId, $matches)) {
            return false;
        }
        if (! $this->eligibility->evaluate($actor, self::CAPABILITY, $ticket)->allowed) {
            return false;
        }

        return match ($matches[1]) {
            'ACCOUNT' => $this->respondAccount($actor, $ticket, (int) $matches[2], $intentId, $message),
            'ACCESS' => $this->respondAccess($actor, $ticket, (int) $matches[2], $intentId, $message),
            'COMMS' => $this->respondCommunications($actor, $ticket, (int) $matches[2], $intentId, $message),
            'HISTORY' => $this->respondHistory($actor, $ticket, (int) $matches[2], $intentId, $message),
            'COVERAGE' => $this->respondCoverage($actor, $ticket, $intentId),
            'SUPPORT' => $this->respondSupport($actor, $ticket, (int) $matches[2], $intentId),
        };
    }

    public function ownsTool(string $tool): bool
    {
        return collect(self::TOOL_PREFIXES)->contains(fn (string $prefix): bool => str_starts_with($tool, $prefix));
    }

    public function confirm(User $actor, SupportTicket $ticket, string $actionId): AiSupportConfirmedActionEvidence
    {
        $action = $this->domainAction($actor, $ticket, $actionId);
        $payload = (array) $action->payload;
        $tool = (string) ($payload['tool_id'] ?? '');
        if (! $this->ownsTool($tool)) {
            throw new AuthorizationException;
        }
        if ($action->consumed_at) {
            return AiSupportConfirmedActionEvidence::query()
                ->where('idempotency_key', (string) ($payload['idempotency_key'] ?? ''))
                ->where('actor_user_id', $actor->id)->firstOrFail();
        }
        if (! $action->isActive()) {
            throw ValidationException::withMessages(['confirmation' => 'This confirmation expired or changed. Review a fresh recap and confirm again.']);
        }

        $confirmed = $this->evidence->commitConfirmedAction(
            $actor,
            (string) $payload['confirmation_reference'],
            (string) $payload['idempotency_key'],
            (string) $payload['confirmation_action'],
            fn (array $preview): array => $this->commit($actor, $tool, $preview),
        );

        DB::transaction(function () use ($actor, $ticket, $action, $payload, $confirmed): void {
            $locked = AiSupportMessageAction::query()->lockForUpdate()->findOrFail($action->id);
            if ($locked->consumed_at) {
                return;
            }
            $locked->forceFill([
                'payload' => ['idempotency_key' => $confirmed->idempotency_key, 'tool_id' => $confirmed->tool_id,
                    'confirmed_action_evidence_id' => $confirmed->id],
                'consumed_at' => now(),
            ])->save();
            $message = $this->automatedMessage($ticket, $this->receiptText($confirmed));
            AiSupportMessageAction::query()->create([
                'id' => (string) Str::uuid(), 'support_ticket_message_id' => $message->id,
                'support_ticket_id' => $ticket->id, 'actor_user_id' => $actor->id,
                'action_type' => AiSupportMessageAction::TYPE_DOMAIN_RECEIPT,
                'payload' => ['title' => 'Done and checked', 'receipt' => $confirmed->receipt_reference,
                    'url' => $this->receiptUrl($confirmed), 'label' => $this->receiptLabel($confirmed)],
            ]);
            $this->events->record($ticket, 'intent_completed', [
                'support_ticket_message_id' => $message->id, 'capability_id' => self::CAPABILITY,
                'tool_id' => $confirmed->tool_id, 'tool_version' => $confirmed->tool_version,
                'result_code' => 'authoritative_receipt_created',
                'safe_metadata' => ['intent_id' => (string) ($payload['intent_id'] ?? '')],
            ], $actor);
        }, 3);

        return $confirmed;
    }

    public function renew(User $actor, SupportTicket $ticket, string $actionId): AiSupportMessageAction
    {
        $action = $this->domainAction($actor, $ticket, $actionId, false);
        if ($action->consumed_at) {
            throw ValidationException::withMessages(['confirmation' => 'This action was already completed.']);
        }
        $payload = (array) $action->payload;
        $tool = (string) ($payload['tool_id'] ?? '');
        if (! $this->ownsTool($tool)) {
            throw new AuthorizationException;
        }
        $preview = $this->refreshPreview($actor, $tool, (array) ($payload['renew_payload'] ?? []));

        return $this->issueAction($actor, $ticket, $tool, $preview, (string) ($payload['intent_id'] ?? ''),
            (string) ($payload['title'] ?? 'Review action'), (string) ($payload['summary'] ?? 'Review the current information before confirming.'),
            array_values((array) ($payload['fields'] ?? [])), (string) ($payload['confirm_label'] ?? 'Confirm'));
    }

    private function respondAccount(User $actor, SupportTicket $ticket, int $number, string $intentId, string $message): bool
    {
        if (in_array($number, self::HUMAN_ACCOUNT, true)) {
            $this->handoff->transfer($actor, $ticket, 'family_account_'.$number);

            return true;
        }
        if ($number === 7) {
            $name = $this->nameFromMessage($message);
            if (! $name) {
                $this->offerRead($actor, $ticket, 'Your account name is '.$actor->name.'. Tell me the new name, for example: change my name to Mary Smith.', $intentId, 'account.profile');

                return true;
            }
            $this->issueAction($actor, $ticket, 'account.name.update', [
                'name' => $name, 'expected_name' => (string) $actor->name,
                'expected_updated_at' => $this->stamp($actor->updated_at),
            ], $intentId, 'Change your account name?', 'This changes the name shown on your own LoLo account. It does not change a care receiver profile.', [
                ['label' => 'Current name', 'value' => (string) $actor->name], ['label' => 'New name', 'value' => $name],
            ], 'Confirm name change');

            return true;
        }
        if ($number === 10) {
            if ($actor->hasVerifiedEmail()) {
                $this->offerRead($actor, $ticket, 'Your email is already verified.', $intentId, 'account.profile');

                return true;
            }
            $this->issueAction($actor, $ticket, 'account.verification.resend', [
                'expected_email' => (string) $actor->email, 'expected_verified' => false,
            ], $intentId, 'Send a new verification email?', 'The message goes to your current account email. LoLo will record that it was sent, but cannot promise inbox delivery.', [
                ['label' => 'Account email', 'value' => $this->maskEmail((string) $actor->email)],
            ], 'Confirm and send email');

            return true;
        }

        $body = match ($number) {
            1, 2 => 'Registration and sign-in use LoLo’s secure authentication pages. This signed-in chat cannot restore access for someone who cannot sign in, and it never asks for a password.',
            3 => 'To sign out, use the account menu and choose Sign out. The assistant does not end the browser session from chat.',
            4, 5 => 'Forgotten passwords use the secure reset page. If a link expired or was already used, request a fresh reset and use only the newest email.',
            6 => 'Password changes use LoLo’s secure Account Settings form. Passwords and reset tokens never belong in chat.',
            8 => 'Email changes use the secure Account Settings page and may require re-verification. The assistant does not collect verification codes in chat.',
            9 => 'Email verification confirms that you control the login address. Sending a verification email is not the same as completing verification.',
            12, 13 => 'Open Account Settings for personal name, login email, password, and personal controls. Family access manages shared members; Care receiver profiles manage care information.',
            15 => 'Deleting a login does not silently erase required care, payment, support, or Family records. Shared dependencies and retention requirements still apply.',
            17 => 'LoLo does not currently expose a released self-service phone-number workflow for this Family account.',
            18 => 'LoLo does not currently offer a released authenticator-app or SMS multi-factor setting in this Family account flow.',
            default => 'Open Account Settings to review this account detail.',
        };
        $this->offerRead($actor, $ticket, $body, $intentId, 'account.profile');

        return true;
    }

    private function respondAccess(User $actor, SupportTicket $ticket, int $number, string $intentId, string $message): bool
    {
        if (in_array($number, self::HUMAN_ACCESS, true)) {
            $this->handoff->transfer($actor, $ticket, 'family_access_'.$number);

            return true;
        }
        $membership = $this->familyAccounts->membership($actor);
        $account = $membership->familyAccount;
        if ($number <= 3 || in_array($number, [5, 14, 15, 19, 20], true)) {
            $members = $account->activeMemberships()->with('user:id,name,email')->get();
            $invites = $account->invitations()->whereNull('accepted_at')->whereNull('canceled_at')->where('expires_at', '>', now())->get();
            $body = 'This Family Account has '.$members->count().' active '.Str::plural('member', $members->count()).': '
                .$members->map(fn (FamilyAccountMember $row): string => $row->user->name.' ('.($row->isOwner() ? 'owner' : 'member').')')->implode(', ').'.';
            if ($membership->isOwner()) {
                $body .= ' '.$invites->count().' invitation'.($invites->count() === 1 ? ' is' : 's are').' pending.';
            }
            $body .= ' Active members share Family care records; only the owner can manage membership and owner-only billing/account matters.';
            if ($number === 20) {
                $body = 'LoLo does not currently support per-member restrictions for selected recipients, payments, or conversations. No restriction was applied.';
            } elseif ($number === 19) {
                $body = 'Ended access removes future visibility into that Family Account. Private records or reasons from an account you no longer belong to are not exposed in chat.';
            }
            $this->offerRead($actor, $ticket, $body, $intentId, $number === 14 ? 'family.billing.payment_method' : 'family.access');

            return true;
        }
        if ($number === 4) {
            $email = $this->emailFromMessage($message);
            if (! $membership->isOwner() || ! $email) {
                $this->offerRead($actor, $ticket, $membership->isOwner()
                    ? 'Tell me the email address to invite. I will show a recap before sending.'
                    : 'Only the Family Account owner can invite another member.', $intentId, 'family.access');

                return true;
            }
            $this->issueAction($actor, $ticket, 'family-access.invite', [
                'email' => $email, 'family_account_id' => (int) $account->id,
                'expected_account_updated_at' => $this->stamp($account->updated_at),
            ], $intentId, 'Invite this person to the Family Account?', 'They will be able to see shared Family care records after accepting. The invitation expires after seven days.', [
                ['label' => 'Email', 'value' => $this->maskEmail($email)], ['label' => 'Access', 'value' => 'Family member'],
            ], 'Confirm and send invitation');

            return true;
        }
        if (in_array($number, [6, 7, 8], true)) {
            $invitation = $this->selectInvitation($actor, $message, $number === 7);
            if (! $membership->isOwner() || ! $invitation) {
                $this->offerRead($actor, $ticket, 'I could not identify one active invitation owned by this Family Account. Open Family access to review invitations.', $intentId, 'family.access');

                return true;
            }
            $tool = in_array($number, [6, 7], true) ? 'family-access.invitation.resend' : 'family-access.invitation.cancel';
            $this->issueAction($actor, $ticket, $tool, [
                'invitation_id' => (int) $invitation->id, 'expected_updated_at' => $this->stamp($invitation->updated_at),
                'expected_usable' => $invitation->isUsable(),
            ], $intentId, in_array($number, [6, 7], true) ? 'Resend this Family invitation?' : 'Cancel this Family invitation?',
                in_array($number, [6, 7], true) ? 'A new link will replace the earlier link and will expire after seven days.' : 'The invitation link will stop working. Existing members are unchanged.', [
                    ['label' => 'Invited email', 'value' => $this->maskEmail($invitation->email_normalized)],
                ], in_array($number, [6, 7], true) ? 'Confirm and resend' : 'Confirm cancellation');

            return true;
        }
        if ($number === 12) {
            $member = $this->selectMember($actor, $message);
            if (! $membership->isOwner() || ! $member || $member->isOwner()) {
                $this->offerRead($actor, $ticket, 'Tell me which non-owner Family member to remove, or open Family access. Ownership transfer needs LoLo Support.', $intentId, 'family.access');

                return true;
            }
            $this->issueAction($actor, $ticket, 'family-access.member.remove', [
                'member_id' => (int) $member->id, 'expected_status' => (string) $member->status,
                'expected_updated_at' => $this->stamp($member->updated_at),
            ], $intentId, 'Remove this Family member?', 'They will immediately lose access to shared Family care records. Historical activity remains recorded.', [
                ['label' => 'Member', 'value' => (string) $member->user->name],
            ], 'Confirm removal');

            return true;
        }
        if ($number === 13) {
            if ($membership->isOwner()) {
                $this->handoff->transfer($actor, $ticket, 'family_ownership_transfer_required');

                return true;
            }
            $this->issueAction($actor, $ticket, 'family-access.leave', [
                'member_id' => (int) $membership->id, 'expected_status' => (string) $membership->status,
                'expected_updated_at' => $this->stamp($membership->updated_at),
            ], $intentId, 'Leave this Family Account?', 'You will immediately lose access to its shared care records. This does not delete the account.', [
                ['label' => 'Account owner', 'value' => (string) $account->owner->name],
            ], 'Confirm leaving account');

            return true;
        }

        $this->offerRead($actor, $ticket, 'Open Family access to review current members and invitations. Invitation acceptance itself happens through the private emailed link.', $intentId, 'family.access');

        return true;
    }

    private function respondCommunications(User $actor, SupportTicket $ticket, int $number, string $intentId, string $message): bool
    {
        if ($number === 3) {
            return $this->careOperations->respond($actor, $ticket, [...$this->recordFor('FAM-MATCH-018'), 'intent_id' => 'FAM-MATCH-018'], $message);
        }
        if ($number <= 4) {
            $this->offerRead($actor, $ticket, 'Your caregiver conversations are in Messages. The assistant can open an authorized conversation, but never claims that a message was read.', $intentId, 'family.messages');

            return true;
        }
        if ($number === 5) {
            $this->handoff->transfer($actor, $ticket, 'unsafe_messaging');

            return true;
        }
        if (in_array($number, [6, 7, 8, 11, 12, 14], true)) {
            $notifications = $actor->notifications()->latest()->limit(8)->get();
            $unread = $actor->unreadNotifications()->count();
            $body = $notifications->isEmpty()
                ? 'You do not have any notifications yet.'
                : 'You have '.$unread.' unread. Latest: '.$notifications->take(3)->map(fn (DatabaseNotification $row): string => (string) data_get($row->data, 'title', MarketplaceNotificationPresentation::label((string) data_get($row->data, 'event_key', 'generic')))
                    .' ('.($row->read_at ? 'read' : 'unread').')')->implode('; ').'.';
            $this->offerRead($actor, $ticket, $body, $intentId, 'family.notifications');

            return true;
        }
        if ($number === 9) {
            $notification = $this->selectNotification($actor, $message);
            if (! $notification) {
                $this->offerRead($actor, $ticket, 'I could not identify one of your notifications. Open Notifications to choose it.', $intentId, 'family.notifications');

                return true;
            }
            if (! $this->available($actor, $ticket, 'notification.mark-read')) {
                $this->offerRead($actor, $ticket, 'Open Notifications to mark this item read.', $intentId, 'family.notifications');

                return true;
            }
            if (! $notification->read_at) {
                $notification->markAsRead();
            }
            $this->automatedMessage($ticket, 'That notification is now marked read.');
            $this->events->record($ticket, 'intent_completed', ['capability_id' => self::CAPABILITY,
                'tool_id' => 'notification.mark-read', 'tool_version' => 'v1', 'result_code' => 'notification_marked_read'], $actor);

            return true;
        }
        if ($number === 10) {
            $count = $actor->unreadNotifications()->count();
            if ($count === 0) {
                $this->offerRead($actor, $ticket, 'You have no unread notifications.', $intentId, 'family.notifications');

                return true;
            }
            $this->issueAction($actor, $ticket, 'notification.mark-all-read', ['expected_unread_count' => $count],
                $intentId, 'Mark every notification read?', 'This changes only notification read state. It does not accept, approve, pay, or cancel anything.', [
                    ['label' => 'Unread notifications', 'value' => (string) $count],
                ], 'Confirm mark all read');

            return true;
        }
        if (in_array($number, [13, 15], true)) {
            if ($this->isNotificationPreferencesLocationQuestion($message)) {
                $this->offerRead(
                    $actor,
                    $ticket,
                    'Open Notifications and use Delivery preferences to change your personal email and in-app settings.',
                    $intentId,
                    'family.notifications.preferences',
                );

                return true;
            }
            $channels = $this->channelsFromMessage($message, $number);
            $eventKey = $this->eventKeyFromMessage($message);
            $events = $eventKey ? [$eventKey] : MarketplaceNotificationPresentation::eventsForRole('family');
            $this->issueAction($actor, $ticket, 'notification.preferences.update', [
                'event_keys' => $events, 'email_enabled' => $channels['email'], 'in_app_enabled' => $channels['in_app'],
                'expected_hash' => $this->preferenceHash($actor, $events),
            ], $intentId, 'Save these notification preferences?', 'This changes email and in-app delivery only. SMS and push controls are not released.', [
                ['label' => 'Events', 'value' => $eventKey ? MarketplaceNotificationPresentation::label($eventKey) : 'All Family notifications'],
                ['label' => 'Email', 'value' => $channels['email'] ? 'On' : 'Off'],
                ['label' => 'In-app', 'value' => $channels['in_app'] ? 'On' : 'Off'],
            ], 'Confirm preferences');

            return true;
        }

        $this->offerRead($actor, $ticket, $number === 17
            ? 'LoLo does not currently support user-created AI reminders. Existing visit reminders follow platform notification settings.'
            : ($number === 16
                ? 'Family notification email follows your login email. Change it only through secure Account Settings and complete any required re-verification.'
                : 'If an expected message is missing, check the event preference and current notification record. LoLo does not expose provider secrets or claim delivery without a recorded result.'),
            $intentId, $number === 16 ? 'account.profile' : 'family.notifications');

        return true;
    }

    private function respondHistory(User $actor, SupportTicket $ticket, int $number, string $intentId, string $message): bool
    {
        if (in_array($number, [10, 15], true)) {
            $this->handoff->transfer($actor, $ticket, 'care_history_correction');

            return true;
        }
        if ($number === 11) {
            return $this->careOperations->respond($actor, $ticket, [...$this->recordFor('FAM-VISIT-032'), 'intent_id' => 'FAM-VISIT-032'], $message);
        }
        if ($number === 12) {
            return $this->careOperations->respond($actor, $ticket, [...$this->recordFor('FAM-REGULAR-002'), 'intent_id' => 'FAM-REGULAR-002'], $message);
        }
        if ($number === 13) {
            return false;
        }
        if ($number === 14) {
            $this->offerRead($actor, $ticket, 'LoLo does not currently generate an official Care History export. You can open Care History and use your browser’s print feature for a local copy.', $intentId, 'family.care_history');

            return true;
        }
        $filters = [];
        if (preg_match('/(?:booking|visit|#)\s*#?(\d+)/i', $message, $match) === 1) {
            $filters['search'] = $match[1];
        }
        $query = $this->history->query($actor, $filters);
        $booking = $query->first();
        $summary = $this->history->summary($actor, $filters);
        if (! $booking) {
            $this->offerRead($actor, $ticket, 'I did not find an authorized past-care record matching that request.', $intentId, 'family.care_history');

            return true;
        }
        $row = $this->history->present($booking);
        if ($number === 8) {
            $profile = $booking->caregiver?->caregiverProfile;
            if ($profile) {
                $this->offerRead(
                    $actor,
                    $ticket,
                    'I found the current public caregiver profile linked to booking #'.$row['booking_id'].'. Availability still needs to be confirmed for any new care.',
                    $intentId,
                    'family.caregiver_profile',
                    $profile,
                );

                return true;
            }
            $this->offerRead($actor, $ticket, 'That historical caregiver no longer has a public profile I can open. You can browse current caregiver profiles instead.', $intentId, 'family.caregivers');

            return true;
        }
        if ($number === 9) {
            if ($booking->carePlan) {
                $this->offerRead(
                    $actor,
                    $ticket,
                    'I found the recurring-care journey linked to booking #'.$row['booking_id'].'.',
                    $intentId,
                    'family.recurring_care.journey',
                    $booking->carePlan,
                );

                return true;
            }
            $this->offerRead($actor, $ticket, 'This historical visit is not linked to a recurring-care plan.', $intentId, 'family.care_history');

            return true;
        }
        $money = collect($summary['money'])->map(fn (array $amount): string => $amount['net_billed_label'].' net billed and '.$amount['refunded_label'].' refunded')->implode('; ');
        $body = in_array($number, [1, 2, 3, 4], true)
            ? 'Care History has '.$summary['care_provided'].' care record'.($summary['care_provided'] === 1 ? '' : 's').', '.$this->minutesLabel((int) $summary['worked_minutes']).', '.$money.'.'
            : 'Booking #'.$row['booking_id'].' with '.$row['caregiver_name'].' for '.$row['recipient_name'].' was '.$row['visit_status_label'].'. Worked time: '.($row['worked_label'] ?: 'not recorded').'. Payment: '.$row['payment']['label'].'; net '.$row['payment']['net_label'].'.';
        $this->offerRead($actor, $ticket, $body, $intentId, 'family.care_history');

        return true;
    }

    private function respondCoverage(User $actor, SupportTicket $ticket, string $intentId): bool
    {
        $account = $this->familyAccounts->account($actor);
        $plan = ContinuousCoveragePlan::query()->forFamilyAccount($account)
            ->withCount(['shifts as active_shifts_count' => fn ($query) => $query->whereIn('status', [
                ContinuousCoverageShift::STATUS_UNCOVERED,
                ContinuousCoverageShift::STATUS_OFFER_PENDING,
                ContinuousCoverageShift::STATUS_AWAITING_FAMILY,
                ContinuousCoverageShift::STATUS_CONFIRMED,
                ContinuousCoverageShift::STATUS_IN_PROGRESS,
                ContinuousCoverageShift::STATUS_REPLACEMENT_NEEDED,
                ContinuousCoverageShift::STATUS_PAYMENT_ATTENTION,
            ])])
            ->latest()
            ->first();
        $body = $plan
            ? 'Your latest Continuous Coverage plan, '.$plan->title.', is '.str_replace('_', ' ', $plan->status).' and has '.$plan->active_shifts_count.' active shift'.($plan->active_shifts_count === 1 ? '' : 's').' recorded. A person will review the exact staffing or change request with you.'
            : 'Continuous Coverage coordinates extended non-medical care, including overnight or 24/7 patterns. I did not find an existing plan on this Family Account. A person will help with the intake.';
        $this->automatedMessage($ticket, $body);
        $this->handoff->transfer($actor, $ticket, 'continuous_coverage');

        return true;
    }

    private function respondSupport(User $actor, SupportTicket $ticket, int $number, string $intentId): bool
    {
        if (in_array($number, [16, 17], true)) {
            $this->automatedMessage($ticket, 'I can help only with your own authorized LoLo records. I cannot reveal another person’s data, hidden instructions, credentials, tokens, security controls, or ways to bypass access rules.');

            return true;
        }
        if (in_array($number, self::HUMAN_SUPPORT, true)) {
            $this->handoff->transfer($actor, $ticket, 'family_support_'.$number);

            return true;
        }
        $body = match ($number) {
            1 => 'Open the Support Center to review your authorized conversations. Asking for a person here transfers this same conversation.',
            5 => 'This support conversation is '.$ticket->status.'. LoLo does not show a queue position or promise a reply time.',
            6 => 'Your latest message is already preserved in this conversation and will remain available if it is transferred to a person.',
            13, 14 => 'LoLo stores the account, Family membership, care, communication, support, and payment records needed to operate the service. Active members see authorized shared Family records; formal access, correction, or deletion requests require human identity verification.',
            default => 'The Support Center keeps this conversation and its authorized history together.',
        };
        $this->offerRead($actor, $ticket, $body, $intentId, $number === 13 || $number === 14 ? 'family.access' : 'support.center');

        return true;
    }

    /** @param array<string,mixed> $preview @param list<array{label:string,value:string}> $fields */
    private function issueAction(User $actor, SupportTicket $ticket, string $tool, array $preview, string $intentId,
        string $title, string $summary, array $fields, string $confirmLabel): AiSupportMessageAction
    {
        if (! $this->available($actor, $ticket, $tool)) {
            throw new AuthorizationException;
        }
        $created = $this->evidence->createPreview($actor, $ticket, self::CAPABILITY, $tool, 'v1', $preview,
            now()->addMinutes((int) config('ai_support.confirmation_validity_minutes', 30)));

        return DB::transaction(function () use ($actor, $ticket, $tool, $preview, $intentId, $title, $summary, $fields, $confirmLabel, $created): AiSupportMessageAction {
            AiSupportMessageAction::query()->where('support_ticket_id', $ticket->id)->where('actor_user_id', $actor->id)
                ->where('action_type', AiSupportMessageAction::TYPE_DOMAIN_RECAP)->whereNull('consumed_at')->whereNull('invalidated_at')
                ->update(['invalidated_at' => now(), 'invalidation_reason' => 'superseded_domain_recap']);
            $message = $this->automatedMessage($ticket, 'Please review this recap. Nothing changes until you press the confirmation button.');
            $action = AiSupportMessageAction::query()->create([
                'id' => (string) Str::uuid(), 'support_ticket_message_id' => $message->id,
                'support_ticket_id' => $ticket->id, 'actor_user_id' => $actor->id,
                'action_type' => AiSupportMessageAction::TYPE_DOMAIN_RECAP,
                'payload' => ['tool_id' => $tool, 'intent_id' => $intentId, 'title' => $title, 'summary' => $summary,
                    'fields' => $fields, 'confirm_label' => $confirmLabel, 'confirmation_reference' => $created['confirmation_reference'],
                    'idempotency_key' => (string) Str::uuid(), 'confirmation_action' => str_replace(['.', '-'], '_', $tool),
                    'renew_payload' => $preview],
                'expires_at' => now()->addMinutes((int) config('ai_support.confirmation_validity_minutes', 30)),
            ]);
            $this->events->record($ticket, 'intent_action_offered', ['support_ticket_message_id' => $message->id,
                'capability_id' => self::CAPABILITY, 'tool_id' => $tool, 'tool_version' => 'v1',
                'result_code' => 'explicit_confirmation_issued', 'safe_metadata' => ['intent_id' => $intentId]], $actor);

            return $action;
        }, 3);
    }

    /** @param array<string,mixed> $preview @return array<string,mixed> */
    private function refreshPreview(User $actor, string $tool, array $preview): array
    {
        if ($tool === 'account.name.update') {
            $fresh = $actor->fresh();
            $preview['expected_name'] = (string) $fresh->name;
            $preview['expected_updated_at'] = $this->stamp($fresh->updated_at);
        } elseif ($tool === 'account.verification.resend') {
            $preview['expected_email'] = (string) $actor->fresh()->email;
            $preview['expected_verified'] = $actor->fresh()->hasVerifiedEmail();
        } elseif (isset($preview['invitation_id'])) {
            $invitation = $this->ownedInvitation($actor, (int) $preview['invitation_id']);
            $preview['expected_updated_at'] = $this->stamp($invitation->updated_at);
            $preview['expected_usable'] = $invitation->isUsable();
        } elseif (isset($preview['member_id'])) {
            $member = $this->ownedMember($actor, (int) $preview['member_id'], $tool === 'family-access.leave');
            $preview['expected_status'] = (string) $member->status;
            $preview['expected_updated_at'] = $this->stamp($member->updated_at);
        } elseif ($tool === 'notification.mark-all-read') {
            $preview['expected_unread_count'] = $actor->unreadNotifications()->count();
        } elseif ($tool === 'notification.preferences.update') {
            $preview['expected_hash'] = $this->preferenceHash($actor, (array) $preview['event_keys']);
        }

        return $preview;
    }

    /** @param array<string,mixed> $preview @return array{outcome_code:string,domain_reference_type:string,domain_reference_id:string,receipt_reference:string} */
    private function commit(User $actor, string $tool, array $preview): array
    {
        return match ($tool) {
            'account.name.update' => $this->commitName($actor, $preview),
            'account.verification.resend' => $this->commitVerification($actor, $preview),
            'family-access.invite' => $this->commitInvite($actor, $preview),
            'family-access.invitation.resend' => $this->commitResend($actor, $preview),
            'family-access.invitation.cancel' => $this->commitCancelInvitation($actor, $preview),
            'family-access.member.remove' => $this->commitRemoveMember($actor, $preview),
            'family-access.leave' => $this->commitLeave($actor, $preview),
            'notification.mark-all-read' => $this->commitMarkAllRead($actor, $preview),
            'notification.preferences.update' => $this->commitPreferences($actor, $preview),
            default => throw new AuthorizationException,
        };
    }

    private function commitName(User $actor, array $preview): array
    {
        $locked = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
        if (! hash_equals((string) $locked->name, (string) ($preview['expected_name'] ?? ''))) {
            throw ValidationException::withMessages(['confirmation' => 'Your account changed. Review the new name again.']);
        }
        $this->assertUpdated($locked, (string) ($preview['expected_updated_at'] ?? ''), 'Your account changed. Review the new name again.');
        $name = trim((string) ($preview['name'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['name' => 'Enter a name between 2 and 120 characters.']);
        }
        $locked->forceFill(['name' => $name])->save();

        return $this->receipt('account_name_updated_verified', 'user', (int) $locked->id, 'account-'.$locked->id.'-name-updated');
    }

    private function commitVerification(User $actor, array $preview): array
    {
        $fresh = $actor->fresh();
        if (! hash_equals((string) $fresh->email, (string) ($preview['expected_email'] ?? '')) || $fresh->hasVerifiedEmail() !== (bool) ($preview['expected_verified'] ?? false)) {
            throw ValidationException::withMessages(['confirmation' => 'Your email verification state changed. Review it again.']);
        }
        if ($fresh->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['confirmation' => 'Your email is already verified.']);
        }
        $fresh->sendEmailVerificationNotification();

        return $this->receipt('verification_email_requested_verified', 'user', (int) $fresh->id, 'account-'.$fresh->id.'-verification-requested');
    }

    private function commitInvite(User $actor, array $preview): array
    {
        $account = $this->familyAccounts->account($actor);
        if ((int) $account->id !== (int) ($preview['family_account_id'] ?? 0)) {
            throw new AuthorizationException;
        }
        $this->assertUpdated($account, (string) ($preview['expected_account_updated_at'] ?? ''), 'The Family Account changed. Review the invitation again.');
        $result = $this->invitations->send($actor, (string) $preview['email']);

        return $this->receipt($result['delivered'] ? 'family_invitation_sent_verified' : 'family_invitation_created_delivery_unconfirmed',
            'family_account_invitation', (int) $result['invitation']->id, 'family-invitation-'.$result['invitation']->id.'-sent');
    }

    private function commitResend(User $actor, array $preview): array
    {
        $invitation = $this->ownedInvitation($actor, (int) ($preview['invitation_id'] ?? 0), true);
        $this->assertInvitation($invitation, $preview);
        $result = $this->invitations->resend($actor, $invitation);

        return $this->receipt($result['delivered'] ? 'family_invitation_resent_verified' : 'family_invitation_renewed_delivery_unconfirmed',
            'family_account_invitation', (int) $result['invitation']->id, 'family-invitation-'.$result['invitation']->id.'-resent');
    }

    private function commitCancelInvitation(User $actor, array $preview): array
    {
        $invitation = $this->ownedInvitation($actor, (int) ($preview['invitation_id'] ?? 0), true);
        $this->assertInvitation($invitation, $preview);
        $cancelled = $this->invitations->cancel($actor, $invitation);

        return $this->receipt('family_invitation_cancelled_verified', 'family_account_invitation', (int) $cancelled->id, 'family-invitation-'.$cancelled->id.'-cancelled');
    }

    private function commitRemoveMember(User $actor, array $preview): array
    {
        $member = $this->ownedMember($actor, (int) ($preview['member_id'] ?? 0), false, true);
        $this->assertMember($member, $preview);
        $this->access->remove($actor, $member);

        return $this->receipt('family_member_removed_verified', 'family_account_member', (int) $member->id, 'family-member-'.$member->id.'-removed');
    }

    private function commitLeave(User $actor, array $preview): array
    {
        $member = $this->ownedMember($actor, (int) ($preview['member_id'] ?? 0), true, true);
        $this->assertMember($member, $preview);
        $this->access->leave($actor);

        return $this->receipt('family_member_left_verified', 'family_account_member', (int) $member->id, 'family-member-'.$member->id.'-left');
    }

    private function commitMarkAllRead(User $actor, array $preview): array
    {
        $count = $actor->unreadNotifications()->count();
        if ($count !== (int) ($preview['expected_unread_count'] ?? -1)) {
            throw ValidationException::withMessages(['confirmation' => 'Your unread notifications changed. Review a fresh count and confirm again.']);
        }
        $actor->unreadNotifications()->update(['read_at' => now()]);

        return $this->receipt('notifications_marked_read_verified', 'user_notification', (int) $actor->id, 'notifications-'.$actor->id.'-all-read');
    }

    private function commitPreferences(User $actor, array $preview): array
    {
        $events = array_values(array_intersect((array) ($preview['event_keys'] ?? []), MarketplaceNotificationPresentation::eventsForRole('family')));
        if ($events === [] || ! hash_equals((string) ($preview['expected_hash'] ?? ''), $this->preferenceHash($actor, $events))) {
            throw ValidationException::withMessages(['confirmation' => 'Notification preferences changed. Review a fresh recap and confirm again.']);
        }
        foreach ($events as $eventKey) {
            UserNotificationPreference::query()->updateOrCreate(['user_id' => $actor->id, 'event_key' => $eventKey], [
                'in_app_enabled' => (bool) ($preview['in_app_enabled'] ?? true),
                'email_enabled' => (bool) ($preview['email_enabled'] ?? true),
                'sms_enabled' => false, 'push_enabled' => false,
            ]);
        }

        return $this->receipt('notification_preferences_updated_verified', 'user_notification_preference', (int) $actor->id, 'notification-preferences-'.$actor->id.'-updated');
    }

    private function selectInvitation(User $actor, string $message, bool $includeExpired = false): ?FamilyAccountInvitation
    {
        $query = $this->familyAccounts->account($actor)->invitations()
            ->whereNull('accepted_at')
            ->whereNull('canceled_at')
            ->when(! $includeExpired, fn ($builder) => $builder->where('expires_at', '>', now()));
        if ($email = $this->emailFromMessage($message)) {
            return $query->where('email_normalized', $email)->first();
        }
        $rows = $query->get();

        return $rows->count() === 1 ? $rows->first() : null;
    }

    private function selectMember(User $actor, string $message): ?FamilyAccountMember
    {
        $rows = $this->familyAccounts->account($actor)->activeMemberships()->with('user')->get();
        $needle = mb_strtolower($message);
        $match = $rows->first(fn (FamilyAccountMember $row): bool => str_contains($needle, mb_strtolower((string) $row->user->email))
            || str_contains($needle, mb_strtolower((string) $row->user->name)));

        return $match ?: ($rows->where('access_level', FamilyAccountMember::ACCESS_MEMBER)->count() === 1 ? $rows->where('access_level', FamilyAccountMember::ACCESS_MEMBER)->first() : null);
    }

    private function selectNotification(User $actor, string $message): ?DatabaseNotification
    {
        if (preg_match('/\b([0-9a-f]{8}-[0-9a-f-]{27,})\b/i', $message, $match) === 1) {
            return $actor->notifications()->whereKey($match[1])->first();
        }
        $rows = $actor->notifications()->latest()->limit(10)->get();

        return $rows->count() === 1 || str_contains(mb_strtolower($message), 'latest') ? $rows->first() : null;
    }

    private function ownedInvitation(User $actor, int $id, bool $lock = false): FamilyAccountInvitation
    {
        return $this->familyAccounts->account($actor)->invitations()->when($lock, fn ($query) => $query->lockForUpdate())->findOrFail($id);
    }

    private function ownedMember(User $actor, int $id, bool $selfAllowed = false, bool $lock = false): FamilyAccountMember
    {
        $member = $this->familyAccounts->account($actor)->memberships()
            ->with('user')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->findOrFail($id);
        if (! $selfAllowed && $member->isOwner()) {
            throw new AuthorizationException;
        }

        return $member;
    }

    private function assertInvitation(FamilyAccountInvitation $invitation, array $preview): void
    {
        if (! hash_equals($this->stamp($invitation->updated_at), (string) ($preview['expected_updated_at'] ?? ''))
            || $invitation->isUsable() !== (bool) ($preview['expected_usable'] ?? false)) {
            throw ValidationException::withMessages(['confirmation' => 'This invitation changed. Review it again.']);
        }
    }

    private function assertMember(FamilyAccountMember $member, array $preview): void
    {
        if (! hash_equals((string) $member->status, (string) ($preview['expected_status'] ?? ''))
            || ! hash_equals($this->stamp($member->updated_at), (string) ($preview['expected_updated_at'] ?? ''))) {
            throw ValidationException::withMessages(['confirmation' => 'This Family membership changed. Review it again.']);
        }
    }

    private function assertUpdated(mixed $model, string $expected, string $message): void
    {
        if (! hash_equals($this->stamp($model->updated_at), $expected)) {
            throw ValidationException::withMessages(['confirmation' => $message]);
        }
    }

    private function offerRead(User $actor, SupportTicket $ticket, string $body, string $intentId, string $targetId, mixed $resource = null): void
    {
        $resourceType = match (true) {
            $resource instanceof CarePlan => 'care_plan',
            $resource instanceof CaregiverProfile => 'caregiver_profile',
            default => null,
        };
        $resourceContext = ['resource_type' => $resourceType, 'resource_id' => $resource?->id];
        $guides = [];
        if ($this->navigation->allowedFor($actor, $targetId, $resourceContext)) {
            $definition = $this->navigation->definition($targetId) ?? [];
            $guides[] = ['task_type' => str_contains($targetId, 'history') ? AiSupportGuidedTask::TYPE_FAMILY_HISTORY : AiSupportGuidedTask::TYPE_FAMILY_REQUEST,
                'target_id' => $targetId, 'resource_type' => $resourceType, 'resource_id' => $resource?->id,
                'label' => (string) ($definition['label'] ?? 'Open the right page'),
                'verifier_id' => 'authoritative_family_state_v1'];
        }
        $this->guidedTasks->offerFamilyReadResult($actor, $ticket, Str::limit($body, 1400, ''), $intentId, 'family_administration_authoritative_read', $guides);
    }

    private function available(User $actor, SupportTicket $ticket, string $tool): bool
    {
        return $this->eligibility->evaluate($actor, self::CAPABILITY, $ticket, $tool)->allowed;
    }

    private function domainAction(User $actor, SupportTicket $ticket, string $actionId, bool $active = true): AiSupportMessageAction
    {
        return AiSupportMessageAction::query()->whereKey($actionId)->where('support_ticket_id', $ticket->id)
            ->where('actor_user_id', $actor->id)->where('action_type', AiSupportMessageAction::TYPE_DOMAIN_RECAP)
            ->when($active, fn ($query) => $query->whereNull('invalidated_at'))->firstOrFail();
    }

    /** @return array<string,mixed> */
    private function recordFor(string $intentId): array
    {
        return app(FamilyIntentCatalog::class)->find($intentId) ?? ['intent_id' => $intentId];
    }

    private function nameFromMessage(string $message): ?string
    {
        if (preg_match('/(?:name|called)\s+(?:to|is)\s+([\pL][\pL\pM .\'-]{1,119})/iu', $message, $match) !== 1) {
            return null;
        }

        return trim($match[1], " \t\n\r\0\x0B.!?");
    }

    private function emailFromMessage(string $message): ?string
    {
        return preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $message, $match) === 1 ? mb_strtolower($match[0]) : null;
    }

    /** @return array{email:bool,in_app:bool} */
    private function channelsFromMessage(string $message, int $intentNumber): array
    {
        $lower = mb_strtolower($message);
        $turnOff = str_contains($lower, 'off') || str_contains($lower, 'stop') || str_contains($lower, 'unsubscribe') || str_contains($lower, 'disable');
        if ($intentNumber === 15) {
            return ['email' => ! $turnOff, 'in_app' => true];
        }
        $mentionsEmail = str_contains($lower, 'email');
        $mentionsInApp = str_contains($lower, 'in-app') || str_contains($lower, 'in app');

        return [
            'email' => $mentionsInApp && ! $mentionsEmail ? true : ! $turnOff,
            'in_app' => $mentionsEmail && ! $mentionsInApp ? true : ! $turnOff,
        ];
    }

    private function isNotificationPreferencesLocationQuestion(string $message): bool
    {
        $value = mb_strtolower($message);

        return preg_match(
            '/\b(?:where|how)\b.{0,80}\bnotifications?\s+(?:preferences?|settings?)\b|\b(?:open|show|find|take me to|go to)\b.{0,48}\bnotifications?\s+(?:preferences?|settings?)\b|\bnotifications?\s+(?:preferences?|settings?)\b.{0,48}\b(?:where|how)\b/iu',
            $value,
        ) === 1;
    }

    private function eventKeyFromMessage(string $message): ?string
    {
        $lower = mb_strtolower($message);

        return collect(MarketplaceNotificationPresentation::eventsForRole('family'))->first(
            fn (string $event): bool => str_contains($lower, mb_strtolower(MarketplaceNotificationPresentation::label($event)))
                || str_contains($lower, str_replace('_', ' ', mb_strtolower($event)))
        );
    }

    /** @param list<string> $events */
    private function preferenceHash(User $actor, array $events): string
    {
        $rows = UserNotificationPreference::query()->where('user_id', $actor->id)->whereIn('event_key', $events)->orderBy('event_key')->get()
            ->map(fn (UserNotificationPreference $row): array => [$row->event_key, (bool) $row->email_enabled, (bool) $row->in_app_enabled, $this->stamp($row->updated_at)])->all();

        return hash('sha256', json_encode([$events, $rows], JSON_THROW_ON_ERROR));
    }

    private function minutesLabel(int $minutes): string
    {
        return intdiv(max(0, $minutes), 60).'h '.str_pad((string) (max(0, $minutes) % 60), 2, '0', STR_PAD_LEFT).'m worked';
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return Str::substr($local, 0, 1).'***@'.$domain;
    }

    private function stamp(mixed $value): string
    {
        return $value?->toIso8601String() ?? '';
    }

    /** @return array{outcome_code:string,domain_reference_type:string,domain_reference_id:string,receipt_reference:string} */
    private function receipt(string $outcome, string $type, int $id, string $reference): array
    {
        return ['outcome_code' => $outcome, 'domain_reference_type' => $type, 'domain_reference_id' => (string) $id, 'receipt_reference' => $reference];
    }

    private function receiptText(AiSupportConfirmedActionEvidence $evidence): string
    {
        return match ($evidence->outcome_code) {
            'account_name_updated_verified' => 'Your account name was changed and checked.',
            'verification_email_requested_verified' => 'A new verification email was requested. Check your inbox and spam folder.',
            'family_invitation_sent_verified' => 'The Family invitation was sent and recorded.',
            'family_invitation_created_delivery_unconfirmed' => 'The Family invitation was created, but email delivery could not be confirmed. Open Family access to resend it.',
            'family_invitation_resent_verified' => 'A new Family invitation link was sent. The earlier link no longer works.',
            'family_invitation_renewed_delivery_unconfirmed' => 'The invitation link was renewed, but email delivery could not be confirmed.',
            'family_invitation_cancelled_verified' => 'The Family invitation was cancelled and checked.',
            'family_member_removed_verified' => 'The Family member’s access was removed and checked.',
            'family_member_left_verified' => 'You left the Family Account. Its shared records are no longer available to your account.',
            'notifications_marked_read_verified' => 'All current notifications were marked read.',
            'notification_preferences_updated_verified' => 'Your notification preferences were saved and checked.',
            default => 'The requested action was completed and checked against the current record.',
        };
    }

    private function receiptLabel(AiSupportConfirmedActionEvidence $evidence): string
    {
        return match ($evidence->domain_reference_type) {
            'user' => 'Open Account Settings',
            'family_account_invitation', 'family_account_member' => 'Open Family access',
            default => 'Open Notifications',
        };
    }

    private function receiptUrl(AiSupportConfirmedActionEvidence $evidence): string
    {
        return match ($evidence->domain_reference_type) {
            'user' => route('profile'),
            'family_account_invitation', 'family_account_member' => route('family.access'),
            default => route('family.notifications.index'),
        };
    }

    private function automatedMessage(SupportTicket $ticket, string $body): SupportTicketMessage
    {
        return DB::transaction(function () use ($ticket, $body): SupportTicketMessage {
            $locked = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->responder_mode !== SupportTicket::RESPONDER_MODE_AUTOMATED) {
                throw new \RuntimeException('Automated ownership ended before delivery.');
            }
            $message = SupportTicketMessage::query()->create([
                'support_ticket_id' => $locked->id, 'sender_user_id' => null,
                'kind' => SupportTicketMessage::KIND_PUBLIC, 'responder_type' => SupportTicketMessage::RESPONDER_AUTOMATED,
                'body' => Str::limit($body, 2000, ''), 'client_message_id' => (string) Str::uuid(),
            ]);
            $locked->forceFill(['last_public_message_at' => $message->created_at, 'last_public_message_sender_id' => null, 'opener_last_read_at' => null])->save();

            return $message;
        }, 3);
    }
}
