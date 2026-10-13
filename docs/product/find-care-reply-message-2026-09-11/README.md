# Clear caregiver response and hiring action

The Needs your attention card now identifies the caregiver and distinguishes accepted invitations from applications. Request 47 reads “Sofia Martinez accepted your invitation”, followed by “Review the response, then choose Hire and confirm your caregiver.” The link is labelled “Review & hire” and opens the existing applicants tab without hiring anyone.

For multiple applicants, the card shows the reply count and how many accepted an invitation. Only accepted invitations linked to an active application for the same request and caregiver count toward that message. Closed and past-date requests retain their existing handling.

Applicant cards show the next-step sentence even in the compact overview. A single attention card fills the row; two or three cards use the existing column styles. Other action types keep their compact content.

Verified the real local request 47 at 1440px, 390px and 320px, plus navigation to its applicant list. No browser errors or overflow. Screenshots are in `screenshots/`; results are in `validation.json`. Browser checks did not hire, invite, or update care records.

Six focused regression tests passed, 66 assertions, using SQLite in memory. Coverage includes accepted invitations for one-time/recurring requests, organic applications, mixed replies, inactive or mismatched invitation links, historical applicants, the existing review link, and unchanged care records.
