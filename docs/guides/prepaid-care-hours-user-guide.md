# LoLo prepaid care hours user guide

Prepared October 30, 2026. Describes the shared family-wallet release prepared locally. It is not yet verified or deployed in production. Delivery requires the bridge release followed by the family-wallet release, using the normal deployment process for each; see the [deployment runbook](../deployment/zero-downtime-deployments.md#family-wallet-contract-v2).

Initial pilot: new prepaid screens and purchases are restricted to Thibaud Peverelli's signed-in family account, `peverelli.t@gmail.com`. No manual environment change is needed to start this pilot. Other family accounts keep the ordinary card-payment experience, and public gift and contribution sales stay unavailable. The full journeys below describe the capability after access is granted. Already-paid gifts and existing balances retain their claim, receipt and care-payment rights.

Care hours let you pay for future care for yourself or someone you love. Several relatives can add hours to one family balance, managed by the account owner and usable for eligible care for anyone in that family. A gift code also lets you buy before the recipient has a LoLo account.

The simple sequence is **buy or claim hours, request care, review coverage, then book**. Posting a request does not use or reserve hours. Hours are reserved when a visit is booked and settled against its approved care time.

## 1 Choose the right way to give

| What you want to do | Use this option | Where the hours go |
| --- | --- | --- |
| Buy care for yourself or someone in your family | **Prepaid care hours**, then **Buy prepaid care** | Your shared family balance after payment succeeds |
| Help a family already using LoLo | The family's private contribution link | The family balance named on the link |
| Give a present before someone joins LoLo | **Buy a gift of care** | A private code; the person claims its hours into their family balance |
| Redeem a gift | **Have a gift code?** | Your family balance after sign-in or signup and confirmation |

A contribution link identifies a family balance. A gift code is claimed once into the signed-in person's family balance. Neither purchase gives the buyer access to the family's care information. You do not choose a care recipient when buying or claiming hours.

## 2 Packages and the welcome bonus

Every purchased hour costs **$31 USD**, including the hourly platform fee. No additional hourly platform fee is charged when those hours cover eligible care.

| Package | Price | Welcome bonus if eligible | Total with bonus |
| --- | --- | --- | --- |
| 5 hours | $155 | None | 5 hours |
| 10 hours | $310 | 1 free hour | 11 hours |
| 20 hours | $620 | 2 free hours | 22 hours |
| 40 hours | $1,240 | 4 free hours | 44 hours |

The welcome bonus applies automatically, once per family account, on their first qualifying 10-, 20-, or 40-hour package. Direct purchases, outside contributions and claimed gifts all share that family account’s eligibility, regardless of who pays or who receives care. Previous welcome hours stay intact; a prior award anywhere in the family means no new welcome award. A 5-hour purchase does not use up their eligibility. Later qualifying packages receive their purchased hours only. There is no promotion code to enter.

For an unclaimed gift, the buyer purchases the stated paid hours. LoLo checks the family account's bonus eligibility when the gift is claimed and shows the exact total before confirmation. A 10-hour gift therefore guarantees 10 paid hours; it adds 1 welcome hour only if the claiming family account is eligible.

Paid and awarded free hours follow the same rules:

- They have no expiry and stay with the family account.
- Package purchases are non-refundable, including unused hours.
- One hour continues to cover one hour of eligible care if the standard hourly price increases.
- Cancelling an undelivered visit or recording a no-show returns its reserved hours to the family's balance.

Care hours cover standard individual visits, including eligible individual visits in a recurring schedule. Shared care, continuous coverage and custom-price arrangements are excluded. Buying hours does not reserve a caregiver or guarantee availability.

## 3 Find your balance and buy hours

1. Sign in to your family account and open **Care**. Select **Prepaid care hours** beside **Request new care**. The same link is available from **Billing**.
2. **Available credit** shows the hours ready to use, including zero before your first purchase. Reserved and held hours appear separately. This is one balance for your family.
3. Select **Buy prepaid care** to go straight to packages. No recipient selection or care profile is required.
4. Choose a package and review paid hours, any free welcome hours, total care time and price.
5. Accept the non-refundable and no-expiry terms, select **Continue to payment** and complete payment.
6. **Your care hours are ready** shows the receipt and updated family balance. Use **Your activity** for contributions, purchases and receipts.

Buying hours does not create a care request or profile. Gift recipients can also claim without setting up care first, as described in section 5.

**If payment is still processing:** return to the same receipt and use **Check payment status**. Hours are available only after payment is confirmed. Do not buy again just because the confirmation is taking time. An unfinished checkout may offer **Continue payment**.

## 4 Let siblings contribute to a parent's care

### The family account owner creates the link

1. Open the family’s **Prepaid care hours** balance.
2. Expand **Let someone contribute**.
3. The link shows **Family care balance**. You do not need to select a person or enter a label.
4. Select **Create private link**, then **Copy link**.
5. Send the link privately to the relatives who want to help.

Copy or save the link when it is displayed. A later visit lists when links were created and lets the owner disable them; it does not display the original private URL again. If needed, create a new link and disable an older one.

Only the family account owner can manage these links. Each contributor can buy a package without joining LoLo or being invited into the family account.

### Each contributor buys a package

1. Open the private link and confirm the family label on the page.
2. Enter your name and email, then select **Send verification code**.
3. Enter the six-digit email code and select **Verify email**. The code expires after 20 minutes under the current settings.
4. Choose a package, review the terms and select **Continue to payment**.
5. Complete payment. The hours are added to the named family balance once payment succeeds, and you receive your own purchase receipt.

Contributors see **Family care balance** and package information. Older links may keep the display name approved when they were created; they still add to the same family pool. Contributors do not see the balance, address, care needs, caregiver information or booking history. Payment does not grant booking rights.

### Example with Robert and his children

Robert's daughter Emma manages his family account. She shares their family's private contribution link with her brothers Daniel and Alex. Assume the family has not received a welcome bonus and each purchase finishes before the next starts.

| Person | Purchase | Added to the family balance |
| --- | --- | --- |
| Emma | 10 hours for $310 | 11 hours, including the family's one welcome hour |
| Daniel | 5 hours for $155 | 5 hours |
| Alex | 10 hours for $310 | 10 hours |
| Total | $775 paid by three people | **26 family care hours** |

Everyone contributes to the same family pool for Robert's care. The family can also use those hours for another eligible care recipient within the same account. They buy fixed hour packages; this is not an arbitrary-dollar cash wallet. If another welcome-bonus purchase is already processing, wait for its result before buying another package.

The owner can select **Disable link** to stop new purchases through it. Completed contributions stay in the balance. A payment already accepted before a link was disabled may still complete.

## 5 Give a gift to someone who has no account

### Jane buys care for her mum Linda Bennett

1. Jane opens **Buy a gift of care** from a Care hours screen, or opens LoLo's `/care-hours/gifts` page.
2. She selects **10 hours** for **$310**. The package shows **+ 1 free welcome hour**, subject to Linda's family-account eligibility when she claims. Jane enters her own name and email, requests a six-digit code and verifies her email. Her package selection stays selected through verification and resending a code. The public journey does not require an account; the current private pilot still requires the approved signed-in account.
3. She reviews her selected package, accepts the gift terms and selects **Continue to payment**.
4. After confirmed payment, **Your gift is ready** shows the gift code and a claim-page link.
5. Jane selects **Copy code** and sends the code and claim link privately to Linda, for example in a Thanksgiving card or personal message.

LoLo does not ask Jane to look Linda up by email or automatically email the gift to Linda. Jane controls how she shares it. The receipt email links Jane back to her private gift receipt; it does not put the gift code in the email itself.

Keep the code private: whoever claims it first adds it to their family account. It can be claimed once and cannot be split between family accounts.

### Linda enters the code and claims her hours

1. Open the claim link, or choose **Have a gift code?**. The public claim page is `/care-hours/claim`.
2. Enter the code and select **Continue**.
3. Create a free family account with your name, email and password, or sign in. Your code is kept for the next step.
4. Review the paid hours, any **Free welcome hours**, and **Hours to add** to your family balance.
5. Accept the terms and select **Claim my gift**.
6. **Your care hours are ready** shows the hours added and available family balance. Choose **Find care** or **View my care hours**.

Linda needs no card, phone number, care profile or care request to claim. There is no recipient chooser. Claiming does not create a self-care profile, publish a request or book a visit. **Find care** opens the normal request setup, where she can arrange care for herself or another person in her family.

If Linda's family account is eligible for the welcome offer, Jane's 10-hour gift adds **11 hours**. Otherwise it adds 10 paid hours. The review confirms the exact total before Linda accepts.

### Claiming when you already manage a loved one's care

Use the same steps while signed in to your family account. The hours go into your existing family balance and can cover eligible care for any of its recipients. Check that you are signed in to the intended family account before confirming: claimed hours cannot move to another family account.

## 6 Request care using your hours

1. Open **Care** and start a new care request. After a gift claim, **Find care** opens the same normal request setup.
2. Select the correct recipient and enter the care, location and schedule.
3. In **Payment when you book**, eligible available hours are selected automatically as **Use care hours first**. The option shows your available family hours.
4. Review the estimate. It shows care hours covered and any additional estimated payment. For a recurring request, it estimates the first week and reminds you that the balance is shared across visits.
5. If you prefer your card, select **Use card instead**. LoLo remembers that choice through posting and reopening the request.
6. Publish the request. No hours are reserved or spent at this point.

The same family balance can cover any eligible recipient, including someone added during request setup. Changing the recipient does not require linking another balance. An explicit **Use card instead** choice remains selected when you change recipients.

If the hours option is missing, check the family’s available balance and the care arrangement’s eligibility. Reserved, held or unavailable hours cannot cover a new visit. Custom-price and shared-care arrangements keep their existing payment flow.

## 7 Review the caregiver and confirm the visit

When you choose a caregiver, review **Care with [caregiver]**. The payment area shows **Scheduled care**, **From care hours** and **Remaining payment**. At this stage the labels are **Use care hours** and **Usual card payment**.

Choose **Confirm hire** only after checking the schedule, recipient and coverage. Confirmation reserves the planned care hours for the visit. It does not convert an existing card-funded visit to prepaid funding.

### When hours cover the full visit

Suppose Linda has 11 available hours and books a 2-hour visit. The review shows 2 hours from her balance and **$0 remaining payment**. A saved card is not required for fully covered scheduled care. Once booked, 2 hours are reserved and 9 remain available for other eligible visits.

### When there are not enough hours

Suppose Linda has 1 available hour and requests 2 hours of standard care. The review shows 1 hour covered and **$31 remaining payment** at the current standard price.

The visit may show a payment action before it is ready:

- Select **Pay $31.00** to cover the uncovered amount. This can be a one-time payment without saving a card.
- Select **Buy more hours** when sales are available, then return to the visit and select **Check coverage**.
- Review the visit again until it confirms **Scheduled care is covered**.

The donor's card is never made your backup card. Uncovered time follows the payment coverage shown for the visit. A purchase does not automatically save a card or change your normal billing method.

## 8 Complete care and approve the hours

The caregiver follows the normal check-in and check-out process. Open the visit to review the recorded time and any corrections before approving it.

For prepaid care, the approval review shows:

- **Care hours to use** for the approved care time.
- **Hours to return** if fewer hours are needed than were reserved.
- **Separate payment for uncovered time**, if applicable.

After settlement, the visit shows **Paid with care hours**, hours used and the remaining available balance. A separate payment is shown when uncovered time was paid. If additional approved care is still unpaid, the visit displays the amount due and a **Pay** action.

For example, a 2-hour reservation with 1 hour 30 minutes of approved billable care uses 1 hour 30 minutes and returns 30 minutes to the family's available balance. More care than planned can use additional available hours; any uncovered approved time is shown for payment follow-up.

Approving hours starts the normal payment process. It is not a promise that the caregiver's bank payout has already arrived. The caregiver's agreed care rate is preserved; package-processing fee allocation is handled by LoLo during settlement and does not remove extra minutes from the family's balance.

## 9 Recurring care and schedule changes

A recurring arrangement contains individual visits. The hour balance is shared across them; buying 10 hours does not pay every future occurrence indefinitely.

Eligible available hours can be preselected for a new recurring offer. The first visit review shows its actual coverage. Check later visits for reserved hours or payment actions as their funding is prepared.

An existing plan keeps its current payment preference until you change it. In the plan's **Details** area, expand **Payment for future visits**. Confirm and select **Use care hours for future visits** or **Use card for future visits**. The change applies only to future visits whose payment has not already been prepared. Already funded visits keep their payment arrangement.

For a longer proposed visit, LoLo may show **Cover extra time · $[amount]** before the schedule change can be accepted. The current visit remains protected. Paying for extra time does not itself accept the new schedule; the change still needs acceptance.

## 10 Cancellations and no-shows

There is no cancellation or no-show fee under the current product rules. When an undelivered prepaid visit is cancelled or marked as a no-show, its full reserved hour credit returns to the same family's balance, including bonus hours. The visit can show **Reserved hours returned**.

Returning hours is not a cash refund of the original package. If part of the visit used separate card funding, follow that visit's payment status for any release or reversal of the separate payment. Contact support if care was actually delivered or the recorded time needs correction; completed care is handled through the hours-review process.

## 11 Read the balance and history

| Label | Meaning |
| --- | --- |
| **Available** | Hours ready for another eligible visit |
| **Reserved** | Hours set aside for upcoming visits or care awaiting approval |
| **Temporarily on hold** | Hours unavailable while LoLo reviews a payment issue |
| **Contributions and usage** | Dated records of purchases and movements of hours, with visit references where relevant |
| **Purchases** | Recent package purchases and contributions associated with this family |

Your own purchases offer a **Receipt** link. Another person's purchase appears as **Contribution** in the family purchase list; it does not expose their private receipt. An outside contributor reopens their receipt using the purchase email and a fresh verification code when needed. Gift buyers use the same email-verification approach to reopen a private gift receipt.

One family balance covers eligible care for all its recipients. Older purchases and usage remain visible in the same family history, with their original financial records preserved. Owner or membership changes do not erase the balance; access follows the family’s permissions. Hours cannot be transferred to another family account.

## 12 Notifications you may receive

LoLo distinguishes purchase messages from visit updates. A contribution does not add the contributor to the family's visit notifications.

| Event | Who sees it | Message or action |
| --- | --- | --- |
| A new family account is created, including while claiming a gift | The new account holder | **Welcome to LoLo Care**, with help finding care; in-app and email follow normal preferences. This is separate from confirmation that gift hours were added. |
| Email verification requested for a contribution, gift purchase or private receipt | The entered buyer email | **Your LoLo care-hours verification code**, with a six-digit code and expiry |
| Direct purchase or private contribution succeeds and hours are issued | Buyer by email | **Your LoLo care-hours receipt**, with paid amount, hours and receipt link |
| Hours are added by purchase, contribution or gift claim | Active authorized family members in LoLo | **Care hours added**, linking to the family balance |
| An unassigned gift payment succeeds | Gift buyer by email | **Your LoLo care-hours gift**, linking to the private gift receipt |
| A package payment attempt needs attention | Buyer by email | Review-purchase message; no spendable hours are added for that incomplete attempt |
| A gift payment needs review | Gift buyer by email | Review the gift receipt before sharing it |
| Issued hours are put on hold | Active authorized family members in LoLo | **Care hours need review**, with a link to the balance |
| A prepaid visit has uncovered time or a payment-coverage problem | Active authorized family members | **Visit coverage needs attention**, linking to the visit; normal notification delivery preferences apply |
| Approved prepaid care is settled with no remaining amount due or payment hold | Active authorized family members | **Care hours used for your visit**, with hours used and any separate payment; in-app and email follow personal preferences |
| The caregiver's full positive net earnings have been transferred to Stripe | The caregiver | **Earnings available in Stripe**, linking to Earnings; bank arrival follows Stripe's payout schedule |
| An invalid purchase is corrected before hours were issued | Buyer by email | **Your LoLo purchase was corrected**, with the original-payment reversal and receipt link |

Normal care notifications continue for relevant booking, visit, hours-review, schedule and payment events. The family Notifications preferences include **Care hours need attention** and **Care hours used for a visit**. Family email preferences still apply to those standard visit updates; purchase receipts and buyer verification messages use the purchase email separately. The [notification reference](prepaid-care-hours-notifications.md), also included with the admin guide, lists every trigger and channel.

The gift buyer does not receive the recipient's care activity or a separate automatic claim-confirmation email. Their private receipt shows when a gift has been claimed without disclosing the recipient's care account. There is no expiry reminder because hours do not expire, and no automatic package top-up purchase.

## 13 If something does not look right

| Situation | What to do |
| --- | --- |
| You cannot see **Prepaid care hours** or **Buy prepaid care** | Check with LoLo whether new purchases are available. Existing balances remain accessible even when new sales are paused. |
| Your payment says **Payment processing** | Keep the same receipt, use **Check payment status**, and avoid paying twice. |
| Your gift says **Confirming your gift payment** | Use **Check payment**. A code is issued only after confirmed payment. |
| A verification code expired or was entered incorrectly | Request a new code using the purchase email. If asked to wait, respect the retry limit. |
| A gift code is rejected | Check the complete code. If it was already claimed, open your balance. For an unexplained error, ask the buyer for the purchase reference and contact support. |
| You left a gift claim open and it no longer resumes automatically | Reopen the claim page and enter the code again. The temporary signup handoff lasts one hour; the gift itself has no expiry. |
| You are signed in as a caregiver when claiming | Use a family account; the claim page offers **Sign out**. |
| You are signed in to the wrong family account before claiming or paying | Stop before confirmation and sign in to the intended family. Claimed hours cannot move between family accounts. |
| A contribution link is unavailable | Ask the family owner for a current link. Previously completed contributions remain valid. |
| Hours are reserved but you cannot use them for a new visit | Open the existing visit using them. The same hours cannot cover two bookings at once. |
| A prepaid visit needs more coverage after a top-up | Return to that visit and select **Check coverage**. |
| You see **Payment under review**, **Purchase needs attention**, or held hours | Use **Contact support** and quote the purchase or visit reference. Do not try to bypass the review with another account or duplicate purchase. |
| A custom-price warning appears | Contact support before buying or claiming. Prepaid hours apply to eligible standard care. |

For support, share the purchase reference, visit reference and a brief description of the problem. Keep gift codes and verification codes private.

## Source references

The user steps and labels follow the October 30 shared family-wallet implementation and terms version `2026-10-30-family-wallet`. These references are for maintainers updating the guide.

- [Care hours routes](../../routes/care-hours.php) and [package configuration](../../config/care_hours.php).
- [Family balance list](../../resources/views/care-hours/family/index.blade.php), [balance detail](../../resources/views/care-hours/family/show.blade.php), [package selection](../../resources/views/care-hours/partials/packages.blade.php), and [family controller](../../app/Http/Controllers/CareHours/FamilyCareHoursController.php).
- [Contribution page](../../resources/views/care-hours/public/contribute.blade.php) and [contribution service](../../app/Services/CareHours/CareHourContributionService.php).
- [Gift purchase](../../resources/views/care-hours/gifts/buy.blade.php), [gift receipt](../../resources/views/care-hours/gifts/receipt.blade.php), [code entry](../../resources/views/care-hours/gifts/enter-code.blade.php), [claim review](../../resources/views/care-hours/gifts/claim.blade.php), [ready screen](../../resources/views/care-hours/gifts/ready.blade.php), and [registration](../../resources/views/livewire/pages/auth/register.blade.php).
- [Request payment review](../../resources/views/livewire/family/partials/care-request-review.blade.php), [hire and completion review](../../resources/views/livewire/family/partials/care-decision-review.blade.php), [visit funding summary](../../resources/views/care-hours/partials/booking-summary.blade.php), [plan preferences](../../resources/views/components/care-hours-plan-funding.blade.php), and [schedule-change coverage](../../resources/views/components/care-hours-change-funding.blade.php).
- [Request defaults and identity](../../app/Services/CareHours/CareHourRequestFundingService.php), [care eligibility](../../app/Services/CareHours/CareHourEligibilityService.php), [purchase notices](../../app/Services/CareHours/CareHourPurchaseService.php), [gift notices](../../app/Notifications/CareHourGiftNotification.php), [purchase notices and receipts](../../app/Notifications/CareHourPurchaseNotification.php), and [verification email](../../app/Notifications/CareHourVerificationNotification.php).
