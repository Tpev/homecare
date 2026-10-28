# Prepaid care hours notification reference

Updated October 28, 2026. This reference covers prepaid purchases, gifts, contributions and their booking and payment notifications in the local release. Production remains a separate activation decision. A recorded email send means the configured mail transport accepted the message; actual inbox delivery has not been verified for this release.

Use this with the [user guide](prepaid-care-hours-user-guide.md) and [admin guide](prepaid-care-hours-admin-guide.md). Names in examples are fictional.

## Who receives what

- **Buyer** means the person who paid. A guest contributor or gift purchaser can receive transactional email without creating a LoLo account.
- **Family** means active members of the recipient's family account, including its owner. Shared visit events expand to those members; they retain separate notification preferences and read states.
- **Caregiver** means the person assigned to the visit. A contributor does not receive caregiver or booking updates merely because they funded hours.
- **In-app** means a notification in the signed-in person's notification center. It does not mean a text message or a mobile push notification.

Purchase and gift emails use a dedicated transactional path. Visit notifications use the existing marketplace notification system. The two systems have different preference and retry rules, described below.

## When a gift recipient creates an account

Linda's account creation also triggers the normal family signup messages. These are separate from claiming her gift; the welcome message does not confirm that any hours have been credited.

| Trigger | Recipient and channel | Exact subject or title | Destination and behavior |
| --- | --- | --- | --- |
| A new family account is registered, including signup while claiming a gift | The new account holder; in-app and email under normal preferences | **Welcome to LoLo Care**; email subject **[LoLo Care] Welcome to LoLo Care** | **Find care** opens request creation. Explains creating a request, reviewing caregivers and messaging them. One welcome delivery per account and channel. |
| The same family registration completes | Configured operations recipients; email only | **[LoLo Care] New family registration: [account email]** | **Review family account** opens the admin user record. Includes signup contact details and account creation information; absent optional details are marked as not provided. One email to each intended operations recipient for that registration. |

Jane does not receive Linda's signup messages merely because she bought the gift. The buyer email-verification message is also separate from Linda's account welcome. Registration does not create a care booking or confirm a caregiver payment.

## Purchase gift and contribution messages

The following subjects and titles are the text used by the application. Verification codes and gift claim codes are different: the six-digit email verification code proves access to an inbox; the gift code is the private entitlement the buyer shares.

| Trigger | Recipient and channel | Exact subject or title | Destination and behavior |
| --- | --- | --- | --- |
| A guest requests email verification for a contribution, gift purchase or protected receipt | Entered buyer email; email only | **Your LoLo care-hours verification code** | Contains a six-digit code. Default validity is 20 minutes. No gift claim code is included. Requests are limited to three per 15 minutes for the applicable email scope. |
| A direct purchase or private-link contribution has paid and its hours have been issued | Buyer; email only | **Your LoLo care-hours receipt** | **View receipt**. Shows recipient display name, total hours, price, any welcome hours, included family fee, non-refundable terms and no expiry. A contributor receives their own purchase receipt. |
| The same hours are issued to a recipient | Active family members; in-app only | **Care hours added** | Opens that recipient's Care hours page. States the number of hours added. The buyer does not receive family access through this notification. |
| A gift payment is confirmed, before a recipient is chosen | Gift buyer; email only | **Your LoLo care-hours gift** | **View gift receipt**. Shows purchased hours and price. The buyer opens the private receipt and verifies their email to reveal the claim code. The email does not expose the eventual recipient. |
| A paid gift is successfully claimed | Active members of the claiming family; in-app only | **Care hours added** | Opens the recipient's Care hours page. The credited total includes any welcome bonus confirmed at claim. No separate gift-claimed email is sent to the purchaser. |
| A normal package purchase fails or needs payment attention before completion | Buyer; email only | **Your LoLo care-hours payment needs attention** | **Review purchase**. Says the purchase has not completed and no hours were added. A queued warning is suppressed if hours have already been issued by delivery time. |
| A gift payment needs attention before payment completes | Gift buyer; email only | **Your LoLo care-hours gift** | **View gift receipt**. The body says to review the current payment status before sharing the gift. A queued warning is suppressed if payment has completed by delivery time. |
| A purchase is held after a payment dispute or funding review | Active family members, if a family is attached; in-app only | **Care hours need review** | Opens the recipient balance. Says some hours are on hold and to contact LoLo for help with affected visits. Suppressed if the purchase is no longer held when the pending message is delivered. |
| A gift purchase is held | Gift buyer; email only | **Your LoLo gift needs review** | **View gift receipt**. Also queues the family hold message when the gift has already been claimed. The buyer message is suppressed if the purchase is no longer held when dispatched. |
| An authorized correction of an invalid, unissued purchase succeeds | Buyer; email only | **Your LoLo purchase was corrected** | **View purchase**. Says no hours were issued and the purchase amount was returned to the original payment method. This is an operational correction, not a refund option for valid packages. |

Buyer transactional messages are not controlled by family notification email preferences. The family balance-added and hold messages also use the direct purchase outbox rather than marketplace preference toggles. These messages do not send SMS or push notifications.

## Visit coverage settlement and caregiver earnings

These messages use normal notification preferences. In-app and email are enabled by default; each person can change their own preferences. Email subjects add the prefix **[LoLo Care]** to the title shown below.

| Trigger | Recipient | Exact title | Meaning and destination |
| --- | --- | --- | --- |
| Upcoming prepaid visit lacks sufficient coverage, or approved actual care leaves an amount due | Family | **Visit coverage needs attention** | Upcoming care: review care-hour and payment coverage. After approval: review payment for additional care time. Opens the care request. The same funding or settlement revision does not repeatedly notify. |
| Approved prepaid care has been reconciled with no remaining amount due and no funding hold | Family | **Care hours used for your visit** | Shows hours used and either the additional visit payment in USD or that no additional visit payment was needed. Opens the visit. This confirms the family side of settlement; caregiver payout may still be processing. |
| The caregiver's full positive net amount for a prepaid visit is confirmed transferred | Caregiver | **Earnings available in Stripe** | Shows the net amount and opens **Earnings**. Explicitly states that bank arrival follows the caregiver's Stripe payout schedule. No success notice is sent for a pending or partial payout, an unconfirmed transfer, or zero earnings. |

The family preference names are **Care hours need attention** and **Care hours used for a visit**. Caregiver earnings use the existing **Payout sent** preference.

Reconciliation can run repeatedly without sending another success notice for the same settlement revision or the same confirmed transfer set. A later approved time correction may produce an updated family settlement notice. A downward correction does not announce another transfer because no new transfer occurred. A higher corrected total can produce a new earnings notice after its additional transfer is confirmed.

## Normal booking messages still apply

Prepaid funding changes how a visit is paid. It preserves the surrounding application, chat, scheduling and care workflow. The table lists the user-facing titles for the standard request and visit journey; marketplace email subjects use the **[LoLo Care]** prefix.

| Stage | Recipient | Title and delivery |
| --- | --- | --- |
| Caregiver selected for a one-time request | Selected caregiver | **You were hired**; in-app and email by preference. Opens care details. |
| Family confirms the hire | Family | **Hire confirmed**; in-app only. This acknowledgement does not generate an email, even if an older email preference is enabled. |
| Scheduled visit is about 24 hours away | Family and eligible caregiver | **Visit tomorrow**; in-app and email by preference. |
| Scheduled visit is about one hour away | Family and eligible caregiver | **Visit starting soon**; in-app and email by preference. |
| Caregiver checks in | Family | **Care visit started**; in-app and email by preference. Opens the visit. |
| Caregiver checks out and submits their timesheet | Family | **Care visit completed**; in-app and email by preference. The family reviews actual hours. Completion is not itself a payout confirmation. |
| Family marks a visit completed | Caregiver | **Care visit completed**; in-app and email by preference. This is a different action from the caregiver checkout message above. |
| Eligible, undisputed timesheet is automatically approved after 24 hours | Family and caregiver | **Timesheet approved**; in-app and email by preference. A pending time correction or applicable hold prevents automatic approval. |
| One party proposes a visit schedule change | Other party | **Visit change request**; in-app and email by preference. |
| Proposed visit schedule change is answered | Original requester | **Visit change accepted** or **Visit change declined**; in-app and email by preference. |
| Family cancels a scheduled visit | Caregiver | **Visit cancelled**; in-app and email by preference. Reserved prepaid hours return to the recipient balance through the cancellation workflow. |
| Family records caregiver no-show | Caregiver | **Visit marked no-show**; in-app and email by preference. Reserved hours are restored; no cancellation/no-show fee applies. |
| A caregiver cancellation reopens a request for replacement | Family | **Choose a replacement caregiver**; in-app and email by preference. |
| Family submits a caregiver review | Caregiver | **You received a new review**; in-app and email by preference. |
| Chat message or human support reply remains unread | Message recipient | Immediate in-app message alert. Email is delayed, grouped and subject to the rules below. |

Scheduled reminders run around the target time, within a five-minute window on each side. For recurring visits, the caregiver reminder is sent only when the visit's actual payment coverage is ready, including valid prepaid coverage. A family reminder is not proof that funding is ready; families must still check any coverage warning.

Application-submitted and invitation-sent acknowledgements are also in-app only. Notifications to the other party retain their usual preference-controlled delivery. Existing request recruitment and new-request operations alerts continue under their own rules; they do not disclose prepaid donor details.

## Recurring care and corrections

Recurring care retains its normal offer and schedule notifications. These are in-app and email by preference unless the recipient has disabled a channel.

| Action | Recipient | Title |
| --- | --- | --- |
| Family sends a recurring offer | Caregiver | **Recurring care offer** |
| Marketplace hire directly establishes recurring care | Caregiver | **Recurring care confirmed** |
| Caregiver accepts | Family | **Recurring care accepted** |
| Caregiver proposes different terms | Family | **Recurring care counteroffer** |
| Caregiver declines | Family | **Recurring care declined** |
| Family accepts a counteroffer | Caregiver | **Counteroffer accepted** |
| Family proposes a schedule change | Caregiver | **Recurring care schedule change** |
| Family requests another visit | Caregiver | **Extra visit requested** |
| Caregiver answers the change | Family | **Recurring care update accepted** or **Recurring care update declined** |
| Family skips one visit | Caregiver | **Regular visit skipped** |
| Family pauses recurring care now or schedules a pause | Caregiver | **Recurring care paused** |
| Family resumes recurring care manually | Caregiver | **Recurring care resumed** |
| Recurring care resumes automatically on its scheduled date | Family and caregiver | **Recurring care resumed** |
| Family ends the plan | Caregiver | **Recurring care plan ended** |

Time corrections use the following messages, in-app and email by personal preference. Family links open the visit's time-correction review; caregiver links open their care details. Messages are deduplicated by correction, version, recipient and action.

| Trigger | Recipient | Exact title |
| --- | --- | --- |
| Caregiver first submits corrected time | Family | **Visit time ready to review** |
| Caregiver resubmits after requested changes | Family | **Updated visit time ready to review** |
| Family asks the caregiver to amend the proposal | Requesting caregiver | **Please update your visit time** |
| Family approves hours that can proceed through normal finalization | Requesting caregiver | **Your corrected hours were approved** |
| Approved correction requires admin financial review | Family and requesting caregiver | **Time correction sent to LoLo Care** |
| Approved hours require further payment confirmation | Family | **Payment confirmation needed** |
| Correction is financially finalized, automatically or by an administrator | Family and requesting caregiver | **Visit time updated** |
| Caregiver withdraws the proposal | Family | **Time correction withdrawn** |
| A pending proposal still awaits family review at a reminder point | Family | **Visit time waiting for your review** |
| Requested changes still await the caregiver at a reminder point | Requesting caregiver | **Your visit time needs an update** |
| A correction is escalated to support | Family and requesting caregiver | **LoLo Care is reviewing the visit time** |

The default reminder points are 12 and 24 hours from submission; each reminder is sent once. The default escalation point is 48 hours. A nearing card-authorization expiry or repeatedly stalled finalization can cause earlier support review under the existing correction safeguards. An approval notice means the correction was approved; the applied/settlement message and the Earnings screen show whether its financial effects completed. A support case or correction notification does not authorize a package refund.

Ordinary card-funded visits retain their existing authorization, payment capture, refund and payout notices. Fully prepaid visits do not generate a card-authorization notification merely because prepaid hours were selected. Do not interpret a restored hour balance as a cash refund.

## Messages that are not sent automatically

- No email to a future gift recipient asking them to join. Jane Bennett shares the claim code privately with her mother Linda Bennett.
- No automatic purchase-by-recipient-email delivery. Outside contributions use the private contribution link; gifts use a claim code.
- No message to the gift buyer revealing who claimed it, the recipient's care details, schedule, balance or caregiver.
- No low-balance, expiry or unused-hours reminder. Hours do not expire.
- No separate purchase-style receipt for reserving hours, posting a request, changing the default payment selection, or restoring unused hours after cancellation.
- No message to every donor when care is booked or used. Ordinary booking messages go to authorized family members and caregivers.
- No generic admin email for every prepaid purchase, gift claim, failed transfer or health-check issue. Administrators use the prepaid screens, payment queue and monitoring output.
- No prepaid SMS or push channel. Existing CRM SMS is a separate feature and does not make these notification events text messages.

## Preferences and privacy

Families open **Notifications** to read alerts, mark them read and save preferences for visit events. Turning off Email for a listed event prevents future sends for that person, including a reserved marketplace email that has not yet been sent. It does not recall a message already accepted by the transport. Each active family member controls their own preferences.

For chat and human support replies, email waits five minutes after the first pending unread message, groups pending messages for that conversation, and sends at most once per conversation every 30 minutes. Reading the conversation cancels the pending email for that reader. The service checks preferences and current access again at dispatch. Administrator operational alerts remain immediate where explicitly configured.

Receipt and verification messages go to the verified buyer address recorded for that transaction. Receipt URLs can open protected pages; possessing an email alone does not grant family membership. Gift receipt messages never disclose the identity of the eventual recipient. Removing a family member before a queued purchase notice is sent prevents its delivery to that member.

## Delivery and support operations

### Purchase outbox

Purchase receipt, gift receipt, family credit, hold, attention and correction notices are recorded in `care_hour_purchase_notices`. The purchase ID, message kind and recipient form a unique key. Direct in-app notices also use a deterministic notification ID, so replay does not add a duplicate alert.

`homecare:reconcile-care-hour-purchases --limit=100` checks purchase state and then dispatches pending notices. The configured schedule runs every five minutes. This is a mutating recovery command: it can contact Stripe and send emails. It is not a read-only report.

A dispatcher claims a notice while processing it. A claim older than ten minutes can be retried. A delivery exception leaves `sent_at` empty, clears the processing claim and records a retry error. Delivery is attempted again by later reconciliation. An email can be duplicated if the transport accepted it but the process stopped before the outbox was marked sent; exactly-once email delivery is not guaranteed.

Resolved warnings are consumed without sending. In this outbox, `sent_at` and the command's delivered count can therefore include a notice intentionally suppressed after the underlying condition was resolved. They do not prove that a specific email reached an inbox. Notice failures are recorded independently of purchase reconciliation failures, so a zero command exit code alone is not sufficient evidence that all receipts were delivered.

### Marketplace delivery records

Coverage, settlement, earnings and ordinary visit alerts are recorded per recipient and channel in `marketplace_notification_deliveries`. Their dedupe keys prevent financial reconciliation from repeatedly announcing the same event. Failures are recorded as `failed`; email opt-outs at dispatch are `suppressed`. The generic marketplace path does not automatically resend an already reserved failed delivery on a repeated financial reconciliation.

New settlement and earnings notifications run after any enclosing database transaction commits. Notification errors are reported without changing confirmed settlement or payout amounts into failed financial records.

The read-only `care-hours:health --json` report checks overdue purchase notices plus prepaid visit coverage, settlement and earnings delivery backlogs. Ordinary card payout events are excluded from the prepaid-specific notice check. The command itself sends no email or alert. Operations must connect its result to their existing monitoring process; no such production change is implied by this guide.

### When someone reports a missing message

1. Identify the actual transaction or visit and whether the expected message is in-app or email.
2. Confirm the authoritative purchase/claim/settlement status. A pending payment should not have a success receipt.
3. Confirm the intended buyer address or active family membership. Never change a recipient merely to make a message arrive.
4. For a marketplace message, check the person's event preference and delivery record. For a purchase message, check the outbox record, attempts and error.
5. Check whether the message was suppressed because the issue resolved or the user opted out. Suppressed is different from failed.
6. Check the configured mail transport and provider delivery evidence through authorized operations access. A local log or `sent` row is not proof of inbox delivery.
7. Reconcile an unsettled payment using the supported admin action. Do not ask the customer to buy again just to generate another receipt.

## Example notification timeline

Jane Bennett buys a 10-hour gift for her mother Linda Bennett. Jane receives an email verification code and, after payment succeeds, a gift receipt email. She opens her protected receipt, views the gift code and shares it privately.

Linda enters the gift code, creates her account and claims for herself. If she qualifies for the welcome bonus, 11 hours are added and her family account receives **Care hours added** in-app. Jane does not receive Linda's account or care details.

Linda posts a two-hour request with care hours selected by default. Posting sends no prepaid payment receipt and does not reserve the balance. When she hires Claire, the usual hire messages apply. Scheduled reminders, check-in and checkout updates follow the visit workflow.

After Linda approves two hours, the family receives **Care hours used for your visit** with no additional visit payment. Once Claire's full net transfer is confirmed, Claire receives **Earnings available in Stripe**. The remaining nine hours stay in Linda's balance. If Claire's payout setup needs attention, the family settlement can still be complete while the earnings notice waits for a confirmed payout.

## Implementation references

- [Purchase outbox and suppression](../../app/Services/CareHours/CareHourPurchaseService.php)
- [Purchase notification subjects and in-app payload](../../app/Notifications/CareHourPurchaseNotification.php)
- [Gift email content](../../app/Notifications/CareHourGiftNotification.php)
- [Email verification notification](../../app/Notifications/CareHourVerificationNotification.php)
- [Gift claim family notices](../../app/Services/CareHours/CareHourGiftService.php)
- [Visit coverage attention](../../app/Services/CareHours/PrepaidBookingFundingService.php)
- [Approved visit settlement notice](../../app/Services/CareHours/PrepaidBookingSettlementService.php)
- [Confirmed caregiver earnings notice](../../app/Services/CareHours/PrepaidTransferPlanner.php)
- [Marketplace delivery and family expansion](../../app/Services/Notifications/MarketplaceNotificationService.php)
- [Preference resolver](../../app/Services/Notifications/NotificationPreferenceResolver.php)
- [Email suppression policy](../../app/Services/Notifications/NotificationDeliveryPolicy.php)
- [Event labels and preference catalog](../../app/Support/MarketplaceNotificationPresentation.php)
- [Signup listener](../../app/Listeners/SendOpsUserRegisteredAlert.php), [family welcome](../../app/Services/Family/FamilyWelcomeNotificationService.php), and [family registration operations email](../../app/Mail/Ops/FamilyRegisteredOpsAlertMail.php)
- [Scheduled visit reminders](../../app/Console/Commands/DispatchMarketplaceNotifications.php)
- [Automatic timesheet approval](../../app/Console/Commands/AutoApproveTimesheets.php)
- [Time correction notifications](../../app/Services/Booking/CareBookingTimeCorrectionService.php)
- [Time correction reminders](../../app/Console/Commands/ProcessTimeCorrections.php)
- [Purchase reconciliation schedule](../../routes/console.php)
- [Read-only prepaid notice health](../../app/Services/CareHours/CareHourHealthService.php)
- [Existing notification delivery policy](../product/notification-delivery-policy.md)
