# Customer Success and prepaid care rollout

Status: implemented locally; deployment is performed by the owner after the reviewed changes are committed and pushed to master.

## Deployment

Do not deploy an uncommitted checkout. This release extends the prepaid compatibility inventory, so obtain the matching driver before the first deployment of this version. From the existing production checkout, after master contains the approved commit:

```bash
cd /var/www/homecare
git fetch origin master
git restore --source=origin/master -- deploy.sh
./deploy.sh
cd /var/www/homecare
./deploy.sh --status
php artisan care-hours:health --json
```

Use the existing production runtime setup described in [Atomic Production Deployments](zero-downtime-deployments.md). The driver applies the migration and prepares configuration, routes, views, assets and workers. No agent production access is needed.

Prepaid purchases, gift purchases and contributions already default to public access in this release. No separate activation command is required. Existing pauses remain authoritative: CARE_HOURS_PUBLIC_ENABLED and CARE_HOURS_PRIVATE_PILOT_ENABLED must both resolve to true (both default true when absent); enrollment, sales, contributions and welcome-bonus switches retain their own pauses. The driver does not edit the shared environment. If an intentional pause exists, the owner must decide whether to remove it before redeploying. Legacy CARE_HOURS_ENABLED / CARE_HOURS_GIFTS_ENABLED flags do not control this public release.

The new migration adds durable staff management, internal audit records, manual credit records and staff conversation read state; it allows credit lots without an online purchase. Do not reverse this migration. The matching driver rejects retained releases missing these dependencies, because older code cannot preserve customer silence or settle offline credits safely. Repair forward after the first activation; rollback becomes available only to another compatible release with matching prepaid exposure settings.

## Staff access

The workspace is at **/admin/customer-care**, under **Work queues → Customer Success** or **All tools → Care operations → Customer Success** for admins. Gift purchases and prepaid hours are grouped under **All tools → Communications & money**. SDRs use the **Customer care** menu; dedicated Customer Success staff have their care tools in the main navigation. Active admin and SDR accounts already have access. Dedicated Customer Success accounts use the customer_success role and land directly in the workspace.

To prepare a new staff login, run this interactively after deployment:

```bash
php artisan customer-care:create-user teammate@example.com --name="Teammate Name"
```

The command prompts for the initial password without echoing it and sends no email. It refuses to convert an existing customer account or revive revoked/blocked staff. Existing eligible staff retain their role and password. Give the teammate their login through your normal secure onboarding process. No real staff accounts are created by deployment.

Customer Success has customer search/account preparation, manual prepaid credits, managed requests and read-only gift purchase status. Refunds, claim secrets, payment recovery and broader administrator billing controls remain in the existing admin area.

## Removing a request

Use **Remove request** beside the request title and confirm a reason. Draft/open postings are withdrawn; applicants and invitations are closed using the existing lifecycle. The family receives no notification. Requests with an active recurring plan, unfinished visits, unapproved hours, disputes, or pending reviews must be resolved first.

Removal archives the request from active staff lists. Visit, payment, conversation, and audit records are retained. **Archived requests** is available on the workspace and each family account. **Restore request** returns it to those lists without reopening the posting or notifying the family. The additive `staff_archived_at` migration is applied by the deployment driver.

## Team workflow

1. Find a customer, or prepare an account without sending an invitation.
2. Record offline payment hours, amount received in USD, a payment reference and a reason. Credits have an immutable staff audit trail. The same family/payment reference or form submission cannot add hours twice. Credits do not create a Stripe charge or send a receipt. They also do not deposit the offline funds into Stripe; existing caregiver payouts need available platform funds.
3. Create a request, save a draft or post it immediately. Requests remain owned by the customer's family account and retain the actual staff creator. Staff creation does not record customer acceptance of terms.
4. Search/invite eligible caregivers, review and shortlist applicants, or record a placement agreed with a caregiver. Hiring requires enough prepaid hours for the first visit. Existing screening and work-eligibility rules apply.
5. Use the family-style request workspace tabs (Overview, Caregivers, Visits, Messages, Activity): caregiver messages, internal notes, upcoming visits, rescheduling, cancellations, actual times, approvals, disputes, schedule-change responses and time corrections. Recurring plans include pause, resume, end, schedule proposals and extra-visit requests. Caregivers still receive their normal operational communications and respond to proposals.
6. Use the workspace's caregiver reply inbox and attention queue for follow-up. Opening the Messages tab updates staff read state without marking it read for the customer. Recurring-visit conversations are grouped into the originating request.

Managed requests suppress customer email, in-app/database and broadcast notifications at creation and queued delivery. The setting follows future recurring visits. Taking over an existing request suppresses future messages, including queued messages; already delivered communications cannot be recalled. Customer login/security notifications and unrelated requests are unchanged. This is notification suppression, not hiding account records from their owner.

Managed hires use prepaid care to avoid customer checkout or authentication prompts. Credit more hours before approving an overrun. Existing card-funded visits and finalized payment adjustments continue through the administrator payment-operations workflow; taking over a request does not convert its existing payment method. Time corrections that require finalized financial changes are flagged for administrator review.

## Owner verification after deployment

- Sign in with an existing admin/SDR or the newly prepared Customer Success account and open /admin/customer-care.
- Confirm the new customer, request, credit and gift-status pages load.
- Confirm care-hours:health reports the expected public settings and healthy payment configuration.
- Verify gift purchase, buyer email verification, receipt delivery and redemption with your deployed mail/payment setup when ready. Local tests use captured mail and payment stubs and do not prove live inbox delivery or provider availability.

Local validation covers staff authorization, duplicate-credit protection, prepaid hiring and settlement, recurring changes, queued notification suppression, isolated staff inbox state and preservation of existing customer/payment workflows. Desktop/mobile browser checks use a separate SQLite fixture with provider calls disabled.

## URL directory

These URLs are derived from the application routes and the production hostname in the checked-in Nginx configuration. They have not been opened or checked on production. New routes become available after deployment. Sign in at [carelolo.com/login](https://carelolo.com/login).

There is no standalone /admin landing page; use [the dashboard](https://carelolo.com/dashboard) or the workspace links below.

| Page | URL | Access |
| --- | --- | --- |
| AI support overview | [admin/ai-support](https://carelolo.com/admin/ai-support) | Admin |
| AI support activity | [admin/ai-support/activity](https://carelolo.com/admin/ai-support/activity) | Admin |
| AI knowledge library | [admin/ai-support/knowledge](https://carelolo.com/admin/ai-support/knowledge) | Admin |
| Create knowledge entry | [admin/ai-support/knowledge/create](https://carelolo.com/admin/ai-support/knowledge/create) | Admin |
| AI support pilots | [admin/ai-support/pilots](https://carelolo.com/admin/ai-support/pilots) | Admin |
| Redirect to AI support settings | [admin/ai-support/readiness](https://carelolo.com/admin/ai-support/readiness) | Admin |
| AI support settings | [admin/ai-support/settings](https://carelolo.com/admin/ai-support/settings) | Admin |
| Care coverage calendar | [admin/analytics/care-coverage-calendar](https://carelolo.com/admin/analytics/care-coverage-calendar) | Admin |
| Caregiver coverage map | [admin/analytics/caregiver-map](https://carelolo.com/admin/analytics/caregiver-map) | Admin |
| Funnel analytics | [admin/analytics/funnel](https://carelolo.com/admin/analytics/funnel) | Admin |
| Usage analytics | [admin/analytics/usage](https://carelolo.com/admin/analytics/usage) | Admin |
| Care assistance applications | [admin/care-assistance](https://carelolo.com/admin/care-assistance) | Admin |
| Admin prepaid balances and operations | [admin/care-hours](https://carelolo.com/admin/care-hours) | Admin |
| Admin gift purchases and management | [admin/care-hours/gifts](https://carelolo.com/admin/care-hours/gifts) | Admin |
| Recurring care plans | [admin/care-plans](https://carelolo.com/admin/care-plans) | Admin |
| Caregiver moderation history | [admin/caregivers/moderation-logs](https://carelolo.com/admin/caregivers/moderation-logs) | Admin |
| Caregiver reviews and screening | [admin/caregivers/reviews](https://carelolo.com/admin/caregivers/reviews) | Admin |
| Content media library | [admin/content/media](https://carelolo.com/admin/content/media) | Content permission |
| Content posts | [admin/content/posts](https://carelolo.com/admin/content/posts) | Content permission |
| Content settings | [admin/content/settings](https://carelolo.com/admin/content/settings) | Content permission |
| Continuous coverage (feature access required) | [admin/continuous-coverage](https://carelolo.com/admin/continuous-coverage) | Admin |
| CRM | [admin/crm](https://carelolo.com/admin/crm) | CRM permission |
| Customer Success workspace / customer search | [admin/customer-care](https://carelolo.com/admin/customer-care) | Admin, SDR, Customer Success |
| Customer Success gift purchase status (read-only) | [admin/customer-care/gifts](https://carelolo.com/admin/customer-care/gifts) | Admin, SDR, Customer Success |
| Customer onboarding directory | [admin/customer-onboarding](https://carelolo.com/admin/customer-onboarding) | Admin, SDR, Customer Success |
| Family acquisition overview | [admin/family-acquisition](https://carelolo.com/admin/family-acquisition) | Admin |
| Family leads | [admin/family-leads](https://carelolo.com/admin/family-leads) | SDR permission |
| Family onboarding records | [admin/family-onboarding](https://carelolo.com/admin/family-onboarding) | Admin, SDR, Customer Success |
| CRM alias | [admin/leads](https://carelolo.com/admin/leads) | CRM permission |
| Admin notifications | [admin/notifications](https://carelolo.com/admin/notifications) | Admin |
| Payment operations | [admin/payments/ops](https://carelolo.com/admin/payments/ops) | Admin |
| Provider outreach AI | [admin/provider-outreach-ai](https://carelolo.com/admin/provider-outreach-ai) | Admin |
| All care requests | [admin/requests](https://carelolo.com/admin/requests) | Admin |
| Record a past visit | [admin/requests/record-past](https://carelolo.com/admin/requests/record-past) | Admin |
| SDR outreach | [admin/sdr-outreach](https://carelolo.com/admin/sdr-outreach) | Admin |
| SMS inbox | [admin/sms](https://carelolo.com/admin/sms) | Admin |
| Support tickets | [admin/support/tickets](https://carelolo.com/admin/support/tickets) | Admin |
| User directory | [admin/users](https://carelolo.com/admin/users) | Admin |
| Voice AI console | [admin/voice-ai](https://carelolo.com/admin/voice-ai) | Admin |

### Individual records

Open the corresponding list or customer page to select a real record. Replace braces with its identifier; gift purchases use the purchase public ID, not its database ID. Content previews additionally require a signed link generated by the app.

| Route | Purpose |
| --- | --- |
| /admin/ai-support/knowledge/{entry}/edit | Edit AI knowledge entry |
| /admin/care-assistance/{application} | Care assistance application |
| /admin/care-hours/gifts/{purchase} | Gift purchase details and admin controls |
| /admin/care-hours/{account} | Prepaid account audit and payment operations |
| /admin/care-plans/{carePlan} | Admin recurring plan details |
| /admin/caregivers/screening/{screeningCase}/audit | Screening audit export |
| /admin/content/posts/{blogPost}/edit | Edit content post |
| /admin/content/posts/{blogPost}/preview | Signed content preview |
| /admin/customer-care/customers/{customer} | Customer details, balance, manual prepaid credit form, request history |
| /admin/customer-care/customers/{customer}/requests/create | Create a managed request for this customer |
| /admin/customer-care/requests/{careRequest} | Manage the complete request workspace |
| /admin/customer-care/requests/{careRequest}/edit | Edit an eligible draft/open request |
| /admin/family-onboarding/{onboarding} | Family onboarding record |
| /admin/requests/recorded-visits/{recordedVisit} | Recorded visit details |
| /admin/requests/{careRequest} | Admin request details |
| /admin/support/tickets/{ticket} | Support ticket |
| /admin/users/{user} | User details |

### Related customer and public pages

| Page | URL |
| --- | --- |
| Prepaid information | [Prepaid care hours](https://carelolo.com/prepaid-care-hours) |
| Gift information | [Care gift cards](https://carelolo.com/care-gift-cards) |
| Buy a gift | [Gift checkout](https://carelolo.com/care-hours/gifts) |
| Redeem a gift | [Gift redemption](https://carelolo.com/care-hours/claim) |
| Customer prepaid balance | [Family care hours](https://carelolo.com/family/care-hours) |
| Buy prepaid hours | [Family prepaid purchase](https://carelolo.com/family/care-hours/buy) |

For manual credits, start in Customer Success, select the customer, then use **Add hours from an offline payment**. The credit endpoint is a POST form action, not a separate page. Contribution links and receipt links are generated for each transaction; do not invent or share their private tokens.
