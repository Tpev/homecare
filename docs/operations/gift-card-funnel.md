# Gift-card funnel and OPS notifications

The gift purchase admin page at `/admin/care-hours/gifts` includes date-filtered event totals, session conversion, gross confirmed sales and recent OPS delivery status. Existing gift purchase search and status filters remain separate from the funnel's date range.

## Deployment

1. Deploy the application changes and build the frontend assets with the existing deployment process (`npm run build`).
2. Run the new migration with the normal production migration process (`php artisan migrate --force`). It creates `care_hour_gift_events` and records the start of tracking; it does not backfill old visits or purchases.
3. Keep the existing OPS configuration: `SLACK_OPS_ENABLED` and `SLACK_OPS_WEBHOOK_URL`. No new webhook or Slack credentials are required. Do not put webhook values in logs or documentation.
4. Restart queue workers through the existing deployment process. Ensure the Laravel scheduler and queue worker are running. The scheduler invokes `ops:gifts:dispatch` every minute to recover pending deliveries.
5. Use `php artisan ops:gifts:dispatch --status` for a read-only delivery summary. The same command without `--status` dispatches pending notifications.

No production migration, payment, email, Slack delivery or deployment is performed by the automated test suite or the local preview.

## Recorded events

| Event | Source and meaning | OPS notification |
| --- | --- | --- |
| `landing_view` | GET `/care-gift-cards`; repeat page loads counted separately | No |
| `shop_view` | GET `/care-hours/gifts` after access checks | No |
| `verification_requested` | Accepted gift-buyer verification request; not a guarantee of email delivery | No |
| `verification_completed` | Successful code verification | No |
| `checkout_submitted` | Validated purchase created or recovered using its request key | No |
| `checkout_started` | Payment provider checkout session created; not proof that the hosted page rendered | Yes |
| `payment_confirmed` | First accepted provider-confirmed successful payment | Yes |
| `payment_failed` | First recorded provider payment failure for a purchase; retry may later succeed | Yes |
| `checkout_expired` | Provider checkout expired without payment | No |
| `gift_claimed` | Successful claim and credit to the recipient's family account | Yes |

Purchase events are unique by event type and purchase, verification events by event type and verification ID. A second valid purchase is a separate gift. Duplicate requests, webhook retries and reconciliation do not duplicate milestones or their notification records. Receipt-code recovery is not counted as a new gift-buyer verification.

Checkout recovery records a missing checkout-created milestone only when this tracker already recorded that purchase's checkout submission. Opening an old receipt does not manufacture a new checkout. Recorded event time is when the application first observes the event; delayed webhooks or recovery can therefore appear after the original provider action.

## Counting and attribution

- Dates are inclusive calendar dates in Europe/Paris, implemented as UTC half-open boundaries with daylight-saving adjustments. Today's activity stops at the observation time.
- Activity totals count events recorded in the selected dates. Known Stripe test purchases and their linked events are excluded. Unknown-mode checkouts remain visible until their mode is confirmed. Anonymous test visits cannot always be distinguished from normal visits.
- Unique visitors are anonymous Laravel session identifiers, not verified people. The same session persists through authentication if its data is preserved; expired cookies, another browser or another device may create another session. No IP address, email, name, OTP, gift code or checkout bearer URL is stored in the event table.
- HEAD requests, known bot/preview user agents, prefetches and signed-in staff page views are excluded. This is not perfect bot detection. Counts represent server page loads; browser-cache restores and upstream cached responses may not reach the application.
- Conversion starts with a session's first recorded shop visit in the chosen dates. It follows matched checkout, paid and claimed outcomes through the observation time, including outcomes after the selected date range. Each session counts once per step even if it buys multiple gifts. Recent visitors are not classified as lost.
- Landing-to-shop uses first landing-page visits in the chosen dates. Verification is a separate diagnostic because a buyer with a valid verification token may skip that step.
- A claim keeps the buyer's acquisition session, even if the recipient is a different person. Purchases without a matching session still appear in event totals and sales; they do not inflate session conversion.
- Confirmed sales are gross payments before fees, later refunds or disputes. These are event metrics, not current gift balances. The existing purchase details show current payment and hour status.
- Historical visits are unavailable before the migration. If a chosen range includes that period, the panel labels its coverage as partial.

## Notification reliability

Notifications use the existing `SlackOpsNotificationService::deliverManagedPayload` transport and OPS webhook. They contain gift hours, amount, purchase reference, payment mode, Paris event time and an authenticated admin link. They do not contain buyer contact details or secret codes.

The event row is also a delivery outbox. Notifications are queued only after the surrounding transaction commits. A unique event record and locked delivery claim prevent duplicate workers or webhook retries from sending the same event twice.

- `pending`: ready or waiting for retry. Explicitly retryable HTTP rejections allow up to four delivery attempts.
- `sending`: a worker has claimed the event.
- `sent`: Slack returned its expected `ok` acknowledgement.
- `disabled`: OPS was disabled or unconfigured when the event was recorded; enabling it later does not automatically flood Slack with these older events.
- `failed`: definitive rejection with no remaining automatic retry.
- `uncertain`: connection/acknowledgement outcome is unknown, or the worker stopped during delivery. It is **not automatically replayed**, since Slack may already have received it. Check OPS before any deliberate recovery.

The admin panel shows recent statuses and flags failed/uncertain notifications. Analytics and Slack failures do not fail a purchase or claim. Tracking errors log only their exception type; no payment secrets are logged. Review operational logs if event coverage is incomplete despite successful sales.

## Validation

`tests/Feature/CareHours/GiftFunnelTest.php` covers the HTTP buyer journey, view/session deduplication, bot/staff exclusion, webhook/payment and claim deduplication, failed-payment callbacks, recovered checkout handling, Paris daylight-saving date boundaries, cohort maturity, test-mode exclusion, admin access, missing-schema fallback, Slack retries, uncertain deliveries and transaction rollback. All provider, mail, queue and Slack interactions are faked.
