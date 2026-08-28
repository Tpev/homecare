# Current Family Layout Production Browser Audit

Status: Source corrections implemented; production deployment and authenticated retest pending

Audited: August 28, 2026

Scope: Family AI Support two-user pilot; no Caregiver AI work and no general release

## Outcome

The authenticated production audit exercised the Family assistant against the current Care workspace on desktop and a 390 × 844 mobile viewport. Core chat behavior, authorized account reads, exact-record links, page highlighting, request-type recommendation, private drafting, and mobile conversation anchoring worked. No live care request was created; the audit-only private request draft was discarded after explicit confirmation.

The audit also found deterministic routing and presentation defects caused by the newer Care navigation. This correction batch makes those behaviors explicit instead of leaving them to lexical or model selection.

## Production behaviors that passed

- The pilot assistant opened in automated mode and clearly offered human transfer.
- Enter submitted a message; unsent composer text retained focus and its full value during refresh.
- The mobile panel filled the viewport, kept the composer visible, and stayed at the newest message.
- Care Overview, Find Caregivers, Billing, submitted hours, Care History, Care profiles, and Family access resolved to authorized current pages.
- Care Overview, caregiver search, billing, submitted hours, history, and care-profile pages exposed their registered `data-ai-target` markers.
- Billing highlighted the secure card-management control without requesting card details in chat.
- Submitted-hours answers read the signed-in Family Account and opened exact care records without approving anything.
- A natural recurring need was correctly recommended as recurring care and collected the recipient, schedule, tasks, start date, and authorized account address into a private recap.
- Pricing returned the approved hourly breakdown.
- No live request, payment, profile, visit, invitation, or other domain write occurred during the audit.

## Defects found and source corrections

| Production observation | Correction |
| --- | --- |
| “Where can I find and compare caregivers?” showed request applicants | Generic find, browse, search, and compare language now routes to `family.caregivers`; applicant language remains request-specific. |
| “Open my care schedule,” “Open Care Actions,” and “Open Care Arrangements” produced unrelated choices | Each current Care destination now has a deterministic command path and exact guided target. |
| The one-time-versus-recurring help choice could fall into unrelated Support Center content | An explicit care-type question now starts a real care-request goal, replaces a stale unrelated goal, and offers one-time, recurring, unsure, and human choices. |
| A previous payment task remained visible during a new explicit navigation goal | Explicit current-layout navigation cancels the stale goal and its open guided task before offering the new destination. |
| Notifications could report a missing highlight shortly before its marker appeared | Client target discovery now waits up to twelve seconds for slower Livewire/mobile navigation before reporting failure. |
| Two submitted-hours buttons were both named “Review hours” | Multi-record actions now include the visit subject/date in each button label. |
| The desktop guide strip overlapped the floating Support launcher | Desktop layout now reserves the launcher area instead of centering underneath it. |
| “What does LoLo do?” opened the Support Center | The governed orientation entry now offers the current Family Care Overview first. |
| “two-hour visit” did not show the two-hour total | Pricing recognizes written one- through twelve-hour durations; two hours returns the approved $60 Family total. |
| A completed recurring draft failed after “change the start time to 10:00 AM” | Explicit start-time and duration edits now update the private draft deterministically, invalidate the old recap, and issue a fresh recap without a provider call. |
| Payment-history and other informational questions inherited or created unrelated long-lived goals | Durable goals now start only for actionable work; read-only answers and guided destinations do not replace the user’s current task. |
| “Cancel a booked visit” could stop the current chat guide, while cancellation-policy questions transferred to a person | Only an explicit standalone request to stop the guided task cancels it. Visit policy questions now route to the cancellation-policy intent and explain the 24-hour window without inventing a fee or refund. |
| A care-profile purpose question returned only profile counts | Purpose, contents, and visibility questions now route to the governed care-profile explanation before offering Care profiles. |
| Notifications and Care profiles opened correctly but sometimes reported that the highlight was missing; Livewire navigation logged Alpine errors | The route-dependent inner Livewire key now matches the outer component key, so a navigation mounts one fresh Alpine chat instance and target discovery observes the new page. |

## Known product state intentionally not changed

- The application’s visible payment-fee implementation may differ from the approved pilot KB truth. This batch changes AI Support only: $30/hour Family total, $27/hour caregiver gross less actual Stripe processing fees, and a $3/hour gross LoLo platform portion. It does not change payment or Stripe implementation.
- The recurring request recap did not expose **Confirm and create request** because pilot publishing controls were not all enabled. The existing `ai-support:activate-batch5-pilot` command now idempotently enables the two publish commits, both publish tools, and `care_request_publish_v1` for the exact two pilot grants while keeping general release off.
- Provider instability previously triggered the automatic answer stop. The capability was restored for the pilot, and subsequent audited turns completed, but health monitoring remains relevant.

## Regression coverage

- `FamilyGuidedAssistanceTest` covers exact current-layout commands, the caregiver-directory/applicant boundary, and unique multi-record hour labels.
- `Batch10FamilyGoalJourneyTest` covers replacing a stale payment goal with actual one-time/recurring path choices.
- `PaymentTimeKnowledgeContentTest` covers written-duration pricing math.
- `FamilyExperienceKnowledgeAlignmentTest` covers the current navigation registry, page markers, and idempotent KB publication.
- The generated 324-intent catalog remains sourced from the human-readable registry and keeps the Care Overview as the first destination for Family orientation.

## Production retest after deployment

Run the normal deployment, publish the one changed KB definition through the existing realignment command, then repeat these exact pilot prompts:

1. `Open Care Actions.`
2. `Open my care schedule.`
3. `Open my Care Arrangements.`
4. `Where can I find and compare caregivers?`
5. `Compare the caregivers who replied to my request.`
6. `I need help deciding whether I need one-time care or recurring care.`
7. `How much does care cost for a two-hour visit?`
8. `Which submitted hours are waiting for me to review right now?`
9. `Where do I change my notification preferences?`

Expected: exact current pages, visible highlights without false missing-target messages, distinct record buttons, real care-type choices, and a $60 two-hour Family total. Keep Live for everyone off throughout the retest.
