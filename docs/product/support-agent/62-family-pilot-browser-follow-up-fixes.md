# Family Pilot Browser Follow-up Fixes

Status: Second production browser audit completed; deterministic follow-up corrections implemented and verified in source; deployment and final authenticated retest pending

Date: August 28, 2026

Scope: Exact two-user Family pilot only. General release remains off. No Caregiver AI work and no payment-domain behavior change.

## Why this follow-up exists

The authenticated browser audit found four remaining gaps after the current-layout correction batch:

1. A complete recurring request recap could omit **Confirm and create request** even though one-time confirmation worked.
2. `Where do I change notification preferences?` opened Notifications without targeting the Delivery preferences panel.
3. A cancellation-policy question asked during another guide could be mistaken for a new-care question and replace the active guide.
4. The care-profile explanation described access boundaries but did not tell a Family what useful information to include.

## Corrected behavior

### Recurring request confirmation

`ai-support:activate-batch5-pilot` now has explicit postconditions for both pilot grants and every Batch 5 capability, commit control, and tool control. The operation rolls back and fails clearly if either one-time or recurring confirmation is still unavailable. Its result table explicitly reports both confirmation paths as enabled. It still refuses to run when **Live for everyone** is on or when the active cohort is not exactly the configured two users.

### Notification preferences guidance

The deterministic resolver recognizes ordinary location questions such as `Where do I change notification preferences?`. This is a navigation request, not an instruction to change a setting, so the assistant does not invent email/in-app values or show a confirmation recap. It offers a **Notification preferences** button that opens Notifications, scrolls to the exact Delivery preferences panel, opens the collapsed panel, highlights it, and focuses it.

### Cancellation questions during a guide

Booked-visit cancellation, rescheduling, and review questions are excluded from the new-care type decision path. A cancellation-policy question now receives the governed 24-hour-window explanation while the existing payment, profile, or other guided task remains active. The assistant does not ask `one date or every week?`, replace the guide, guess a fee, or call the model for this deterministic question.

### Useful care-profile contents

Both canonical profile overview articles now explain that useful shareable details can include:

- preferred name and communication style;
- daily routine;
- mobility or accessibility needs;
- comfort preferences and interests;
- household context;
- non-medical safety notes; and
- the kinds of daily assistance that help.

The articles also say to include only relevant information the Family is comfortable sharing, keep it current, and avoid diagnoses or clinical instructions. Existing Family-account and candidate/assigned-caregiver visibility boundaries remain intact.

## Knowledge publication

Both changed care-profile articles are part of the 55-entry `family-experience-alignment-v1` package. After deployment:

```bash
php artisan ai-support:realign-family-kb \
  --publish \
  --actor-email=test@test.com \
  --reason="Publish the Family pilot follow-up guidance for useful care-profile contents."
```

The command changes KB versions only. It does not change care, payment, user, or pilot-access records.

Then restore and verify the complete Batch 5 pilot capability set:

```bash
php artisan ai-support:activate-batch5-pilot --actor-email=test@test.com
```

The expected output includes `One-time request confirmation — Enabled`, `Recurring request confirmation — Enabled`, and `Live for everyone — Off`.

## Production browser acceptance

Use a pilot Family account and keep **Live for everyone** off:

1. Build a valid recurring request draft through chat. The complete recap must show **Modify something** and **Confirm and create request**. Do not click Confirm unless intentionally creating a test request.
2. Ask `Where do I change notification preferences?`, use **Notification preferences**, and verify the page opens directly on the expanded, highlighted Delivery preferences panel.
3. Start a payment-method or care-profile guide, then ask `What happens if I need to cancel a booked visit?`. The cancellation policy must be answered and the earlier guide must remain available.
4. Ask `What should a care profile contain?`. The response must mention practical content such as communication, routine, mobility, comfort, safety, and daily assistance, plus the sharing boundary.

## Automated verification

- Full AI Support and Support feature suites: 281 tests and 6,265 assertions passed.
- Final routing and guide-preservation subset: 44 tests and 2,084 assertions passed after broadening the natural-language cases.
- Notification location questions route deterministically and create a guided task for `family.notifications.preferences`, with no setting-change recap.
- Cancellation-policy questions bypass care-type intake, make no provider call, preserve the active guide, and create no replacement path-choice action.
- The Family Notifications page renders the new semantic target, and frontend guidance opens `<details>` targets before highlighting them.
- Both care-profile articles are verified against the required practical-content and clinical-boundary language.
- Batch 5 activation verifies both pilot grants, one-time confirmation, recurring confirmation, and all registered lifecycle tools while keeping general release off.

## Rollback

- Use the Admin **Emergency stop** for immediate AI shutdown.
- Keep **Pilot only** selected to prevent access beyond the two configured pilot users.
- Disable `capability.care_request_publish_v1`, `commit.one_time`, `commit.recurring`, and the two request-publication tools to return to recap-only request preparation.

## Second production browser audit

The August 28 production retest started from an automated pilot conversation and used the signed-in Family account without confirming or publishing a request.

Verified in production:

- Enter submitted the message.
- The composer cleared immediately while the response was processing.
- Care-profile navigation reached the current Care profiles page and displayed the active guide.
- Notification-preference guidance reached Notifications, highlighted the exact Delivery preferences section, and gave the correct floating instruction.
- A cancellation-policy question did not start care intake and did not replace the notification guide.
- No care request was created.

The retest also exposed four deterministic gaps:

1. `Stop this task` with no active goal fell through to the model and transferred the conversation to a person.
2. A Livewire refresh could remove the `open` state from the highlighted Delivery preferences `<details>` element after arrival was recorded.
3. A complete recurring-care sentence was not applied to the new private draft after the user accepted the recommended care type, causing the assistant to repeat questions already answered.
4. A subsequent weekday answer depended on a provider extraction and transferred after a repeated provider/contract failure.

The source correction now handles a no-task Stop request without a provider or handoff. It also extracts the ordinary request-intake fields deterministically from the user's message, including recipient, recognized care tasks, weekdays, start date, start time, duration or time range, and simple step-by-step address answers. The exact production sentence now reaches a complete recurring recap with zero provider calls and still creates no live request until the Family explicitly confirms.

The frontend now reopens a guided `<details>` target after any Livewire morph and reinitializes the target after the server records arrival. This preserves the expanded Delivery preferences panel as well as its highlight and instruction.

The useful care-profile article was present in source but the production answer still reflected the previously published version. The KB realignment publication command remains required after this deployment.

### Second-audit automated verification

- Full AI Support, Support chat, and Family notification regression selection: 276 tests and 6,236 assertions passed.
- Exact complete recurring production sentence: ready-for-recap, correct recipient, both care tasks, Monday schedule, October 19 start, 9:00 AM–12:00 PM, zero provider calls, and zero care requests.
- No-active-task Stop: automated reply retained, zero provider calls, zero handoff, and zero care requests.
- Production asset build passed.

## Final production acceptance after deterministic intake deployment

The deployed deterministic corrections passed the authenticated pilot acceptance:

- `Stop this task` with no active task returned the safe no-change answer in under one second and kept the conversation automated.
- The published care-profile answer named communication, routine, mobility, comfort, non-medical safety, daily assistance, clinical boundaries, and candidate/assigned sharing boundaries.
- A cancellation-policy detour returned the governed 24-hour-window explanation without care-type intake and preserved the notification guide.
- The complete recurring-care sentence reached the recap immediately after **Continue with recurring care**. The recap contained Production Test Recipient, Companionship, Daily living assistance, Monday at 9:00 AM for three hours, the October 19 start date, **Modify something**, and **Confirm and create request**.
- Confirm was not selected. The private draft was discarded, its receipt appeared in chat, and the Care overview still showed `Being arranged: 0`.

One remaining frontend race was reproduced: the Delivery preferences target received the correct highlight and instruction, but the Livewire arrival refresh removed the `<details open>` attribute. The final source correction observes changes to the `open` attribute as well as the existing class and guided-data markers, so a highlighted details target is reopened after a DOM morph. A final deployment and focused notification-panel retest remain pending.
