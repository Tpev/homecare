# Family Pilot Browser Follow-up Fixes

Status: Implemented and verified in source; production deployment and authenticated retest pending

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
