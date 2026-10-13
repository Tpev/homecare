# Withdrawal and care timeline

Local reference: http://127.0.0.1:8028/family/requests/44

Withdraw request is now directly beside the status in the recruitment header, available across its stages whenever the existing draft/open and no-booking eligibility permits it. The former hidden Request options control was removed. The confirmation explains that applications stop, active applications and pending invitations close, affected caregivers are notified, and history stays available. Keep request receives initial focus; Escape and Keep request both dismiss the dialog and return focus to the trigger. The existing withdrawal service retains authority over ownership, eligibility, updates, and notifications.

Care story has been renamed Care timeline throughout the family request, visit, and recurring-care navigation. It is a milestone overview of care selection, visits, reviewed hours and payment, rather than a complete invitation or message log. Its introduction now says that directly. Back to request/recurring care returns to the record summarized by the timeline.

Successful withdrawal now closes any stale hiring review or invitation preview and focuses the request navigation. A rejected withdrawal preserves drafts and displays the existing validation message. Closed requests without a booking use factual timeline copy and no longer mark scheduling as underway. Eight targeted regression scenarios verified these behaviors along with ownership, booked-request eligibility, withdrawal side effects, and existing booked/recurring timelines. No withdrawal-service rules changed.

The frontend build and browser checks passed at 1440px, 390px and 320px. Header action visibility, confirmation content, cancellation, Escape, focus return, no overflow, timeline naming and the exact return URL were verified. The browser did not submit a withdrawal or send any messages.

- [Browser results](validation.json)
- [Desktop request header](screenshots/request-1440.png)
- [Mobile confirmation](screenshots/confirmation-320.png)
