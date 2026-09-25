# Notification delivery

Chat messages and human support replies for families/caregivers appear in-app immediately. Email waits five minutes after the first pending message, groups the pending messages for that recipient and conversation, and sends only if still unread. Further messages can generate at most one email per conversation every 30 minutes. Email preferences and current access are checked again at dispatch. Admin incident and handoff alerts remain immediate.

Invitation-sent, application-submitted, and hire-confirmed acknowledgements are in-app only, including for accounts with older enabled email preferences. Notifications to the other party and payment-action emails keep their existing behavior.

Reading a conversation clears its related notifications and cancels pending email for that reader. Shared family members retain independent read state. Home and notification-center visits also reconcile older alerts against existing conversation read timestamps. Dashboard update links mark the selected alert read before opening its destination. The navigation badge refreshes when in-app alerts are read.

Operations new-request emails exclude `is_system_generated` records, including recurring and Continuous Coverage visits.

## Running and deploying

- Apply migrations normally; the September 25 migration adds delivery lookup indexes only.
- The existing Laravel scheduler must run every minute. It now runs `homecare:dispatch-unread-message-emails` without overlap. The existing worker continues serving other jobs; chat email timing does not depend on a queue driver's delay support.
- `config/notifications.php` contains the five-minute delay and 30-minute cooldown.
- Deferred delivery statuses are `pending`, `sending`, `sent`, `batched`, `suppressed`, `failed`, and `unconfirmed`. `sent` means transport acceptance, not inbox delivery. A transport failure is recorded and reported; uncertain sends are not retried automatically. An interrupted `sending` claim becomes `unconfirmed` after 15 minutes, allowing later new messages through after the cooldown.

Regression coverage: `tests/Feature/Notifications/NotificationNoiseReductionTest.php` plus existing notification, messaging, support, and shared-family tests.
