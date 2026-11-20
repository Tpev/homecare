# Partner onboarding and directory

Partners register at `/partner/register` in three steps: business and contact details, services and coverage, then account creation and review. Signup creates a `partner` user and one `partner_profiles` record in a transaction. New profiles start with status `new` and open their dashboard immediately. Existing partners can sign in at `/partner/login` or the normal login page.

The partner dashboard is at `/partner/dashboard`, with available lead credits, latest delivered leads, service coverage, saved payment-method information and package choices. `/partner/leads` provides an owner-scoped, searchable inbox with contact details for delivered leads. `/partner/profile` edits the signed-in partner's business, contact details, service categories and coverage. Business contact email is independent of the account's sign-in email. Account settings retain the existing password and sign-in email controls.

## Admin directory

Open **All tools → People → Partner directory** (`/admin/partners`). Search business/contact details or cities, counties and ZIP codes. Combine service-category, state and status filters. Open a profile to review its details, set **New**, **Approved** or **Paused**, and save internal notes. Notes are only available to admins. The signup-link button copies the public onboarding URL for sharing.

Partners can still access their workspace and update their profile while new or paused. Only approved partners can purchase packages or receive new lead deliveries. Signup and approval do not send messages.

The selected partner's directory panel also supports lead delivery. Search an existing family lead, select a requested service offered by the partner, and confirm permission to share the contact and coverage. Sending a lead consumes one available paid credit in the same transaction. A contact can be delivered only once per service category across all partners; other requested categories remain eligible. Delivery snapshots and durable phone/email/source identity claims preserve this rule through CRM deletion, reimport and documented duplicate merges. The partner sees contact details and the selected service request, never internal CRM notes.

## Admin lead inventory

Open **All tools → People → Partner leads** (`/admin/partner-leads`), or **View partner leads** in the partner directory. The inventory lists all family leads, their contact/location details and requested services, including leads with no recorded partner service. Search contacts or locations, filter by requested service, and switch between all leads, available leads, leads with deliveries and leads without a partner service request. Counts reflect the current search and service selection before the delivery-status filter; a partially delivered lead can be both available and sent.

Choose **Review lead** or a service chip to open the delivery panel. Previously sent categories show the recipient and delivery date when retained. The panel offers approved, active partners who provide the selected service and have available paid credits, with their coverage and balance shown for review. Select a partner, confirm permission to share and geographic coverage, then send the lead to their dashboard. Sending uses one credit and the existing transactional exclusivity checks; it does not send an email. Changing the service or recipient clears the confirmations.

Availability uses durable identity claims, including historical CRM merge identities. A deleted delivery snapshot never makes the category available again. Contact restrictions, invalid contact details and unsupported service requests remain visible but cannot be sent. Historical imported answer formats are parsed before pagination so service filters do not silently omit matching leads. This view adds no new database schema and does not change existing lead answers.

## Billing and packages

`/partner/billing` uses the same Stripe-hosted card setup experience as family billing, with a separate partner Stripe customer. Saving a card verifies the Checkout session, successful SetupIntent and payment-method ownership before setting the default. Updating a card also updates the current monthly subscription's payment method. Card numbers and security codes never enter the app; only a display summary is cached for the dashboard.

Prices are defined on the server in `config/partners.php`:

| Package | Lead credits | Price | Renewal |
| --- | ---: | ---: | --- |
| One-time | 25 | $1,000 ($40/lead) | None |
| Monthly | 25 each month | $750/month ($30/lead) | Monthly until canceled |
| Annual | 300 | $7,500 upfront ($25/lead) | None |

Unused paid credits carry over until delivered and can be used across the partner's selected service categories. Annual credits are purchased upfront; delivery remains assigned by the team. Canceling monthly renewal takes effect at the end of the billing period and retains unused purchased credits. Recurring plans and pending checkouts are guarded against duplicate creation.

Invoices show the latest 50 records belonging to the partner's Stripe customer, with invoice status and Stripe-hosted PDF/receipt links. Billing lookup failures show a recoverable error rather than an empty-success state. Preview mode cannot create payments or paid credits.

Only verified paid-invoice webhooks issue credits. Returning from checkout never issues credits. Unique invoice, billing-period and upfront-purchase constraints prevent duplicate allocations. Failed/unpaid invoices issue nothing. Refunds, disputes and early fraud warnings hold remaining affected credits for review; delivery exclusivity is retained. Those holds require operator reconciliation before credit restoration.

## Deployment

Apply the additive billing and lead-delivery migrations before using the new pages. Reuse the application's existing Stripe credentials and signed webhook endpoint. Its event subscription must include `checkout.session.completed`, `checkout.session.expired`, `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed`, `invoice.paid`, `invoice.payment_failed`, `customer.subscription.created`, `customer.subscription.updated`, `customer.subscription.deleted`, `customer.updated`, `charge.refunded`, `refund.updated`, `refund.failed`, `charge.dispute.created`, `charge.dispute.updated`, `charge.dispute.closed`, and `radar.early_fraud_warning.created`. Existing family/prepaid event subscriptions remain required. Tax collection is not newly enabled by these package prices; Stripe Tax requires the account's tax setup and registrations to be verified separately.

## Data and access

- Categories are fixed keys in `PartnerOptions`: meal delivery, pharmacy pickup or delivery, home safety improvements, and estate planning and wills.
- Coverage combines one or more US states with either statewide coverage or specific cities, counties and ZIP codes entered as text. This is searchable profile information, not automated geographic matching.
- Only admins can access the directory or update status/internal notes. Partner components recheck role and account access and always retrieve the profile from the authenticated user.
- All signup fields are validated again on submission. Duplicate sign-in email addresses are rejected. Website links accept only HTTP(S).
- The additive migration `2026_11_17_000100_create_partner_profiles_table.php` must be applied before using these pages. No existing lead data is changed.

## Verification

Run the partner management, workspace, billing and lead-delivery feature suites, the family billing regressions, and `npm run build`. Browser review should cover signup, profile editing, directory delivery, dashboard/package pricing, lead filtering/details, billing failures, and narrow mobile layouts. Use an isolated preview database with fictional records and Stripe bypass; payment tests mock the provider.

Before committing changes to the shared dashboard, routes or authentication views, run `bash deploy.sh --check-prepaid-release "$PWD"`. If a reviewed change affects the prepaid source manifest, follow [local manifest maintenance](../deployment/zero-downtime-deployments.md#local-manifest-maintenance) and commit the refreshed manifest with the source changes.
