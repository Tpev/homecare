# Care Assistance: Customer Success guide

## Rates

An enrolled family's new Care Assistance requests cost **$23.00 per approved hour total**, with no additional hourly customer processing charge. Caregivers earn **$20.00 per approved hour before Stripe processing fees**. LoLo's share is **$3.00 per hour**. The existing payment flow deducts the actual Stripe processing fee from caregiver gross earnings; do not promise a fixed net hourly payout.

For example, two approved hours cost the customer **$46.00**. Caregiver gross earnings are **$40.00**, less the actual Stripe fee shown in earnings. The existing card-authorization buffer is a temporary hold, not an extra hourly charge. Final billing follows approved minutes, rounded to cents.

## Activate the first approved customer

1. Sign in as **Customer Success**, **SDR**, or **Admin**, and open [Customer Success](https://carelolo.com/admin/customer-care).
2. Find the customer by name or email. If needed, use the workspace's account-creation form first. Open the customer's account and verify the payer/family; the applicant may be a different family member.
3. Open **Care Assistance → Activate Care Assistance**.
4. Select the approved application. Guest applications can be linked here even when the applicant did not have an account. Applications linked to another family cannot be reused. If approval happened outside the application form, select that option and record the approval reference in the reason.
5. Check **I verified the approval belongs to this family account**, add the approval reference/reason, and click **Activate Care Assistance**.
6. Verify **Active for new requests**, the $23/$20 rates, and the linked application. Activation is recorded with the staff member, timestamp and reason.

Approving an application and activating account pricing are separate steps. The [application review queue](https://carelolo.com/admin/care-assistance) and application detail page show the activation handoff. Approval alone does not change prices. Activation does not send an email or text; contact the customer through your normal approved follow-up process.

## Post and staff the request

1. From the enrolled customer account, click **Create a request**.
2. Confirm the **Care Assistance** badge and **Customer $23.00/hour total · Caregiver $20.00/hour before Stripe fees** notice.
3. Enter the care recipient, needs, tasks, location and one-time or recurring schedule. Save a draft or click **Post request**.
4. Invite caregivers or review applicants through the normal request workspace. Caregiver job details, invitations and recruitment emails identify Care Assistance and the $20/hour gross rate. Financial hardship details and application notes are never copied into the job.
5. Confirm that the caregiver accepts the program rate. Hire through the usual hire review, or use **Visit tools → Record an agreed placement** when the placement was agreed outside the app.
6. Choose the **family's saved card**. If the customer has no saved card, use the existing customer-onboarding payment setup workflow before completing hiring. Prepaid care hours and gift-hour balances cannot fund Care Assistance jobs.
7. Verify the booking shows the program rate. Staff-managed requests retain the existing customer-notification suppression; caregiver invitations continue through the usual flow.

Customers who post their own new requests also receive program pricing automatically. The program belongs to the whole family account and applies with every caregiver; staff do not select a discount separately for each request.

## During and after care

Use the normal visit tools for schedule changes, check-in/out, actual-time recording, approving hours, cancellations, disputes and time corrections. Program bookings use the same charge, transfer and earnings services as other bookings, with their saved program rates. Recurring generated visits, extra visits under that plan, and shared-care caregiver schedules retain the source request's program terms.

Confirm actual hours before approval. Customer billing and caregiver gross earnings are prorated to approved minutes; the actual Stripe processing fee is deducted from caregiver earnings at settlement. Payment issues are handled through the usual payment/support workflow.

## Existing work and ending participation

**Activation applies to newly created requests and plans.** It does not reprice existing drafts, open postings, accepted bookings, recurring plans or historical payments. If an approved customer already has a standard-priced request, check it before staffing. Create a new request after activation to offer Care Assistance; use the normal withdrawal/cancellation process for obsolete postings. Do not assume editing or reopening an older request changes its pricing.

To end participation, open the customer account, expand **End participation for new requests**, enter the reason, and click **End Care Assistance for new requests**. New requests return to the applicable standard pricing. Existing assistance requests, drafts, bookings and recurring plans keep their agreed $23/$20 terms, including future visits generated under those plans. To change ongoing care, coordinate with the family and caregiver and use the normal end/cancel and new-request workflow.

Ending enrollment does not cancel care, change hours, capture a payment, refund money or send customer messages. Re-enrollment creates a new history entry; earlier work keeps its original agreement.

## Common messages

| Message or situation | What Customer Success should do |
| --- | --- |
| Approved, but account pricing is not active | Open the correct customer account and activate Care Assistance, selecting the approved application. |
| Account already has custom pricing | Ask an administrator to review the existing arrangement. Care Assistance deliberately does not overwrite it. |
| Application already linked to another family | Check that you opened the right account and selected the right application. Do not create a duplicate enrollment to bypass this check. |
| Care Assistance changed in another session | Reload the account, check the latest status/history, and submit only the action still needed. |
| Prepaid hours cannot fund this request | Use the family's saved card. Existing prepaid balances are retained. |
| Cannot change an active application's approval | End participation on the linked customer account first; existing work will keep its terms. |
| A visit needs its agreed pricing restored | Ask an administrator to investigate the saved booking/payment terms. Do not substitute a standard-rate charge. |

## Release notes for the operator

Deploy the complete reviewed change through the normal release process, including migration `2026_11_25_000100_add_care_assistance_enrollment`, the new model concern, enrollment service and views. Run the normal migrations and asset build. The prepaid compatibility source inventory/manifest must match this release. Do not copy only the controller or templates.

The migration adds enrollment provenance and request/plan references; it does **not** enroll any live customer or change existing bookings. After deployment, Customer Success activates the approved customer using the steps above. Do not roll back this migration after program records have been used; preserve the pricing references and release a forward correction instead.

Focused automated validation: `php vendor/bin/phpunit tests/Feature/CustomerCare/CareAssistanceWorkflowTest.php tests/Feature/Admin/CareAssistanceReviewTest.php`. These checks use an isolated test database and simulated payments; they do not charge live cards or send messages.

Release validation completed on November 25, 2026: 339 unique PHP tests passed across the relevant feature suites and final regression runs. The desktop and mobile browser walkthroughs both passed activation, request posting and ending participation while retaining the posted request's pricing (`npx playwright test --config playwright.care-assistance.config.ts`). The production asset build and `bash deploy.sh --check-prepaid-release .` also passed. This validation used local test data; production deployment and customer activation remain separate operational steps.
