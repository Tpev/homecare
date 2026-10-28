# Prepaid care hours administration guide

Updated October 28, 2026. For LoLo administrators, support and payment operations.

Use this guide to trace money into a recipient's care balance, follow the hours into visits, and resolve payment or caregiver settlement issues through the recorded transaction. The feature is implemented in the local codebase. Deployment and production activation remain separate decisions; the testing report records what has actually been verified.

## 1 Start with the right record

| The person reports | Start here | Match these details |
| --- | --- | --- |
| Hours missing after buying or contributing | **Care hours** | Recipient account and purchase reference; confirm buyer email |
| Gift missing, code unavailable or claim blocked | **Gift purchases** | Purchase reference or buyer email |
| A visit needs payment or returned hours are missing | Recipient account, **Visit funding and caregiver settlement** | Visit number and its funding status |
| A caregiver has not received payment | The same visit's settlement and **Payment details and recovery** | Caregiver net, Paid, operation references and last error |
| Several cases look stuck | **Needs attention** and the read-only health report | Age, failure category and original provider reference |

Do not ask a family to buy again simply because a payment result is delayed. First identify the original purchase or visit operation and its actual status. A successful payment, issued hours, approved care and caregiver transfer are different steps.

Example: Jane Bennett buys a gift for her mother Linda Bennett. Before Linda claims it, find Jane's purchase under **Gift purchases**. After claim, follow the linked Linda balance. When Linda books Claire Martin, use the visit link on that balance to review care and settlement. All names in this guide are fictional.

## 2 Access and navigation

An authenticated account with the **admin** role can open these pages. Sales and SDR access to other CRM screens does not grant care-hours administration access.

1. Open **Communications & money → Admin Payments**.
2. Select **Care hours** on the payments page.
3. From **Care hours**, select a recipient, **Gift purchases**, or **Payment operations**.

Direct application paths are `/admin/care-hours` and `/admin/care-hours/gifts`. They still require administrator access. Use the app's configured address; these paths are not public links to send to families.

### Find a recipient account

On **Care hours**, search by a recipient name fragment, numeric account ID, or the family owner's exact email address. Select **Needs attention** to narrow the list. The list shows recipient/account number, family owner email, available hours, active/inactive state, attention count and opening date.

The attention count can include purchase, visit funding and settlement issues for the same incident. It is not a count of unique customers. Some unresolved work is visible in health monitoring before it appears in this filter, so an empty list is not a complete operational health check.

### Find a gift

On **Care-hour gifts**, search by the exact purchase reference or buyer email. Filters are **All gifts**, **Awaiting claim**, **Claimed** and **Needs attention**.

Each result shows paid hours and price, buyer name/email, purchase reference/date, claim state, processing fee and latest admin payment check. After claim, its recipient balance is linked. If a claimed gift's payment is subsequently held, the card also displays its payment status; **Claimed** alone does not mean the funding is usable.

## 3 What the feature promises

| Package | Purchase price | Welcome bonus if eligible | Hours credited |
| --- | --- | --- | --- |
| 5 hours | $155 | None | 5 |
| 10 hours | $310 | 1 hour | 11, otherwise 10 |
| 20 hours | $620 | 2 hours | 22, otherwise 20 |
| 40 hours | $1,240 | 4 hours | 44, otherwise 40 |

Paid hours cost $31 each, including the hourly platform fee. The welcome bonus is automatic and available once per recipient on their first eligible 10-, 20- or 40-hour package, regardless of who pays. A 5-hour purchase preserves that eligibility. Gift bonuses are resolved for the recipient at claim; a gift advertises a conditional bonus, not an unconditional extra entitlement.

Paid and awarded bonus hours follow the same care rules. They do not expire. One hour remains one hour of eligible standard-priced care after a platform price increase. Valid purchases, including unused packages and unclaimed gifts, are non-refundable. Cancelling an undelivered visit or recording a no-show restores its reserved hours; there is no cancellation/no-show fee.

Hours belong to a particular recipient, with family-account access controlling who can arrange their care. A donor does not gain access to the family, care information or booking rights. Several relatives can contribute to one recipient through a private link. The software retains the individual purchase records behind that shared balance.

Family-wide custom pricing blocks buying/claiming hours for that family. A caregiver-specific custom agreement allows standard-priced care with other caregivers, but that particular custom-priced booking uses card. Shared-care arrangements are also excluded from prepaid booking. Escalate identity or pricing incompatibilities instead of creating duplicate recipient balances to work around them.

## 4 Read a recipient account

The account header identifies the recipient, care-hour account number, family number and family owner's email. Check all four before taking action.

### The four balance cards

| Card | Meaning |
| --- | --- |
| **Available** | Unreserved, usable minutes that can cover new eligible care |
| **Reserved** | Minutes allocated to booked care but not yet finalized as used |
| **Used** | Minutes applied to finalized care, subject to audited corrections |
| **On hold** | Unallocated hours withheld because the underlying purchase needs review |

The display is in hours and minutes. The ledger records minutes. A held purchase can also affect minutes already allocated to a visit; inspect the visit error and settlement state rather than relying only on the **On hold** card.

### Purchases and payment issues

For each purchase, the page shows reference, creation time, buyer, price, issued entitlement, status, actual processing fee and last reconciliation time. **Awaiting provider confirmation** means the actual fee is not known yet; it must not be treated as a zero fee.

### Visit funding and caregiver settlement

Each visit links to its admin care-request screen. It shows funding status, reserved hours and the dollar amount of uncovered scheduled time. Once settlement exists, it also shows settlement state, the family's amount due, caregiver net and amount paid.

Expand **Payment details and recovery** to see caregiver gross, allocated package processing fee, visit-payment fee and the latest 25 operation records. Each operation shows its amount, status and available payment, transfer, refund and source-charge references. More than one operation can belong to the same visit; a retry record is not automatically another successful charge or payout.

### Usage and balance history

This history shows event time, description, minutes, visit ID and purchase ID. Use it to explain purchases, reservations, consumption, restored hours and holds. It is a history of movements, not a list of cash payments.

**Recent operator actions** shows the latest 20 care-hours actions recorded for the family, including actor ID, reason and metadata. It is a recent activity view, not the complete financial audit. The current UI has no general-purpose balance editor, manual credit button, recipient-transfer button or individual lot editor.

## 5 Review purchases and gifts

### Understand purchase states

| State | Interpretation and next action |
| --- | --- |
| **Pending** | Payment/checkout has not been confirmed. Locate the original attempt before another payment is made. |
| **Paid** | Payment is confirmed. A ready unclaimed gift legitimately remains here until claim; other unissued purchases may still need fulfillment. |
| **Issued** | Hours were added to a recipient balance. This does not mean they have all been used. |
| **Failed / Expired** | The recorded attempt failed or its checkout expired. Reconcile an uncertain provider result before advising another attempt. |
| **Recovery** | Automatic fulfillment or safe retry could not finish. Review the original payment and error. |
| **Held** | A dispute or reversal requires funding review. A claim or payout may be blocked. |
| **Corrected** | An eligible invalid, unfulfilled purchase was reversed through the audited correction flow. |

### Recheck a purchase

1. Open the recipient account and locate the exact purchase.
2. Expand **Recheck provider status**.
3. Enter a reason of 5 to 500 characters, ideally including the support reference.
4. Select **Recheck payment**.
5. Review the resulting status, fee, balance/history and any remaining error.

This is a recovery action, not a read-only lookup. It records who requested the check and can apply confirmed payment results, issue previously unissued hours, reconcile an existing correction and queue notices. A provider error preserves the recorded evidence and asks for a later retry.

### Recheck a gift

1. Open **Gift purchases** and find the exact buyer/reference.
2. Expand **Payment review**.
3. Enter a reason of 5 to 500 characters.
4. Select **Recheck gift payment** and review the result.

Gift checks have their own audit record, which also works before a gift is assigned to a family. Rechecking payment does not claim a gift for a recipient.

### Support a missing or rejected gift code

The admin list deliberately does not reveal the raw claim code and has no code-reset, claim-on-behalf or reassignment control. Ask the buyer to reopen their gift receipt and verify the purchase email when requested; the verified receipt provides their code. Do not request the code in a support ticket when the purchase reference is enough.

If the gift is already claimed, follow the recipient link. If payment is held, investigate that original purchase. If an unclaimed gift is paid and ready, age alone is not an error: gifts have no expiry. If the recipient has custom family pricing, a different existing account, or an ambiguous identity, resolve that access/eligibility issue before claim; do not move hours by direct database edits.

### Correct an invalid paid purchase

**Correct an invalid paid purchase** appears only for an account-linked purchase in **Recovery**, paid with a recorded charge, with no issued hours, and with no pending/successful correction. The service also refuses a correction if any hours lot was created. It is not a refund facility for valid unused packages or valid unclaimed gifts.

1. Verify why fulfillment is impossible and that no hours were issued.
2. Enter **Reason and support reference** in the form, 10 to 1,000 characters.
3. Tick **I verified that this paid purchase is invalid and no hours were issued**.
4. Select **Reverse invalid purchase**, or **Retry failed correction** when eligible.
5. Confirm the final provider outcome before describing the reversal as complete.

For **Billing correction: pending**, use **Recheck provider status** on the existing request. Do not create a new attempt. Even after a local failure, the previous provider refund must be confirmed failed/cancelled before another attempt is permitted. Successful correction sends the buyer a correction notice and releases a welcome-offer reservation associated with that invalid, unfulfilled purchase.

## 6 Follow hours from request to caregiver payment

The ordinary card path remains available. Owning prepaid hours does not change previously arranged card bookings or existing plan funding.

1. **Request posted.** Eligible recipient hours are preselected on a new request. A family can select card, and that choice is remembered. Posting saves a preference only; it does not charge or reserve hours.
2. **Care booked.** The family reviews coverage before confirming. Full coverage needs no saved card. A shortfall requires a separate payment action or more hours. A donor's payment method is never reused as the family's card.
3. **Visit prepared.** Planned prepaid minutes are reserved. Future regular-care visits are prepared through the existing payment window; creating a plan does not spend the entire balance immediately.
4. **Care delivered.** Existing check-in, pause, checkout and approval steps continue. A visit requiring coverage is not ready for check-in until its funding is protected.
5. **Care approved.** Final approved duration determines usage. Shorter care restores unused reserved minutes. An approved overrun uses eligible additional available hours when possible; uncovered time remains a separate family amount due.
6. **Settlement and transfer.** Confirmed collected funding and actual processing fees determine payable caregiver earnings. The app records transfer operations and retries/reconciles them safely.

An already prepared mixed split is preserved if another gift arrives later. New money does not silently replace that visit's existing authorized card portion. Existing regular-care plans require an explicit change under **Payment for future visits**; already reserved or payment-prepared visits retain their funding.

### Interpret the funding and settlement labels

| Label | What to check |
| --- | --- |
| **Selected** | Prepaid mode is recorded; the visit may not be in its preparation window yet. |
| **Ready** | Planned hours plus valid separate payment coverage protect the planned visit. |
| **Payment attention** | Review the displayed reason: remaining payment, a hold, fee problem or reconciliation error. |
| **Released** | Funding was released, normally for cancelled care; a separate card authorization/refund may still require recovery. |
| **Partially settled** | Some approved coverage is collected, but money remains due. Collected eligible coverage may already have been paid to the caregiver. |
| **Fee pending** | An actual processor fee is missing; caregiver net is not final. |
| **Funding on hold** | A purchase/visit dispute or changed visit state blocks new settlement/payout. |
| **Payout pending** | Check caregiver payout setup, recorded transfer error and any uncertain original operation. |
| **Paid** | Recorded transfers, net of recorded reversals, equal caregiver net and the family amount due is zero. This is not proof of arrival in the caregiver's bank account. |

Other intermediate reconciliation states can appear. Read the error and original operation references; do not infer success from a progress label.

### Recheck visit payments

Where a settlement exists, or funding is **Released**, expand **Payment details and recovery**, provide a 5 to 500 character reason, and select **Recheck visit payments**. This action can reconcile captures, refunds, fees and caregiver transfers. It may move money through the original recorded operations. It does not independently approve care or remove a dispute.

If there is no settlement and funding is not released, this recovery button is not available. Resolve the family's coverage or care-approval step through the normal visit flow first.

## 7 Understand fees and money movements

LoLo bears the package processing fee when the package is purchased. As care is completed, that purchase's actual fee is allocated across all originally issued minutes, including welcome bonus minutes, and deducted from the relevant caregiver earnings. The system consumes the oldest available purchase allocations first and preserves exact ranges for corrections.

For each visit:

**Caregiver net = agreed gross earnings for approved care - allocated package processing fees - applicable separate visit-payment processing fees.**

Use the visit's snapshotted caregiver rate; do not assume the family's $31 rate is the caregiver's gross rate. Fee allocation rounds deterministically in cents, so splitting the same purchase across visits does not repeatedly round up the total fee.

Illustration only: a purchase credits 11 hours and its confirmed processing fee is $11. A visit consuming 2 of those hours is allocated $2 of that package fee. If its agreed caregiver gross is $54 and there is no separate visit-payment fee, caregiver net is $52. Real fees come from the provider, not this example.

One visit can draw on several relatives' purchases. The system retains which source purchase covered each range. If price protection or source-charge capacity requires extra funding, LoLo covers the gap; another donor is not charged and the family's existing hour entitlement is not reduced.

Do not confuse a restored hour reservation with a cash refund. Cancelling a prepaid visit restores its hours. An unused separately collected shortfall can require cancellation/refund of that particular card payment. Neither action refunds the original valid package purchase.

## 8 Handle common support cases

### Robert's children contribute to one balance

Emily owns the family account and creates Robert's private contribution link. Daniel and Sophie each buy hours through it. Search Robert's account to see the combined balance and both separate purchases. Verify whether the once-per-recipient welcome offer was already used or reserved by another checkout. Revoke/replace a leaked contribution link through the owner's existing control; this does not reverse completed purchases or grant donors family access.

### Linda cancels and cannot see all hours returned

Match the correct visit and ensure the cancellation/no-show was recorded, rather than merely requested. Check the **Released** funding state and **Usage and balance history**. Reserved hours should return to the same recipient, including bonus hours. If the source purchase is held, returned hours can remain held. If a shortfall card operation remains, use its recorded recovery control; do not buy replacement hours or manually add a matching balance.

### Claire completed care but has not been paid

Verify the visit is completed/approved, no dispute is open, and the settlement has the expected approved duration. Read **Family amount due**, fee fields and caregiver payout readiness. Compare caregiver net against **Paid** and the operation references. Use **Recheck visit payments** for recorded recovery. An uncertain transfer must be reconciled before another transfer is attempted; never ask another operator to send a replacement manually.

### A buyer disputes a contribution or gift payment

The purchase and its associated lot can be held, and the family receives the applicable hold notice. Before claim, a held gift cannot be redeemed. After claim, affected unallocated hours are withheld and settlements involving those funds can stop. If the dispute is confirmed won and no reversal remains, reconciliation can release the purchase hold. A hold does not automatically reverse an already completed caregiver transfer. Preserve the original references and involve payment operations for any remaining exposure.

### The family says hours were not selected

Check that the chosen recipient is the one linked to the balance, hours are available rather than reserved/held, the account/profile is active, and the booking supports prepaid pricing. A remembered card choice is intentional. Existing prepared card visits do not convert. Copying an eligible prior request preserves its explicit recipient identity; changing the recipient clears that selection. Never match recipients solely by identical names.

## 9 Notifications and delivery operations

The detailed event, recipient, channel and timing matrix is in the [notification reference](prepaid-care-hours-notifications.md). The key operator distinctions are:

- Buyer email verification is required for guest gifts and outside contributions. Verification codes are separate from gift claim codes.
- Purchase/gift receipts and buyer payment-problem messages use email. Successful additions and holds also create the applicable family in-app notifications.
- Visit coverage problems use the existing marketplace notification system. Its email eligibility and family preferences apply.
- The existing booking, visit and approval notifications continue through their current workflows. A prepaid purchase is not a new caregiver booking.
- **Care hours used for your visit** confirms an approved prepaid settlement once no family amount remains due and no hold blocks it. It reports hours used and any extra payment, through family in-app and eligible email delivery.
- **Earnings available in Stripe** tells the caregiver when a positive net settlement has been fully transferred. It distinguishes availability in Stripe from arrival in a bank account, which follows the caregiver's Stripe payout schedule.
- There is no general admin prepaid-incident email. Monitor the operational views and health report; do not expect a new email for every ledger movement.

The care-hour purchase notice outbox records unsent notices and retries them. In-app replay uses a stable notification identity to prevent duplicate insertion. An email can rarely repeat if the process stops after mail transport acceptance but before recording success. Resolved warnings are consumed without sending, so the outbox's sent timestamp and delivered count can include intentionally suppressed notices. Neither proves inbox delivery or that the recipient read a message.

When a notice appears missing, identify its event and intended recipient, check the corresponding purchase outbox or marketplace delivery status and error, and verify the configured transport/worker through the authorized operations process. Purchase notices retry through their outbox, but the generic marketplace path does not automatically resend an already reserved failed delivery when financial reconciliation repeats; those delivery failures need operational review. A purchase-reconciliation command can return success while an individual notice still requires retry. Actual inbox delivery remains a launch verification item unless a later test report explicitly confirms it.

## 10 Scheduled recovery and health checks

These commands are a reference for authorized operators in an approved environment. This guide does not authorize production access, SSH, deployment, enabling flags or sending live notices. Prefer the admin UI for a single known case.

| Command or check | Schedule in code | Effect |
| --- | --- | --- |
| `php artisan care-hours:health --json` | No automatic schedule declared for this command | Read-only aggregate backlog/schema check. No provider calls or notifications. |
| `php artisan homecare:reconcile-care-hour-purchases --limit=100` | Every 5 minutes; no overlapping runs; background | Mutates recovery records, can create/resume accepted checkout attempts, retrieve payment results, fulfill purchases, reconcile corrections and dispatch purchase/gift notices. |
| `php artisan care-hours:reconcile-bookings --limit=100` | Every 10 minutes; no overlapping runs; background | Mutates recorded shortfalls, releases and settlements; can cancel/refund separate visit payments and recover caregiver transfers. |
| Existing regular-care payment preparation | Hourly | Prepares upcoming visits according to the plan and payment window. |
| Existing payout transfer retry | Hourly | Handles eligible payout recovery through existing payment dispatch. |

The limits are bounded to 1-500 by the reconciliation commands. The booking reconciliation command catches several per-operation errors and can still exit successfully; use recorded errors and health backlog to determine whether work is finished.

### Interpret health output

Exit **0 / healthy** means no persisted backlog is older than the selected thresholds. Exit **1 / attention** means at least one category is overdue. Exit **2 / unknown** means schema, parameters or inspection could not establish health. Treat unknown as requiring investigation, not as an empty backlog.

The default age is 30 minutes, with a 1,500-minute grace period for ordinary open checkouts. The command validates the required prepaid schema, including request funding preferences. It checks purchase fulfillment/fees/corrections, settlements, payment releases/operations, booking checkouts, purchase notices, prepaid visit coverage/settlement/earnings notices, holds and the shared Stripe webhook inbox. Ordinary card payout alerts are excluded from the prepaid-specific notice check.

Counts overlap. Ages use stable original business/creation timestamps, not the last retry time. A ready, unclaimed gift is a valid non-expiring obligation and is excluded from stuck fulfillment. The shared webhook category covers ordinary card payments too; its errors are not automatically prepaid errors.

Healthy does not verify provider connectivity, actual email delivery, scheduler liveness, distributed locks, bank delivery or production readiness. Assign a human/monitoring owner to act on nonzero/unknown results; the command itself sends no alert.

## 11 Pause and rollback safely

| Configuration switch | Controls |
| --- | --- |
| `CARE_HOURS_ENABLED` | Discovery and overall new-feature exposure |
| `CARE_HOURS_ENROLLMENT_ENABLED` | New direct recipient enrollment |
| `CARE_HOURS_SALES_ENABLED` | New sales |
| `CARE_HOURS_CONTRIBUTIONS_ENABLED` | New outside contributions |
| `CARE_HOURS_GIFTS_ENABLED` | New gift sales |
| `CARE_HOURS_WELCOME_BONUS_ENABLED` | New welcome-offer quoting |

Pausing exposure or sales does not erase existing balances, cancel prepared care, invalidate accepted purchases or remove previously paid gifts' claim rights. Paid gift claims retain their accepted offer rules. Keep reconciliation, webhooks and necessary notification processing running for existing obligations.

The deployment driver validates a complete, fingerprinted prepaid source package and requires a reviewed full commit pin. Its first prepaid introduction requires cached feature and gift exposure to be disabled. It applies additive migrations and records a permanent prepaid compatibility floor before activation.

Once that floor is recorded, manual rollback, automatic failure recovery and forward deployment to older code must still use prepaid-compatible code. This restriction applies even before the first sale. Do not delete the floor, reverse prepaid migrations, delete ledger data, clear holds, or start old incompatible workers to escape a failed deployment. Prepare a reviewed compatible recovery release or forward repair before introduction.

A retained rollback target must also preserve all six current cached prepaid switches. If its settings differ, use an approved forward release with the intended configuration. The driver does not silently rewrite the shared environment or reopen paused sales. Follow the [deployment runbook](../deployment/zero-downtime-deployments.md) for the exact engineering procedure.

The older support-specific documents named **prepaid visit recovery** and **prepaid visit adjustment** concern already captured card-funded visits. Their case-specific commands are not generic prepaid care-hour balance tools and must not be used for these accounts.

## 12 Before production activation

Review the latest validation report together with the proposed release. Local automated tests do not establish the current production runtime. The authorized deployment owner must verify the complete committed feature and matching manifest; all six additive migrations; warmed disabled introduction flags; compatible recovery release; production database/shared-lock behavior; current webhook delivery; scheduler and queue operation; actual email transport; caregiver payout configuration; and monitoring/support ownership.

The current instruction is to keep production unchanged and never use SSH to the production server from this work. Nothing in this guide changes that boundary.

## Source reference

These links identify the implementation behind the operating instructions.

- [Admin routes and access](../../routes/care-hours.php), [admin role middleware](../../app/Http/Middleware/EnsureAdminEmail.php), [navigation](../../resources/views/livewire/layout/navigation.blade.php)
- [Account admin controller](../../app/Http/Controllers/CareHours/AdminCareHoursController.php), [gift admin controller](../../app/Http/Controllers/CareHours/AdminGiftController.php), [admin views](../../resources/views/care-hours/admin)
- [Package configuration](../../config/care_hours.php), [purchase and correction service](../../app/Services/CareHours/CareHourPurchaseService.php), [gift claim service](../../app/Services/CareHours/CareHourGiftService.php), [eligibility](../../app/Services/CareHours/CareHourEligibilityService.php)
- [Request defaults](../../app/Services/CareHours/CareHourRequestFundingService.php), [booking funding](../../app/Services/CareHours/PrepaidBookingFundingService.php), [settlement](../../app/Services/CareHours/PrepaidBookingSettlementService.php), [transfer planning](../../app/Services/CareHours/PrepaidTransferPlanner.php)
- [Ledger](../../app/Services/CareHours/CareHourLedgerService.php), [per-lot fee allocation](../../app/Models/CareHourLot.php), [read-only health](../../app/Services/CareHours/CareHourHealthService.php), [scheduled commands](../../routes/console.php)
- [Deployment driver](../../deploy.sh), [prepaid compatibility manifest](../deployment/prepaid-care-hours-v1.sha256), [product specification](../product/prepaid-care-hours-spec.md)
