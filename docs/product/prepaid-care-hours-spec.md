# LoLo prepaid care hours product specification

Status: Shared family-wallet contract prepared in the local working tree. It has not been deployed or verified in production. Delivery requires the v1 deployment bridge before the v2 family-wallet release; see the implementation plan and deployment runbook. The pilot remains restricted to `peverelli.t@gmail.com`.

Date: October 30, 2026. Supersedes recipient-scoped ownership and bonus rules; terms version `2026-10-30-family-wallet`.

Audience: Product, design, engineering, operations, and support

Implementation handoff: [Detailed implementation plan](/C:/Users/Epicpp/homecare/docs/product/prepaid-care-hours-implementation-plan.md)

LoLo lets people pay upfront for care hours for themselves or a loved one. Direct purchases and private contributions add hours to one shared family balance; gift codes let someone buy before the recipient has joined LoLo. The family owner manages the wallet, and authorized members can use it for eligible care for anyone in that family. Authorized family members use the balance for future visits, and LoLo settles usage against approved care time. Purchased hours cost $31 each. The family account's first 10-, 20-, or 40-hour package receives a one-time bonus; five-hour purchases preserve that eligibility and receive no bonus.

This specification defines the product behavior for a first release. The core commercial decisions are confirmed. Section 4 records the package prices and automatic welcome bonus, available once per family account regardless of who pays. Five-hour purchases preserve eligibility for the family account's first 10-, 20-, or 40-hour package bonus. Awarded bonus hours follow the same care rules and protections as paid hours. Actual purchase processing fees are spread across all originally issued hours, including bonus hours. No promo code or other promotion is offered at launch. Supporting product defaults and implementation requirements are specified below; launch configuration and technical design remain separate from these settled policies. No code or live payment configuration is changed by this document.

**Compatibility requirement:** prepaid care hours must preserve the current credit-card booking and payment experience. Existing bookings and regular-care plans remain on their existing card flow. Buying a package or holding a balance does not change a payment method, release an authorization, alter a charge, or change a plan. Eligible available care hours are preselected for a new request or booking, with a visible card option and any uncovered amount reviewed before confirmation. An explicit card choice is remembered. Existing plans require an explicit change for future, not-yet-funded visits. The prepaid-first funding rules below apply only inside the selected mode; prepared card funding is preserved.

## 1 Confirmed requirements and supporting defaults

The following requirements are confirmed:

- The standard family price is **$31 per hour**, comprising $30 for care and a $1 hourly fee.
- A person can buy hours for themselves or contribute hours for a loved one.
- The launch promotion awards bonus hours while keeping purchased hours at $31 each.
- Prepaid hours must work with bookings and their final billable duration.
- **Existing credit-card behavior must remain unchanged.** Eligible available hours are preselected for a new request or booking, with a clear option to use card instead. Explicit card choices are remembered. Balances never trigger automatic conversion of existing card bookings or plans, and prepared card funding is not replaced.
- **Gift recipients can join LoLo after purchase.** A buyer purchases an unassigned gift and privately shares its code. The person enters the code, creates a free family account or signs in, reviews the exact hours and claims into the family's wallet. No recipient chooser or synthetic self profile is created. **Find care** then opens normal request setup. Buying by arbitrary recipient email is not supported.

- **LoLo pays the actual Stripe processing fee upfront on a package purchase, then recovers it proportionally from caregiver earnings as its care hours are completed and settled.** Spread the actual fee across all originally issued paid and bonus minutes. Allocation is based on finalized billable minutes drawn from that purchase, not merely the duration initially reserved. Keep the caregiver's agreed gross rate for every delivered hour, including bonus hours.
- **Care-hour package purchases are non-refundable**, including unused or partially used packages and contributions for loved ones. Cancelling an undelivered visit restores its full reserved hour credit to the family's balance, without returning the package purchase in cash.
- **Paid and awarded bonus care hours have no expiry**, including self-purchases and contributions. Inactivity, a campaign ending, or a package being retired does not expire the care balance.
- **One paid or awarded bonus hour continues to cover one full hour of the same eligible care if prices increase, with no extra charge.** This includes the hourly family fee; the remaining time entitlement is not reduced or converted into a fixed dollar balance.
- **An explicitly prepaid visit can be booked without a saved card when hours fully cover it.** Reserve the planned duration without an extra buffer. A backup card is optional. Planned shortfalls in prepaid mode can be covered by a one-off payment without saving a card or by purchasing more hours; approved extensions use available hours first, and unavoidable unpaid overruns are recorded for payment follow-up. These rules do not replace the existing card-only flow.
- **There are no cancellation or no-show fees.** Return the full reserved prepaid time for a cancelled or no-show visit to the same family's balance, including late cancellations and whether the family or caregiver is responsible.
- **Outside contributions use private links enabled by the family account owner for their family wallet.** Contributors verify their email and pay without joining the family account. A contribution adds hours after successful payment and grants no booking rights or access to care information; the owner can disable the link without undoing completed purchases.
- **The welcome bonus is available once per family account on their first 10-, 20-, or 40-hour package, regardless of who pays.** Direct purchases, private contributions and claimed gifts share that family account’s eligibility. Changing payer, care recipient, owner or link does not create another welcome bonus. Previously awarded hours remain intact; an earlier valid award anywhere in the family consumes eligibility.
- **The five-hour package costs $155 and receives no bonus**, including on a first purchase. It always credits five hours. Buying it preserves eligibility for the bonus on the family account's first 10-, 20-, or 40-hour package.
- **Awarded bonus hours follow the same care rules as paid hours.** They have the same eligible care coverage, booking rules, no expiry, price protection, and included hourly family fee. Cancellations and no-shows restore the full reserved paid and bonus credit to the same family balance. Families use one combined care balance.
- **The welcome bonus applies automatically, with no code needed, and is the only promotion at launch.** Eligible self-purchases and private-link contributions show the bonus before payment. There are no other launch promotions to combine with it.

**Confirmed launch catalog:** 5 hours for $155, 10 hours for $310, 20 hours for $620, and 40 hours for $1,240. The first qualifying purchase for a family account adds 1 free hour to the 10-hour package, 2 to the 20-hour package, or 4 to the 40-hour package. Subsequent qualifying-size purchases receive the paid hours only. Section 4 defines these prices, eligibility, care rules, and processing-fee policy.

The user confirmed one balance per family account, managed by the owner. Oldest eligible purchase allocations are used first, with payment-state safeguards and complete source history. Section 6 defines the booking payment approach: card mode retains current behavior; explicitly selected prepaid mode applies available care hours before any separately authorized payment for uncovered time. Section 11 records the confirmed decisions and separates technical-design and launch-configuration work from product policy.

One paid or awarded bonus hour covers 60 billable minutes of eligible care, including the hourly family fee. The family is not charged that fee again when redeeming hours. Packages do not reserve a caregiver or guarantee availability; normal booking and care eligibility rules still apply.

## 2 Scope and family ownership

### First release

- One shared family balance for eligible care for yourself or anyone in the family.
- Direct purchases without choosing a recipient or setting up care first.
- Private contributions into the family wallet through an owner-managed link.
- Unassigned gifts claimed once into the signed-in person's family account, including after signup. The gift recipient need not have an account when the gift is bought.
- The fixed package catalog and automatic once-per-family welcome bonus; no promotion codes or other launch promotions.
- Standard-priced individual visits, including eligible regular-care visits, fully or partly covered by hours. Existing exclusions for shared care, Continuous Coverage and custom pricing remain.
- Combined balances, reservations and history, with source purchases, adjustments and settlement records retained.

### Later releases

Recipient lookup by email, transfers between family accounts, automatic package purchases, package subscriptions, public fundraising pages, anonymous gifts, caregiver-specific packages, promo codes and other promotions are deferred. Custom-price arrangements, Continuous Coverage, shared care and historical visit backfills need separate compatibility work. Buying or claiming does not create another person's care profile; normal request setup handles care details later.

### Family account and wallet

One wallet belongs to the stable `family_account_id`, managed by the family owner and available under existing family permissions. Two relatives buying add to the same balance; two care recipients in the same family can use that balance for eligible visits. The current owner's user ID, a name, a profile or a request-specific recipient ID is not the wallet key. Changes to ownership or membership do not erase or move credit.

No care recipient or profile is required to buy or claim hours. A new gift recipient can create a basic family account, review the gift and claim immediately. Claiming requires no care request, phone, medical details or payment card and creates no artificial self profile. **Find care** opens the normal request setup afterward.

The additive `care_hour_family_wallets` mapping selects a canonical wallet for each family. Existing recipient accounts remain internal source accounts. Preserve all purchases, lots, exact allocations, committed booking funding, usage history and Stripe references on their original rows; never merge financial history by name or bulk-repoint it. Combine same-family, same-currency sources for balances and allocation, while retaining holds and inactive-source restrictions. Old links and records must resolve to the same authorized family wallet without losing recovery access.

Existing welcome awards remain intact. A valid historical award anywhere in the family prevents a new family welcome award. Accepted pending qualifying attempts keep their quote and resolve before another competing claim. These rules apply across direct purchases, contributions and gifts; recipient or payer changes cannot reset them.

### Permissions

| Person | Can do | Visibility and limits |
| --- | --- | --- |
| Family account owner | Buy hours, book with the balance, view family billing, enable or revoke contribution links, manage access | Existing account powers continue; no cash withdrawal or package refund |
| Active family member | Buy hours, book and approve care using the balance, view family balance and billing | Existing operational permissions continue; cannot grant family access through a purchase |
| Outside contributor | Buy for the family identified by a valid contribution link and view their own receipt | No package refund or withdrawal; no access to care details, booking history, other contributions, or the combined balance |
| Caregiver | See that a visit has payment coverage and view their earnings | No access to who gifted hours or which promotion the family used |
| Authorized support or finance staff | Inspect allocations and resolve permitted adjustments, billing errors, or payment reversals | No discretionary unused-package refund flow; every action requires an actor, reason, and audit history |

A contribution never creates family membership or booking permission. Removing a contributor's existing family access does not remove their past contribution or their right to access their own purchase receipt.

## 3 Purchase journeys

### Buy prepaid care for your family

1. Open **Prepaid care hours** from Care or Billing. See one **Available credit** balance, including zero, and separate reserved or held time.
2. Select **Buy prepaid care** to open packages directly. Do not require a recipient chooser, care profile or care request.
3. Choose a package and review paid hours, eligible free hours, total care time and price. Apply the welcome offer automatically; no promo-code field.
4. Accept the current family-wallet terms and select **Continue to payment**. An individual payment does not replace the family's saved card.
5. Confirmed payment shows the receipt and updated family balance. A pending payment shows **Payment processing** and issues no spendable hours.

State before payment: **These hours go into your family balance and can cover eligible care for anyone in your family. Care-hour packages are non-refundable. Paid and bonus hours have no expiry. Each hour covers one hour of eligible care, even if our hourly price increases. Cancelled visits and no-shows return the full reserved hour credit, with no fee.** New acceptance records use `2026-10-30-family-wallet`; historical terms and accepted quotes remain unchanged.

### Confirmed outside contribution flow

1. The family owner enables **Let someone contribute**. New links display **Family care balance**; there is no editable recipient or label field.
2. LoLo provides a private, revocable link. The owner shares it privately.
3. The contributor reviews that label, chooses a package and verifies their own email. The family's welcome eligibility determines any bonus. No family membership is created.
4. Payment uses the contributor's own method. Confirmed payment adds hours to the target family balance with no separate claim.
5. The buyer receives their own receipt. Authorized family members receive an hours-added notification.

Only the link's display label and purchase information are public. New links use **Family care balance**; older links retain their previously approved display-name snapshot while their hours credit the same family wallet. Do not expose balances, membership, addresses, care needs, caregivers, other purchases or booking history. A name or email alone cannot select a target. Link revocation prevents new checkouts but does not undo completed contributions; already accepted payment attempts keep their immutable family target and terms. A payment that cannot validly be fulfilled follows audited invalid-purchase correction, without issuing usable hours.

State: **Your purchase adds care hours to [family label]. It does not give you access to their care account. Care-hour packages are non-refundable and have no expiry.** Retain accepted terms; a contributor cannot withdraw a completed contribution.

### Buy a gift, then claim it

1. The buyer opens **Buy a gift of care**, selects 5, 10, 20 or 40 hours, verifies their email and pays. Package selection survives verification. No recipient is chosen. The general guest capability remains subject to the current signed-in pilot restriction.
2. Authoritative payment confirmation makes a private code available in the verified buyer's receipt. No family balance, spendable minutes or welcome award exists until claim. Emails link to receipt verification and never include the code.
3. The buyer shares the code and general claim-page link privately. Whoever claims it first adds its entitlement to their family account. LoLo does not automatically email the eventual recipient.
4. The recipient enters the code and creates a free family account or signs in. The encrypted code stays in a one-hour server-session intent, never in URLs, auth HTML or flashed input. The gift itself has no expiry and can be entered again.
5. Review the exact paid hours, eligible welcome bonus and total to add to the signed-in family balance. No recipient selection or self-profile creation is needed. Confirm the family-wallet terms.
6. **Claim my gift** atomically binds the original purchase to the family's wallet and issues one lot. Repeated claims cannot add hours. Original charge, actual fee and source remain attached to the purchase.
7. **Your care hours are ready** shows the credited hours and family balance. **Find care** opens normal request setup for yourself or another family recipient. Claiming itself creates no profile, request, booking or card payment.

Example: Jane buys 10 hours for $310 for Thanksgiving before her mum Linda has a LoLo account. Linda enters the code, creates her account, reviews 10 paid hours plus an eligible 1-hour family welcome bonus and confirms. Her family balance has 11 hours ready. Jane's receipt reveals neither Linda's family identity nor care details.

Gift purchase records the conditional welcome offer; eligibility is checked for the claiming family. A later campaign pause does not withdraw an accepted conditional offer. A previous family award means paid hours only; a five-hour gift preserves eligibility. An accepted pending qualifying purchase must resolve before a competing gift can consume the family's welcome eligibility. A changed review requires reconfirmation.

Unclaimed gifts have no expiry and are non-refundable. Holds, disputes and invalid payments block claim until resolved. Pausing new sales or enrollment does not prevent valid paid gifts being claimed, including creation of the necessary family-wallet mapping. Admins can follow unclaimed and claimed gifts and reasoned payment rechecks without showing raw bearer codes. Receipts do not disclose the claiming family's identity or balance.

### Payment outcome rules

- A successful payment issues hours exactly once, including after retries or duplicate payment notifications.
- A failed or abandoned payment issues no hours. A browser success screen alone is insufficient evidence of payment.
- An uncertain outcome remains pending until reconciled; retrying must not create a second purchase or charge.
- A payment that succeeds after the intended purchase can no longer be fulfilled is handled as a billing-error correction, with a visible status for the buyer and support. This is distinct from returning a valid unused package.
- The donor's payment method is never reused for a recipient's booking, shortfall, extension, or automatic top-up.

## 4 Packages and promotions

All amounts in this specification are USD. The following package prices and bonus eligibility are confirmed. Purchased hours cost $31 each, including the hourly family fee. The welcome offer adds free hours on the family account's first 10-, 20-, or 40-hour package. Self-purchases and contributions for loved ones share the once-per-family eligibility rule below.

| Paid hours | Package price | First-purchase bonus | Total care time with bonus | Effective price per care hour |
| --- | ---: | ---: | ---: | ---: |
| 5 hours | $155 | None | 5 hours | $31.00 |
| 10 hours | $310 | 1 hour | 11 hours | About $28.18 |
| 20 hours | $620 | 2 hours | 22 hours | About $28.18 |
| 40 hours | $1,240 | 4 hours | 44 hours | About $28.18 |

The 10-, 20-, and 40-hour offers each provide 10% additional care time on the eligible purchase. The effective reduction against the standard value of all delivered care is approximately 9.09%, not 10% off the payment. These three tiers give the same proportional benefit. Subsequent purchases at these prices issue only the paid hours unless another separately approved offer applies. The five-hour package receives no bonus.

**Confirmed five-hour package:** $155 buys exactly five hours, with no free minutes on either a first or later purchase. It remains the smaller purchase and gift option.

**Confirmed eligibility after five-hour purchases:** buying five-hour packages does not consume the family account's welcome-offer eligibility. The bonus applies to their first qualifying purchase of a 10-, 20-, or 40-hour package, regardless of who pays. For example, a five-hour purchase followed by a 10-hour purchase credits 5 hours and then 11 hours; a later 20-hour purchase credits 20 hours without another bonus. Separate five-hour purchases do not combine retrospectively into a qualifying package. Describe the offer as **your first 10-, 20-, or 40-hour package** rather than implying that any first purchase earns a bonus.

Packages must show the full payable total prominently, with **Includes the hourly platform fee**. For example: **Buy 10 hours + get 1 hour free · $310 · 11 hours of care in total**. Display the $31 rate for paid hours and identify any effective rate as including the bonus. Do not imply that all 11 hours cost $31 each or that the family pays less than $310. A two-hour visit uses two hours of the available care balance.

Each catalog entry defines paid hours, price, and availability. The offer separately defines bonus minutes and eligibility. The recorded purchase retains paid minutes, bonus minutes, total minutes issued, exact amount paid, promotion, family and original source account, buyer, and accepted terms even if the catalog or offer later changes. No campaign is activated by this specification.

### First-purchase promotion rules

**Confirmed October 30:** the welcome bonus applies once per stable family account on its first qualifying 10-, 20-, or 40-hour package, regardless of payer or care recipient. Direct purchases, contributions and gifts share eligibility. A prior valid award on any historical source account consumes eligibility; all previously awarded hours remain intact. Five-hour purchases receive no bonus and preserve eligibility.

**Confirmed bonus-hour care rules:** awarded bonus hours follow the same eligible care coverage, booking rules, no-expiry promise, and protection against future price increases as paid hours. One bonus hour covers one full eligible care hour, including the hourly family fee. Cancelled or no-show visits restore their full reserved paid and bonus time to the original allocations. Bonus hours remain with the family account, have no cash-withdrawal entitlement, and appear in the same usable care balance. Ending the offer does not expire previously awarded hours. For example, buying 10 hours plus 1 free hour produces 11 usable care hours with these protections. The confirmed processing-fee policy below uses all originally issued paid and bonus minutes; internal minute-category consumption order remains a technical-design detail.

**Confirmed automatic application:** apply the welcome bonus whenever the family account and package qualify, including private-link contributions. Require no promo code or manual support request. Show the paid hours, free hours, final total care time and amount due before payment. There are no other promotions at launch, including code-based, fixed-dollar or percentage discounts, and no stacking. For example: **10 hours + 1 free welcome hour · 11 hours total · $310**. Ineligible purchases show their paid hours and price without implying that a bonus will be added.

Operating rules supporting the confirmed offer:

- Use one package per checkout. Subsequent purchases are separate transactions and recheck the same family account's eligibility; multiple packages cannot multiply the welcome award within one checkout.
- A failed or abandoned payment issues no paid or bonus minutes and does not consume eligibility. Reserve eligibility during checkout so concurrent contributors cannot both receive a once-per-family bonus. An uncertain payment remains pending until reconciled; an accepted payment attempt keeps its disclosed quote through resolution.
- Credit paid and bonus minutes together exactly once after confirmed payment. Keep their origins separately traceable while showing one usable care balance.
- A purchase dispute or reversal does not automatically reset welcome eligibility. If support fully voids an invalid or duplicate purchase that owns the recorded welcome claim, eligibility can be restored only after all its issued minutes have been removed, none remain used or reserved, and no other valid welcome award exists for that family account. Reversing a valid purchase whose care was consumed must not create another welcome bonus. Preserve audit history and resolve affected visit funding before releasing any reservation.

### Launch configuration

Set the offer's activation timing and operating budget before going live, and provide an audited way to pause new awards. Any chosen end date or award cap is launch configuration; this specification does not invent a campaign duration or approve a spending limit. Checkout must reflect the current published offer, and a pause or change must honor already accepted payment-attempt quotes and previously awarded hours. Inactivity or the end of the offer never expires issued care time. These configuration tasks do not reopen the confirmed package prices, once-per-family eligibility, no-expiry rule, or other commercial policies.

### Confirmed protection against price increases

Paid and awarded bonus time remain time entitlements: each hour covers 60 billable minutes of the same eligible standard care, including its hourly family fee. Later increases in the standard care price or hourly family fee do not reduce the available minutes or create a surcharge on their use. Gifts and promotional purchases receive the same protection.

For example, a family that buys 10 hours without a bonus for $310 still has 10 hours of eligible care if the standard family rate later increases to $35 per hour. Using two of those hours leaves eight hours and creates no additional family charge for that covered time. A qualifying purchase with one bonus hour retains all 11 hours after the same increase. Both the future $35 rate and this price change are illustrative.

New prices apply to new package purchases and uncovered care under the applicable booking agreement. Existing booking price commitments continue to follow their own rules. Protecting purchased hours does not freeze caregiver compensation at the purchase-date rate: earnings follow the applicable visit agreement, and LoLo bears any increase in the cost of providing the covered care.

The actual processing fee allocated to those hours remains the original purchase fee divided across the original total paid and bonus minutes issued with that purchase. A later hourly price increase does not create a new payment transaction or increase that historical fee allocation. The protection covers the same eligible care offering; it does not automatically add new premium services or incompatible custom agreements.

### Promotion economics

The $30 family care component is not the same as caregiver payout. The repository's standard configuration currently uses $27 per hour of caregiver gross earnings before the applicable processing-fee treatment. At those configured rates, the difference between $31 of family charges and $27 of caregiver gross earnings is $4 per hour before relevant costs; it is not a profit calculation.

At the currently configured $27 caregiver gross hourly rate, the confirmed welcome offers have the following economics if every issued hour is used at that rate:

| Payment | Total care hours | Caregiver gross earnings | Payment less caregiver gross, before other costs |
| --- | ---: | ---: | ---: |
| $310 | 11 | $297 | $13 |
| $620 | 22 | $594 | $26 |
| $1,240 | 44 | $1,188 | $52 |

These differences are not profit and do not price future increases in care delivery costs. Without a bonus, these same paid-hour packages leave $40, $80, and $160 respectively before other costs at today's configured gross rate. A promotion needs a funding budget and must not silently reduce the caregiver's agreed gross rate. Cash planning must distinguish the package processing fee paid upfront, its later recovery from caregiver earnings, fees attributable to unused hours or payment reversals, and other applicable costs. The same recovered fee must not also be counted as a permanent LoLo expense.

### Confirmed package processing fee policy

LoLo initially pays the actual Stripe processing fee on the successful package purchase. Spread that actual fee across all originally issued care minutes, including paid and bonus minutes. When a caregiver completes care and the billable time is approved, deduct that caregiver's proportional share of the purchase fee from their gross earnings. This is the actual payment-processing fee, separate from the $1 hourly fee included in the family's price. All delivered hours, including bonus hours, retain the applicable agreed caregiver gross rate; LoLo funds the promotional care.

For each purchase used by a visit, calculate its share as:

**Actual package processing fee × finalized minutes used from that purchase ÷ original total paid-plus-bonus minutes issued.**

For example, a $310 purchase issuing 10 paid hours plus 1 bonus hour with an illustrative actual processing fee of $11 allocates $1 per delivered hour across all 11 hours. Two approved hours allocate $2; at the configured $27 hourly gross rate, $54 gross becomes $52 after that deduction. The remaining $9 of fee belongs to the remaining nine hours. Total recovery over all 11 hours is capped at the actual $11. The example fee is hypothetical, not a quoted Stripe rate.

For a purchase without bonus hours, the original issued minutes equal its paid minutes. For example, 10 paid hours with a hypothetical actual fee of $10 allocate $2 to a two-hour visit. Bonus minutes never create an additional payment-processing charge, and the fee must not be divided only by paid minutes while also deducting that rate on bonus minutes.

- A booking reserves minutes and their source purchases; it does not yet deduct a fee from caregiver earnings. Finalize the deduction with approved care time and earnings.
- If several caregivers use the package, each bears only the share corresponding to the minutes they deliver. A cancelled or no-show visit creates no package processing-fee deduction for undelivered time. Its full minute and original fee allocations return to the purchase for future use.
- If a visit draws from several purchases, calculate the share separately using each purchase's actual fee and original total paid-plus-bonus minutes, then add the shares. Never average fees across unrelated contributors or family balances.
- For a mixed visit, add the actual processing fee on any new successful card payment under the existing policy, exactly once. Do not invent another card-processing fee for prepaid time or deduct the original package fee a second time.
- Keep the original total issued-minute denominator, including bonus minutes, after usage corrections or payment reversals. Fees associated with unused paid or bonus hours must not be reassigned to caregivers delivering the remaining hours.
- Use deterministic cent allocation across all issued paid and bonus minutes. Full redemption allocates the full actual fee once; partial redemption allocates only the corresponding share. Repeated settlement must not repeat a deduction.
- A time correction that reverses billable minutes also reverses the associated fee deduction with the earnings correction. Restored minutes retain their original fee allocation so a later redemption can assign it to the caregiver who delivers that care.
- Reconcile actual fee adjustments or processor fee returns against the original purchase and its deductions. Total net fee recovery must not exceed the actual processing fee retained by the processor.
- Keep fees attributable to unused minutes visible as unrecovered purchase costs until those minutes are used. No unused-package cash refund is offered, and that unrecovered fee share is not charged to unrelated caregivers.

Caregiver earnings show gross care earnings, the allocated processing-fee deduction, any actual fee from additional card payments, and net payout. Caregivers do not gain access to contributor identities or package promotion details.

## 5 Balance and purchase records

Paid and awarded bonus hours remain valid without a time limit. No annual expiry, inactivity countdown, or requirement to make another purchase applies. The same rule covers gifts and promotional purchases. Booking usage, approved corrections, and payment-dispute holds still follow their own rules; passage of time alone does not reduce the balance.

The family sees one combined balance with these labels:

| Label | Meaning |
| --- | --- |
| Available | Paid and bonus time that can be reserved for another eligible visit |
| Reserved | Time committed to specific visits, including visits awaiting time approval |
| Used | Finalized billable time, net of approved usage reversals |
| On hold | Shown only when time is temporarily unavailable because of a billing correction or payment dispute |

Store and calculate time in whole minutes, using the platform's existing billable-time rules. Display hours and minutes, such as **8 h 30 min**. Do not introduce package-specific quarter-hour rounding. Existing minimum visit lengths remain unchanged.

Each purchase creates a separately traceable allocation of time, referred to internally as a purchase lot. It retains the buyer, family and original source account, paid minutes, any bonus minutes, total issued minutes, price paid, promotion, payment reference, actual processing fee, fee allocations and reversals, terms, and subsequent usage or billing corrections. The family does not need to manage these lots. Technical design must preserve both minute categories through reservations, usage and restoration, and define their deterministic consumption order without changing the promised usable time or requiring the family to choose between balances.

Use the oldest available purchase allocation first, including its paid and bonus minutes, with a stable purchase identifier as the tie-breaker. Within an allocation, the technical design must use a deterministic category order and retain the same fee share per delivered minute. A reservation retains its exact purchase allocations through settlement. Cancellations and corrections restore minutes to those original purchases. New contributions never replace another visit's existing allocation automatically.

For every purchase, issued minutes must equal the sum of available, reserved, used, temporarily held, and reversed minutes. Those categories must be mutually exclusive. Available time must never become negative. Simultaneous bookings, billing corrections, and retries must preserve this rule.

Allocate purchase value to minutes deterministically so all usage, remaining value, and billing corrections reconcile to the exact cents paid. Remaining purchase value is an internal reconciliation measure, not a cash withdrawal entitlement. The display of an effective hourly price must not become a rounded calculation basis that loses or creates money.

## 6 Using hours for bookings

### Explicit funding mode and existing behavior

For a new request, hire or recurring offer, preselect care hours when the family has available eligible hours. The same balance covers any eligible recipient in the family, including someone added during request setup. Show the available hours and any uncovered estimate, and offer **Use card instead**. Remember an explicit card choice across recipient changes, publication and reloads. With no eligible available balance, keep the ordinary card flow. Existing bookings and plans retain their authorization, capture, saved-card, correction, check-in, cancellation and payout behavior; hours are never an automatic fallback for a failed card payment.

The family reviews the selected funding before confirming an eligible booking or recurring offer. Posting a request saves only a preference: it reserves no hours and creates no payment. Commit the booking selection atomically before preparing funding; a draft screen choice or quote does not change the booking's mode. Existing authorizations, charges and prepared or unresolved payment attempts retain their original mode; changing a plan preference does not rewrite them. Funding history determines settlement and recovery even if new enrollment is disabled later. Existing unused hours remain redeemable when new sales or enrollment are paused.

The remainder of this section describes explicitly prepaid bookings, including their separately authorized card shortfalls. It does not replace the existing card-only path.

### Eligibility and funding order

Prepaid hours apply to future visits under the eligible standard-care offering for any eligible recipient in the same family, including after a standard price increase. Eligibility must not require an old purchase price to equal the visit's newer standard hourly rate. Normal caregiver eligibility, minimum durations, and availability still apply. Incompatible legacy or custom-price bookings show their existing payment flow and an explanation that the package does not apply; their agreements are not silently changed. A purchased package is not made ineligible merely because its historical price is lower than today's standard price.

Within prepaid mode, use available hours first, then a separately authorized payment for uncovered time at the applicable booking rate. The booking summary shows planned duration, prepaid time, remaining payment amount, and any temporary card hold separately. At the current standard price, for example: **2-hour visit · 1 h 30 min from your care hours · $15.50 for the remaining 30 minutes**. Do not charge the difference between the old package price and a higher booking rate on the prepaid portion. A card-mode booking continues to use its existing card payment and leaves the care balance untouched.

### Confirmed booking payment rule

When available prepaid hours fully cover the planned visit, reserve those hours and allow booking without a saved card. The recipient can use gifted hours without supplying a payment card. A fully covered visit creates no scheduled-care card charge or hold, and there is no mandatory extra-time buffer in prepaid minutes.

If the balance does not cover the planned visit, show the exact shortfall and let the family choose a one-off payment, an already authorized family payment method, or another package purchase. A one-off payment does not require saving a card for future use. More package hours are an option, not a condition of paying a small shortfall. Resolve the planned shortfall before presenting the visit as funded and ready.

Saving a backup family card is optional and offered as a convenience. Any later use must follow the displayed authorization and existing extra-time approval rules; saving a card does not authorize unlimited extra time or automatic package purchases. A gift payment never supplies permission to reuse the contributor's card for the recipient's other charges.

The purchase disclosure is: **You do not need a saved card when care hours cover your planned visit. If more time is needed, we will show how to cover it.** Any existing card authorization buffer applies only to an uncovered card amount, never to the prepaid portion. Payment failure and uncertain outcomes still require payment-attention handling.

### When hours are reserved

- Publishing a request, messaging a caregiver, or viewing an estimate reserves nothing.
- A one-time visit reserves time when the family confirms hiring and the booking enters payment preparation. A reservation alone does not make an incompletely funded visit ready to proceed.
- For normally generated regular-care visits, reserve per actual visit when it reaches the existing payment preparation window, currently configured by default as 48 hours before the visit. An accepted extra future visit prepares payment immediately under the existing flow and reserves hours at that point. Do not reserve every generated calendar occurrence or an indefinite series upfront.
- When several visits become eligible together, process them by scheduled start, then booking identifier. Previously confirmed reservations keep their allocations.
- Outside that window, future visits may show estimated prepaid coverage with **Hours are reserved closer to the visit**. An estimate is not a funding guarantee.

If partial card coverage is chosen, the minute reservation is provisional until the required payment step succeeds. If the family instead buys more hours, issue them only after confirmed purchase payment and refresh the booking's funding explicitly. Failure releases any provisional reservation and leaves the visit needing payment attention. An unknown payment outcome is held for reconciliation rather than automatically retried or released. Rejected or expired booking attempts release their allocations once their funding outcome is known.

Explicitly prepaid regular care can be set up without a saved card. Plan creation must explain that each visit needs funding when its preparation window opens. Warn the family when the available balance is projected to fall short of upcoming care. If a visit has a shortfall, offer a one-off payment, an authorized optional backup card, or more hours. A backup card may be used only under the family's accepted authorization. There is no automatic package purchase. An unfunded visit enters the existing payment-attention workflow and is not presented as ready; depletion must not silently cancel the whole plan or disturb other funded visits. Existing card-plan setup, preparation timing and recovery remain unchanged.

A valid, fully prepaid reservation must satisfy the prepaid visit's payment-readiness check so the caregiver can check in without a new card authorization. A mixed visit also needs its uncovered card portion protected. A disputed, revoked, or incomplete allocation requires payment attention; the presence of a historic purchase alone does not prove that a visit is funded. Add this check only for prepaid-mode bookings; do not alter existing card-mode check-in rules as part of this feature.

### During and after the visit

1. Keep the planned minutes reserved while care is underway.
2. Caregiver checkout submits worked time. Show **Awaiting time approval**; checkout alone does not consume the reservation finally.
3. Finalize usage on family approval or the existing eligible automatic approval, currently after 24 hours. Existing dispute and correction holds continue to block automatic settlement.
4. Consume the final approved billable minutes, excluding non-billable pauses under existing rules. Release any surplus reservation.
5. Settle the caregiver's gross earnings against the approved visit and deduct the allocated package processing fee described in section 4, including when the family owes no new card payment.

Example: a recipient buys 10 hours without a welcome bonus and reserves three. The balance shows seven available and three reserved. If the approved visit is 2 h 30 min, the final balance is 7 h 30 min available, zero reserved, and 2 h 30 min used. A qualifying 10 + 1-hour purchase instead starts with 11 hours and leaves 8 h 30 min available after the same approved visit.

### Extra time and mixed funding

An approved extension requires additional coverage. Use still-available family hours first and disclose any remaining amount under the existing extra-time approval process. Hours committed to another visit are not available for the extension. If more payment is needed, offer a separately authorized one-off payment or optional family card; do not require saving a card. Deliberate extensions should be approved and funded before the extra time begins. Remind the caregiver and family as the planned end approaches so additional time can be discussed before coverage runs out.

The app must not automatically stop time recording or end an active visit because prepaid coverage runs out. If unavoidable extra time has already been worked and payment cannot be completed, preserve the actual time, obtain its approval through the usual process, and create a separate amount due with a payment-attention case. Do not alter worked time, invent a negative hour balance, charge the gift buyer, or silently treat the visit as fully settled. Record any prepaid portion already settled so recovery cannot consume it twice.

Confirmed prepaid coverage protects the scheduled portion. Approved earnings for an uncovered overrun remain recorded while payment is resolved. This payment rule does not promise that LoLo advances an uncollected overrun immediately; any such payout guarantee would require its own explicit policy. Existing funded visits are not silently cancelled to recover the amount due.

For a mixed booking that finishes early, recompute the uncovered card time first: the prepaid allocation continues to cover the first minutes of the final approved duration. Release or refund unnecessary card coverage, then restore excess prepaid minutes if the final duration is smaller than the prepaid allocation.

New purchases apply to future funding preparations by default. They do not automatically replace the card portion of an already funded visit, refund past visits, or reshuffle reservations. An explicit pre-visit funding refresh may be offered if the old card authorization and new reservation can be reconciled safely.

## 7 Changes cancellations and payment corrections

### Changes to a visit

| Event | Required behavior |
| --- | --- |
| Reschedule requested | Preserve existing funding while the request awaits a decision |
| Reschedule accepted with the same duration and recipient | Preserve the existing reservation; show the new visit details |
| Longer duration accepted | Reserve additional available minutes and resolve any card shortfall before confirming increased coverage |
| Shorter duration accepted | Reduce unnecessary card coverage first; then release prepaid minutes exceeding the new duration to their original purchases |
| Recipient changes | Revalidate care eligibility within the same family; keep funding atomic and preserve the original booking if the change fails. A family recipient change does not choose another wallet |
| Caregiver replacement | Release the cancelled visit's funding and independently fund the replacement; do not keep both reservations |
| Regular care paused, ended, or a visit skipped | Release only affected cancelled visits; retain funding for visits that still proceed |
| Time disputed | Keep disputed prepaid minutes reserved or held until resolution; block automatic usage finalization |
| Approved correction after settlement | Record a linked usage reversal or additional usage, plus any cash adjustment; preserve the original history |

### Confirmed cancellation and no-show policy

LoLo has no cancellation fee and no no-show fee. A cancelled or confirmed no-show visit returns its full reserved prepaid time to the same family's care balance and original source allocations. This applies to advance and late cancellations and to both family and caregiver no-shows. No hours are withheld as a penalty, and no separate cancellation or no-show payment is requested.

Restore the minutes to their original purchases without changing their price protection, processing-fee allocation, or absence of expiry. A cancelled three-hour visit returns all three reserved hours. The package remains non-refundable in cash.

For a mixed-funded visit, restore the full prepaid reservation and release the unused card authorization, or correct an actual visit overpayment through the existing billing process. The buyer's original package charge is not reversed. A cancellation or no-show does not allocate package processing fees to a caregiver for undelivered time; those allocations remain with the restored hours until they are used.

Actual care already delivered follows the normal approved-time settlement process. If a visit ends early after care starts, settle the approved worked minutes and restore the remaining reservation; do not relabel delivered care as a no-show or charge for the unused scheduled time. Existing reliability flags may still record cancellations or no-shows, but they do not create a financial penalty.

### Confirmed non-refundable purchase policy

Successfully fulfilled care-hour package purchases are non-refundable. This applies equally to self-purchases and contributions for loved ones, whether completely unused, partly used, accompanied by a welcome bonus, or reserved for upcoming care. Neither the buyer nor the recipient can withdraw the balance in cash. Support does not offer a discretionary unused-package refund flow.

- Show the non-refundable condition before payment and on the receipt; retain the version accepted by the buyer.
- A cancelled or no-show visit returns its full reservation to the same family's balance and original purchases. A released reservation or approved downward time correction restores its corresponding minutes. These operations do not return the package price in cash.
- For example, after purchasing 20 hours without a bonus for $620 and using two, the recipient retains 18 hours for future eligible care. There is no cash refund for those remaining hours.
- A contributor cannot revoke a successful contribution because some or all of the hours remain unused. Leaving the family account does not undo the contribution.
- Confirm the family account before purchase or claim. Using the wrong valid family account does not create an automatic refund or transfer entitlement. Transfers between family accounts are outside the first release; eligible recipients within one family already share its balance.
- Recipient archival or account closure prevents new contributions and requires resolution of outstanding visits and preservation of balance records. It does not automatically refund or silently erase unused hours.
- Paid and awarded bonus hours have no expiry. Non-refundability, inactivity, and the end of a promotional campaign do not introduce an expiry date.

Duplicate or erroneous charges, purchases that cannot be fulfilled, and externally imposed payment reversals remain billing-recovery cases. They must preserve exact money and minute records and prevent double spending. They do not create a general unused-package refund feature.

### Visit adjustments and payment disputes

A booking-level time or billing correction follows the recorded funding allocation. Restore eligible prepaid minutes to their original purchases; existing correction rules may return an actual excess card charge to its original card payer. Do not convert prepaid minutes into cash or return both minutes and cash for the same funded portion. The package policy does not change the existing treatment of card overpayments.

If a package payment is disputed or reversed, hold its unspent minutes and identify its affected reservations and completed visits. Notify the family and open an operational case. Do not draw from unrelated contributors, charge a donor again, automatically invalidate completed caregiver earnings, or conceal a coverage shortfall. Replacement funding and caregiver payment consequences require explicit resolution.

## 8 Family experience and operations

### Family screens

**Prepaid care hours** shows one family **Available credit**, reserved/held time and **Buy prepaid care**. Packages open directly. Provide **Contributions and usage** and owner-only **Let someone contribute**. Buying and claiming have no recipient chooser; the normal care workflow identifies who receives each visit.

New care-hours screens and the optional funding choice are additive. Preserve existing card billing screens, saved-card setup, readiness indicators and notifications for card-mode users. Show separate prepaid coverage information only in the care-hours experience or when that funding mode is selected. Purchases and gifts never replace the family's saved payment method or suppress a card-mode payment requirement.

History distinguishes purchases, contributions, reservations, released reservations, approved usage, billing corrections, payment reversals, and holds. Display dates, hours or minutes, relevant visit references, and amounts where appropriate. Family billing may show a contributor's chosen display name and contribution amount, but not their card details or private contact information.

At booking, show the funding split before confirmation. At time approval, show planned time, actual billable time, hours to use or restore, and any additional card amount. After settlement, show **Paid with care hours** and the resulting balance.

Purchase receipts identify the buyer, intended family label where applicable, paid package hours, any bonus hours, total care time issued, price per paid hour, total paid, payment status, and accepted terms including **Non-refundable · No expiry**, plus the promise that paid and awarded bonus hours remain fully covered if standard prices increase. State explicitly that the same care rules apply to paid and bonus hours. Show a billing-correction reference when relevant. A contributor receipt links only to that person's purchase information. Avoid showing the technical terms purchase lot, ledger, or payment operation in family-facing screens.

### Notifications

- Send purchase success, failure requiring action, and relevant billing-correction status to the buyer.
- Notify the family when a contribution is added or a billing correction or payment dispute affects care coverage.
- Warn the family when an upcoming visit needs additional funding because hours are insufficient. Offer a one-off payment or more hours, and group repeat warnings around the same unresolved issue.
- Remind the caregiver and family near the planned visit end to request any extension and resolve its funding; the reminder does not itself approve extra time or a charge.
- Follow existing visit and approval notifications for reservations and usage; avoid a separate message for every internal balance transition.
- Do not send care-use details to an outside contributor without separate family access.

### Administration and reporting

Authorized administrators can configure availability and pause or retire the catalog or welcome offer, with an audit of changes. Launch administration must not expose creation of additional promotion types or promo codes. Any subsequent commercial-policy change requires its own specification update and must preserve existing purchases and accepted quotes. Support can inspect a family balance, each purchase and buyer, allocations to visits, billing corrections, and disputed funds. Financial adjustments require a reason and immutable history; arbitrary balance overwrites and discretionary unused-package refunds are prohibited.

Reports distinguish cash collected from package purchases, paid and bonus minutes issued, reserved and used, promotional care costs, unused purchase value, billing corrections and payment reversals, card shortfalls, caregiver gross and net earnings, processing fees paid upfront, and fees recovered or still unrecovered. Bonus-hour face value is not an additional cash receipt. A package purchase and its later booking redemption must not be counted as two cash receipts.

Track purchase conversion, self-purchase versus contribution, average package size, redemption over time, outstanding unused hours, repeat purchases, billing-correction and dispute rates, funding failures, and support volume. Success requires correct balances and uninterrupted settlement as well as adoption.

## 9 Integration requirements

These are implementation requirements to carry into technical design, not a prescribed database or API design:

**Compatibility boundary:** use a small explicit committed-funding-mode dispatcher and separate prepaid services. Preserve the existing card state machine and handler behavior; do not bundle a broad payments refactor, provider-account migration, SDK upgrade, pricing change, or historical booking conversion into this feature. Add regression evidence for feature-disabled, feature-enabled-but-not-selected, and card-selected-with-a-positive-balance cases. Disabling new package sales or enrollment must still allow existing unused hours to be redeemed and funded prepaid care to settle and recover.

1. **Separate purchasing from visit payment.** A package may exist before any booking. One purchase can fund several visits and caregivers; one visit can draw from several purchases plus a family card.
2. **Trace the complete chain.** Preserve purchase → family wallet and original source → visit reservation → approved minutes → caregiver earnings, and any corresponding billing correction or dispute. The visit retains its actual care recipient.
3. **Support check-in and settlement for fully prepaid visits.** Existing check-in protection recognizes payment states, and payment finalization expects a successful visit charge and associated fees. A valid prepaid reservation must satisfy readiness without a saved card and settle without creating a new charge or marking a fake card payment as successful. Incomplete or disputed coverage must still trigger payment attention. One-off shortfall payments must not require enrolling a family in saved-card billing.
4. **Count fees and funds once.** A package charge must not be copied as a separate cash receipt for every visit. Allocate its actual processing fee by finalized minutes against the original total paid-plus-bonus minutes, retain the purchase-to-visit fee records, and reconcile deductions and reversals under section 4. Do not deduct the entire purchase fee for every redemption or charge it again as a visit fee.
5. **Preserve compensation rules.** Promotions cannot silently change caregiver gross rates. Deduct the allocated actual package fee from caregiver earnings as confirmed in section 4, plus any applicable actual processing fee on a new card charge once. Fee records for mixed payments, time corrections, and payment reversals must be visible and reconciled before payout.
6. **Preserve access boundaries.** Existing family membership controls spending. Contributor checkout and receipts remain independent of family membership and the saved family card.
7. **Make retries safe.** Purchase issuance, reservations, usage, and billing reversals must each have a stable operation identity. Duplicate or out-of-order confirmations must not duplicate effects. Validate authoritative payment notifications before issuing hours.
8. **Handle existing records and prices explicitly.** Require stable family ownership, same-family source grouping and an eligible care offering. Honor purchased minutes after standard rate increases without a surcharge or fee recalculation; preserve the original purchase price and actual processing fee. Existing card authorizations and completed visits are not retroactively converted on launch.
9. **Keep exceptional prepaid recovery separate.** The application's existing special prepaid-visit recovery holds are a different workflow; those flags must not be reused for ordinary package purchases or accidentally block their settlement.
10. **Update affected guidance.** Revise family billing and regular-care copy to explain prepaid funding, and extend payment terms and support guidance for the approved package policies. Regular care still settles visit by visit even though families can separately purchase hours upfront.

## 10 Acceptance scenarios

| Scenario | Expected result |
| --- | --- |
| No eligible balance, or the family selects card | Existing card provider calls, amounts, timing, statuses and saved-card behavior remain unchanged |
| Eligible available family hours for a new recipient or existing recipient | Preselect hours on the new request and carry the choice into booking; reserve only after booking confirmation |
| Family owns care hours but selects card for a booking | Use the existing card path and leave all care-hour minutes untouched |
| Family buys a package while a card booking or recurring plan already exists | Do not change the plan, release authorizations, replace charges, or convert prepared visits |
| Family selects prepaid for future unfunded regular-care visits | Only those visits use the prepaid path; existing prepared card visits keep their funding and recovery behavior |
| New prepaid selection is disabled after a prepaid visit was funded | Honor the persisted funding mode for settlement, cancellation and reconciliation; do not charge the full visit again by card |
| New enrollment or package sales are paused while a recipient has unused hours | Keep existing entitlements redeemable; do not require another purchase or force full card payment |
| Prepaid selection is still a draft or loses a race to card preparation | Do not redirect the booking or replace the card operation; commit mode only while the booking has no existing payment operation |
| Buy 10 standard hours without bonus eligibility | A confirmed $310 payment adds 600 available minutes to your existing family balance |
| Buy the 5-hour package, whether first or later | A confirmed $155 payment adds exactly 300 paid minutes and no bonus minutes |
| Buy five hours before a later qualifying larger package | Preserve welcome eligibility; the later first 10-, 20-, or 40-hour package receives its one-time bonus |
| Buy two separate five-hour packages | Each $155 purchase credits 300 minutes with no bonus; do not combine the purchases for a bonus, and preserve eligibility for the first qualifying larger package |
| Buy the 40-hour package without bonus eligibility | A confirmed $1,240 payment adds 2,400 minutes |
| Two relatives each buy 10 hours without bonus eligibility for the same recipient | One 20-hour family balance; two independently traceable purchases |
| Arrange eligible care for different recipients in one family | Both use the same family balance; exact source allocations stay traceable |
| Outside contributor uses a valid link | Hours are added; the buyer gains no family access and does not replace the saved family card |
| Contributor already belongs to a different family account | Allow the contribution without changing either family's membership or exposing recipient care information |
| Owner disables a contribution link | Prevent new checkout through that link; preserve completed contributions and handle already accepted payment attempts under section 3 |
| Buy a gift before selecting its recipient | Record the paid unassigned gift; issue no spendable hours until an authorized claim |
| Gift recipient has no LoLo account | Enter code, register, review and claim into the family wallet; create no care profile or request |
| Attempt to claim into another family by changing an ID | Reject without consuming the code; only the signed-in authorized family is eligible |
| Claim the same paid gift twice | Issue one lot only; other family accounts receive no credit |
| Payment remains pending or fails | No available minutes are issued |
| Payment success is delivered twice | One purchase and one credit only |
| Buy the 20-hour package without bonus eligibility | $620 is paid, 1,200 minutes are issued, and the purchase price is retained |
| First eligible 10-hour purchase | $310 issues 600 paid minutes plus 60 bonus minutes, totaling 11 hours, exactly once |
| First eligible 20-hour purchase | $620 issues 1,200 paid minutes plus 120 bonus minutes, totaling 22 hours, exactly once |
| First eligible 40-hour purchase | $1,240 issues 2,400 paid minutes plus 240 bonus minutes, totaling 44 hours, exactly once |
| Eligible family or private-link buyer reaches checkout | Apply the welcome bonus automatically and disclose it before payment; show no promo-code field or other promotion |
| A qualifying purchase fails before hours are issued | Issue no paid or bonus minutes, release eligibility after the failure is known, and allow a later successful qualifying purchase to earn the bonus |
| Invalid purchase owning the welcome claim is fully voided | Restore eligibility only after all its credited minutes are removed, no used or reserved minutes remain, and no other valid award exists; retain the audit trail |
| A valid purchase is disputed after bonus-funded care was consumed | Do not reset welcome eligibility or award another bonus automatically; use the payment-recovery process |
| Welcome awards are paused while a payment attempt is already accepted | Preserve that attempt's disclosed quote through resolution; prevent new quotes from advertising an unavailable bonus, and retain all previously awarded hours |
| A daughter buys the first eligible package for her mother, then a son buys for the same mother | Only the first purchase receives the welcome bonus; the son's different identity or payment method does not restore eligibility |
| Two care recipients share one family account | They share one welcome eligibility; changing the person receiving care cannot award another bonus |
| The same buyer contributes to two separate eligible family accounts | Each stable family account has independent welcome eligibility; payer identity does not combine them |
| Two contributors attempt the once-per-family offer concurrently | At most one receives the bonus; show and obtain acceptance of any changed quote for the other before payment |
| Book a visit using awarded bonus time | Apply the same eligible care and funding rules as paid time, including no saved-card requirement when the full planned visit is covered |
| Cancel or record a no-show for an undelivered visit using paid and bonus time | Restore all reserved paid and bonus minutes to their original allocations with no fee |
| Standard price increases after a qualifying 10 + 1-hour purchase | All 11 hours still cover the same eligible care, including the hourly family fee, without a surcharge |
| Bonus hours remain unused or the welcome offer ends | Keep the awarded hours valid without expiry, inactivity deductions, or another-purchase requirements |
| A caregiver delivers bonus-funded care | Pay the agreed gross rate and apply that purchase's same proportional processing-fee deduction per minute as paid time; do not reduce gross pay because the family's hour was free |
| Promotion ends or a package is retired after a purchase | Purchased hours remain valid without expiry; the original purchase price and minute records remain unchanged |
| Purchased hours remain unused for a long period | No expiry, inactivity deduction, or new-purchase requirement is applied |
| Standard price increases from an illustrative $31 to $35 after a 10-hour purchase without a bonus | The purchase still covers 10 hours of the same eligible care; no surcharge or reduction in minutes |
| A three-hour visit at an illustrative $35 rate uses two prepaid hours bought earlier | Reserve two hours and charge only the remaining hour at $35; no price-difference charge on prepaid time |
| Standard prices increase before purchased hours are used | Allocate only the original actual package processing fee over its original minutes; caregiver gross follows the applicable visit agreement |
| Book two hours from a ten-hour balance | Eight available hours and two reserved; no scheduled-care card charge |
| Book a fully covered visit with no saved family card | Reserve the planned minutes and allow booking and check-in without asking for a card |
| Book two hours with 1 h 30 min available and no saved card | Show the $15.50 shortfall; offer a one-off payment without saving a card or a package purchase; require complete funding before readiness |
| Choose not to save the card used for a one-off shortfall payment | Complete that authorized payment without creating a future billing mandate |
| The mixed booking's card step fails | Release provisional minutes after the failed outcome is known; show payment attention |
| Two concurrent bookings each request two hours while only three remain | At most three hours are reserved; additional coverage is disclosed or the affected booking remains unfunded |
| Regular care has six weeks of future calendar visits | Reserve only eligible visits in the payment window, in deterministic order; do not consume the entire series upfront |
| A regular-care visit has insufficient hours and no saved card | Notify the family and enter payment attention with payment options; preserve the plan and other funded visits |
| Family accepts an extra future visit within regular care | Reserve at its immediate payment preparation, including outside the usual 48-hour window |
| Caregiver checks in for a fully prepaid visit | A valid complete reservation satisfies payment readiness without a new card authorization |
| Caregiver checks out after two hours | Time stays reserved while awaiting approval |
| Family approves, or eligible auto-approval runs | Consume the final minutes once and release surplus; preserve existing dispute exclusions |
| A two-hour reservation settles at 90 minutes | Consume 90 minutes and return 30 to their original purchases |
| An extension has insufficient hours and card coverage fails | Preserve actual time and settled portions, flag the unpaid remainder, and avoid a negative balance or donor charge |
| A visit needs extra time and unreserved hours are available | Obtain the usual approval and reserve those minutes without requiring a card |
| Unavoidable extra time is worked without enough hours or a successful additional payment | Keep actual time and approved earnings, record an amount due, and involve support; no automatic checkout, donor charge, or negative prepaid balance |
| Cancel a three-hour visit before care is delivered | Return all three reserved hours to the same family and original purchases, with no fee |
| Family cancels late or is a confirmed no-show | Return the full reserved hour credit; no hour penalty or separate payment request |
| Caregiver cancels or is a confirmed no-show | Return the full reserved hour credit; no fee |
| Cancel a mixed-funded visit before care is delivered | Restore all prepaid minutes and release or correct the visit's unused card payment; keep the original package purchase intact |
| A visit ends early after some care was delivered | Settle approved worked minutes and restore unused reserved time without a cancellation penalty |
| Request a cash refund of unused or reserved package hours | Explain the non-refundable purchase terms; no refund workflow or cash withdrawal is offered |
| Buy 20 hours without a bonus for $620 and use two | Keep 18 hours in the family's balance for future care; no cash refund |
| Contributor asks to revoke an entirely unused purchase | Preserve the family's hours and apply the same non-refundable terms as self-purchases |
| Buyer reviews package checkout | Show family destination, price, and non-refundable terms before payment and retain the accepted terms |
| A mixed visit's approved time is corrected downward | Reduce card-funded time first, then restore any excess prepaid time; never issue duplicate compensation |
| Shorten a two-hour booking funded by 90 prepaid minutes and $15.50 to 90 minutes | Keep the 90-minute reservation and release all scheduled-care card coverage |
| Change a booking's recipient and the revised care eligibility/funding fails | Keep the original recipient, booking, and reservation unchanged |
| Family member leaves the account | Their prior contributions remain; future care access ends; their own receipt remains accessible |
| A package payment is disputed after several visits | Isolate the affected purchase and linked visits; preserve other contributors' funds and completed earning records |
| A fully prepaid visit settles | Caregiver earnings can be paid without a new family card charge |
| A two-hour visit uses a 10 + 1-hour purchase whose actual fee is an illustrative $11 | Divide by all 660 originally issued minutes; deduct $2 from that caregiver's $54 gross earnings, leaving $52 after this deduction |
| A two-hour visit uses a 10-hour purchase without a bonus whose actual fee is an illustrative $10 | Deduct $2 from that caregiver's $54 gross earnings, leaving $52 after this deduction |
| A visit uses one hour each from a 10 + 1-hour purchase with an illustrative $11 fee and a 20-hour purchase without a bonus with an illustrative $12 fee | Deduct $1 plus $0.60, totaling $1.60 of package fees |
| A visit is cancelled or recorded as a no-show without delivered care | No package processing fee is deducted from caregiver earnings; keep the fee allocation with the restored hours |
| Several caregivers eventually use all of one package | Their combined net deductions allocate the actual package fee once, with exact cent reconciliation |
| A time correction restores used minutes | Reverse the associated fee deduction with the earnings correction and preserve the original allocation for future use |
| Package hours remain unused | Keep the corresponding fee share unrecovered; do not increase deductions from caregivers delivering other hours |
| A booking uses incompatible custom pricing | Preserve its agreement and show that prepaid hours do not apply |
| A billing-error reversal and booking happen concurrently | The same purchased minutes cannot be both reversed and reserved |

## 11 Decision record and handoff

Confirmed October 30, 2026: credit belongs to one stable family account, managed by its owner and shared across eligible family recipients. Purchases and claims do not select or create a recipient. Welcome eligibility is once per family account, superseding the earlier once-per-recipient policy. Preserve all existing awards and source financial rows. Current terms are `2026-10-30-family-wallet`; older acceptances remain immutable. The approved private pilot audience and ordinary card flow are unchanged.

Core commercial decisions are complete. The following record captures the confirmed policies; supporting defaults elsewhere in the specification make them concrete for product design and engineering.

Confirmed on October 26, 2026: eligible prepaid hours should be selected by default when posting a new care request. This replaces the earlier card-first UI default. The family can choose card instead, and that choice persists. Posting does not spend or reserve hours. Existing card bookings, plans, authorizations, charges and prepared visits retain their original flow; the default applies only to new eligible choices reviewed before booking.

Confirmed on October 16, 2026: LoLo pays package processing fees upfront and recovers the actual fee proportionally from caregiver earnings when the package's minutes are delivered and settled. Spread the actual fee across all originally issued hours, including bonus hours, and keep the agreed caregiver gross rate for every delivered hour. Section 4 defines the fixed paid-plus-bonus denominator, corrections, and total recovery cap.

Confirmed on October 16, 2026: package purchases are non-refundable, including unused hours and gifts. Cancelled and no-show visits restore their full reserved hour credit to the care balance. Section 7 defines the distinction from billing-error corrections and payment reversals. Refund fee-retention and discount-clawback decisions are unnecessary for valid package purchases.

Confirmed on October 16, 2026: paid and awarded bonus hours have no expiry, including gifts and promotional purchases.

Confirmed on October 16, 2026: one purchased hour continues to cover one hour of the same eligible care without extra charge if standard prices increase. Section 4 defines the coverage, caregiver compensation, and original processing-fee allocation.

Confirmed on October 16, 2026: fully prepaid visits can be booked without a saved card or extra reserved-time buffer. A backup card is optional; planned shortfalls require a one-off payment or more hours. Approved extensions use available hours first, and unavoidable already-worked overruns follow the payment-attention process without an automatic cutoff or donor charge. Section 6 defines the booking and settlement rules.

Confirmed on October 16, 2026: no cancellation or no-show fee applies. Return the full reserved hour credit for an undelivered visit to the same family's balance, including late cancellations and family or caregiver no-shows. Section 7 defines the restoration and the separate treatment of actual care already delivered.

Confirmed on October 16 and updated October 30, 2026: outside contributors use owner-enabled private links tied to the family wallet. Contributors verify email and pay without joining the family account or gaining care access. The owner can disable new link use while completed contributions remain in the family's balance. Section 3 defines this flow.

Confirmed launch catalog as of October 16, 2026: purchased hours cost $31 each. Packages are 5 hours for $155, 10 hours for $310, 20 hours for $620, and 40 hours for $1,240. The first qualifying purchase receives 1, 2, or 4 bonus hours for the 10-, 20-, or 40-hour package respectively. Section 4 records the economics and bonus policies.

Confirmed on October 30, 2026: the first-purchase welcome bonus is once per family account, replacing the October 16 once-per-recipient rule. Direct purchases, relatives, outside contributors and gifts share eligibility. Existing awards remain intact. Section 4 records the current rule.

Confirmed on October 16, 2026: the five-hour package has no bonus. It remains $155 for exactly five hours. Buying five-hour packages preserves eligibility for the bonus on the family account's first 10-, 20-, or 40-hour package, regardless of who pays. These purchases do not accumulate into a larger package for a retrospective bonus.

Confirmed on October 16, 2026: awarded bonus hours follow the same care rules as paid hours. They have no expiry, preserve one hour of eligible care after price increases, include the hourly family fee, follow the same booking rules, and return in full when an undelivered visit is cancelled or recorded as a no-show. Paid and bonus hours appear in one usable care balance. The separately confirmed fee policy spreads the actual purchase fee across all originally issued paid and bonus hours.

Confirmed on October 16, 2026: apply the welcome bonus automatically, with no code needed and no other promotions at launch. This includes eligible self-purchases and private-link contributions. Disclose the bonus before payment.

The remaining work is a handoff, not another round of core product decisions:

| Workstream | Implementation status and launch work | Constraint |
| --- | --- | --- |
| Product design | Purchase, contribution, gift claim, balance, booking and admin screens implemented; walkthrough records the staging experience | Preserve the confirmed prices, eligibility and terms |
| Technical design | Additive family-wallet mapping, pooled source accounting, fee allocation, settlement and recovery prepared locally; see implementation plan for exact current evidence | Follow sections 5–10; do not invent a new charge for bonus hours or change the care entitlement |
| Launch configuration | Choose activation timing, operating budget, monitoring and authorized pause controls | Honor accepted payment attempts and issued hours; no live offer is activated by this document |
| Operations and guidance | Update family billing explanations, caregiver statements, support procedures and payment terms for these policies | Keep package purchases distinct from visit charges and billing corrections |

On October 21, 2026, the user expanded gifting to someone who joins LoLo after purchase. The October 30 family-wallet decision simplifies that journey to **enter code → create an account → review → claim into family balance → hours ready**. It removes the earlier self-profile setup and recipient chooser. Email lookup and transfers between family accounts remain outside scope. Actual email-delivery testing was deferred by the user; buyer email verification stays in the product. This local change does not broaden the approved pilot or authorize production-server access.

## 12 Existing product and implementation references

These local references establish the current behavior this feature must accommodate. Runtime configuration may override configured defaults.

- [Family account access specification](/C:/Users/Epicpp/homecare/docs/product/family-account-access-spec.md:75) — owner and member capabilities, shared billing, and separate contributor needs.
- [Care recipient profile specification](/C:/Users/Epicpp/homecare/docs/product/care-recipient-profile-spec.md:11) — optional reusable profiles and multiple recipients.
- [Regular care specification](/C:/Users/Epicpp/homecare/docs/product/regular-care-product-spec.md:11) — individual visits and payment preparation.
- [Pricing configuration](/C:/Users/Epicpp/homecare/config/marketplace.php:45) and [pricing calculation](/C:/Users/Epicpp/homecare/app/Support/MarketplacePricing.php:133) — standard family price, caregiver gross, and minute-based calculations.
- [Payment terms](/C:/Users/Epicpp/homecare/resources/legal/payment-terms-and-cancellation-policy.txt:29) — current pricing and successful-charge processing-fee policy.
- [Recurring payment window](/C:/Users/Epicpp/homecare/app/Services/RegularCare/CarePlanPaymentWindowService.php:20) — configurable default 48-hour preparation window.
- [Extra visit acceptance](/C:/Users/Epicpp/homecare/app/Services/RegularCare/CarePlanService.php:842) — immediate payment preparation for an accepted extra visit.
- [Check-in payment protection](/C:/Users/Epicpp/homecare/app/Services/RegularCare/CareBookingCheckInPolicy.php:50) — readiness checks that must recognize prepaid coverage.
- [Timesheet automatic approval](/C:/Users/Epicpp/homecare/app/Console/Commands/AutoApproveTimesheets.php:23) — approval timing and exclusions.
- [Booking payment cancellation](/C:/Users/Epicpp/homecare/app/Services/Payments/BookingPaymentService.php:868) — release of existing authorizations.
- [Payment finalization](/C:/Users/Epicpp/homecare/app/Services/Payments/BookingPaymentV2Service.php:183) — current dependence on visit charges and processing fees.
- [Caregiver transfer allocation](/C:/Users/Epicpp/homecare/app/Services/Payments/BookingPaymentV2Service.php:843) — current association of transfers with individual charge sources.
