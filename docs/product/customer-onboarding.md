# Customer onboarding

Admin workspace: `/admin/customer-onboarding`, under **Acquire families → Customer onboarding**.

## Staff flow

- Search all family accounts and unlinked family leads. Linked leads and account members do not create additional household rows.
- Open a family to see account, saved-payment, published-request, and first-hire milestones.
- Use **Add family** or **Create family account** to prepare a family owner account. Existing eligible owner accounts are reused; conflicting roles, closed accounts, shared memberships, and duplicate CRM associations require resolution.
- Generate a payment setup link. Copy it or display its locally generated QR code during the visit.
- The family accepts the payment terms and enters details in Stripe-hosted Checkout without logging in. Setup saves a method; it does not charge the family.
- A verified Stripe webhook makes the method the family's default, records completion, and closes the link. The open admin workspace refreshes completion automatically.
- Account access is separate. **Send account access email** explicitly sends the existing password setup/reset email. Creating an account or saving a card does not verify email, accept other terms, or finish care intake.
- Staff can add internal notes and open existing care requests, care intake, and welcome-visit details.

## Status rules

Account creation, payment readiness, request publication, and hiring are independent. Publishing and first-hire milestones remain historical after requests expire or care ends. Draft and system-generated requests do not count as published family requests. A CRM conversion alone does not count as hiring.

Payment readiness requires a known reusable default payment method. A Stripe customer ID alone is not evidence of a saved card. Unknown statuses are displayed explicitly. Expired cards do not show as ready. Directory reads use stored summaries; detail refreshes check Stripe, and the scheduled refresh updates existing families in batches.

## Payment links

Links use random 256-bit tokens, hashed for lookup and encrypted for admin retrieval. They are scoped to one family, expire after seven days, and close after successful setup. Revocation or regeneration disables previous links and attempts to expire their Stripe sessions. Stripe events for revoked sessions cannot change the default payment method.

Opening a link does not consume it. Checkout starts only after consent. Repeated starts reuse the current session, and transient failures reuse idempotency parameters. Completion validates the account, customer, stored Checkout session, SetupIntent, payment method, and consent before changing billing. Delayed webhook delivery can complete a valid session that finished before its Stripe expiry.

The public page exposes no care records or account login, uses privacy headers, and contains no analytics or external QR service. Browser-return query parameters cannot mark a card as saved.

## Deployment and operation

1. Install the locked PHP/Node dependencies and run `npm ci && npm run build`.
2. Run the application migrations, including `2026_10_20_000100_create_family_payment_setup_links`.
3. Keep the existing signed Stripe webhook endpoint receiving `checkout.session.completed`.
4. Keep Laravel's scheduler running. `customer-onboarding:refresh-payments --limit=100` runs hourly and refreshes unknown or day-old payment summaries. It can also be run manually for an initial refresh.

Stripe bypass never fabricates guest payment success. In that mode the guest flow ends at pending confirmation; verified Stripe test fixtures exercise completion in automated tests. No production Stripe calls or family emails are needed for automated verification.

## Validation

Feature tests live in `tests/Feature/Admin/CustomerOnboarding*Test.php` and `tests/Feature/Payments/FamilyPaymentSetupLinkTest.php`. The dedicated Playwright configuration is `playwright.customer-onboarding.config.ts`; it creates only a guarded, isolated QA SQLite database with synthetic families, fakes outbound notifications, and checks desktop/mobile views plus the guest-link journey.
