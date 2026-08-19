# Care Profiles and Request Lifecycle — Batch 5

Status: Deployed, published, and active for the exact two-user pilot; profile lifecycle and Admin production audits passed on August 19, 2026; natural-language request correction `aa0a63d1` is pushed for deployment and final request-lifecycle verification

Approved: August 18, 2026

Owner: Product and Engineering

Availability: The existing two Family pilot users only after activation; **Live for everyone remains off**

## Outcome

Batch 5 lets a Family user manage the ordinary care-profile and care-request lifecycle in the same support conversation. The assistant can read the signed-in user's current state, collect safe information over multiple messages, show a plain-language recap, let the user modify it, require a clear confirmation, execute a narrow existing domain operation, re-read the database, and return a verified receipt.

The language model interprets ambiguous profile wording only. Identity, Family Account membership, resource ownership, allowed fields, readiness, request state, confirmation freshness, writes, and completion proof remain deterministic application responsibilities.

This batch does not enable AI Support for everyone. Deployment alone changes neither Availability nor the two pilot grants.

## Approved product behavior

### Care-receiver profiles

For an authorized active Family member, the assistant can:

- list the account's active or archived profiles and explain readiness;
- create a private profile draft from ordinary conversation;
- add or change allowlisted profile fields over multiple turns;
- show all proposed changes in a visible recap before saving;
- create or update the profile only after explicit confirmation;
- save an incomplete profile so the Family can finish later;
- mark a complete profile ready for requests;
- choose the default profile;
- archive or restore a profile after explaining the effect; and
- verify the saved revision and state before saying the action is complete.

The profile draft is encrypted, actor-bound, Family-bound, editable, replaceable with a fresh recap, and unusable after confirmation, expiry, logout, transfer, or a conflicting revision. A stale confirmation never overwrites a newer profile.

### Care requests

The existing one-time and recurring request journey remains the primary creation path. Batch 5 adds:

- exact authorized status, blocker, and applicant-count reads;
- reuse of a previous request as a new private draft;
- duplication of an open request without changing the original;
- a fresh copy of an expired or withdrawn request rather than reopening it;
- replacement-draft behavior for changes to a live request or request type;
- source disclosure on copied details;
- clearing schedule fields that must be freshly confirmed;
- continuation from validation or stale-confirmation errors without retyping everything;
- confirmed withdrawal of an eligible open request; and
- an authoritative receipt after withdrawal or publication.

Retries remain idempotent. The assistant never treats page arrival, a user saying “done,” or a model-generated sentence as evidence that a request changed.

## Boundaries

- Permanent care-profile deletion transfers to a person. The chat never permanently deletes a profile.
- A profile change never silently changes an active visit or booked care. Current-care changes transfer for review.
- A request with an existing visit is not withdrawn through this tool; the visit workflow remains authoritative.
- A withdrawn or expired request is copied into a new private draft and is never reopened in place.
- A live request is not silently edited. The assistant prepares a reviewed replacement and preserves the original.
- Medical-task requests and emergencies follow the existing safety path. Emergency instructions come before transfer.
- 24/7 coverage remains human-led, with no queue status, wait-time, availability, or staffing promise.
- Cross-account, wrong-role, inactive-member, stale-resource, missing-resource, duplicate-confirmation, and denied-action cases fail closed without disclosing private state.

## Knowledge package

The versioned `profile-request-kb-v1` package contains:

| Content | Count |
| --- | ---: |
| Governed entries | 20 |
| Linked evaluations | 100 |
| Care-profile intent mappings | 26 / 26 |
| Care-request intent mappings | 45 / 45 |
| Total unique profile/request mappings | 71 |
| Total explicit Family catalog mappings after Batch 5 | 230 / 230 |

The entries cover profile purpose and visibility, permissions, fields, readiness, default selection, current-care effects, archive/restore/deletion boundaries, request types, drafts, publication meaning, status, applicants, blockers, reuse, replacement, withdrawal, expiry, recovery, and availability promises.

Publication uses the existing single-Administrator KB workflow. It does not change pilot access, Availability, profiles, or requests.

## Runtime contracts

Batch 5 adds the `family_lifecycle_action_v1` capability and six narrow controls:

- `family-profile.save-draft`;
- `family-profile.make-ready`;
- `family-profile.make-default`;
- `family-profile.archive`;
- `family-profile.restore`; and
- `care-request.withdraw`.

Each consequential operation follows the same sequence:

1. authorize the current actor, Family Account, and exact resource;
2. read and lock the current revision or lifecycle state;
3. prepare an allowlisted change;
4. show a deterministic recap and **Modify something** option;
5. require explicit confirmation;
6. re-authorize and reject stale or conflicting state;
7. call the existing domain service inside the confirmed action; and
8. re-read the database and issue a compact verified receipt.

There is no generic SQL, ORM, arbitrary URL, selector, browser-control, or unrestricted write tool.

## Test baseline

Current completion baseline, passed locally on August 19, 2026:

- Batch 5 lifecycle contract: **23 tests / 148 assertions**;
- Batch 5 knowledge package: **3 tests / 112 assertions**;
- Family Batch 1–5 mass harness: **94 tests / 2,405 assertions**;
- complete AI Support feature suite: **186 tests / 3,214 assertions**;
- executable catalog: **324 / 324** records and **230 / 230** explicit KB mappings;
- deterministic routing: **137 / 137** existing phrases plus **64 / 64** Batch 5 lifecycle phrases;
- nearby collision protection: **10 / 10**;
- profile/request KB evaluations: **100 / 100** structurally validated;
- interactive Chromium suite: **7 / 7** serial journeys, including profile create/edit/readiness/default/archive/restore; exact request read/withdraw/copy/republish/status; payment guidance; exact-user isolation; and Admin evidence;
- responsive support-chat suite: **6 / 6** Pixel/Chromium and **6 / 6** iPhone/WebKit scenarios passed independently, including background-refresh focus, rotation, draft preservation, offline recovery, long history, large text, and resolved-chat restart;
- production frontend build: passed; and
- provider calls and production database writes during the deterministic mass test: **0**.

The focused Batch 5 tests include multi-turn model extraction; wrong-account profile and request denial; stale profile revision; expired recap renewal; independent default/archive/restore confirmation and database verification; distinct reuse, duplicate, live replacement, request-type replacement, and withdrawn-copy modes; exact status, blocker, and applicant reads; copied-draft validation recovery and fresh publication; duplicate/idempotent behavior; request-with-visit denial; permanent-deletion transfer; and exact-pilot-only activation.

The completion audit also corrected two test-found integration defects. A replacement highlighted DOM control now reinitializes guidance once without stealing focus on unrelated DOM changes. The deterministic browser fixture now explicitly separates the standalone `Arthur E2E` request recipient from the seeded `Rosa Existing` profile, preventing a false linked-profile snapshot during copy and publication.

## Authenticated production pilot audit — August 18, 2026

Production showed **Pilot only**, exactly two active Family grants, and 111 published KB entries plus one unrelated draft. `KB-B5-PROFILE-001` was directly inspected as Published. The content-free activity log showed successful Batch 5 capability-extension events for both pilot users. **Live for everyone remained off.**

The `peverelli.t@gmail.com` pilot then completed a fresh authenticated Family journey after the previous human-only test ticket was resolved:

- the assistant correctly read that the account had no active care receiver profile;
- **Open care receiver profiles** navigated to `/family/care-profiles`, kept the chat state, displayed the guidance strip, and applied the visible four-pixel target outline;
- the assistant created the synthetic `Batch Five Test Profile` only after recap and confirmation;
- **Modify something** let the user change the proposed name before confirmation;
- mobility and routine details supplied in two separate messages were accumulated into one recap, confirmed, saved, re-read, and reported as verified;
- the profile was marked ready only after its own explicit recap and confirmation;
- at 390 × 844 the chat occupied the full viewport, reopened at the newest message, retained focus while typing character by character, and rendered the request action at full mobile width; and
- the assistant read request `#9` as **Caregiver selected**, explained that its selected caregiver moves changes into the visit or regular-care workflow, and opened the exact request page with a guidance strip.

The smoke test found three real defects:

1. **Modify something** called an undefined `autoResize` helper after correctly prefilling the composer.
2. Guidance telemetry called `.catch()` on a Livewire result that can be `undefined`, producing a browser error after otherwise-correct navigation.
3. `Archive the Batch Five Test Profile now that the pilot test is complete.` was incorrectly treated as a make-ready request because the incidental word `complete` outranked the explicit verb `archive`.

No incorrect lifecycle recap was confirmed. The archive recap was stopped and the synthetic profile remains isolated from active requests and visits until the corrected archive journey is verified after deployment.

The correction replaces the missing resize call with the existing composer state handler, safely normalizes the Livewire result through `Promise.resolve`, and gives explicit archive/restore/default verbs priority over readiness wording. Regression coverage includes the exact production archive sentence. The completed August 19 source now passes:

- the production frontend build;
- **7 / 7** complete interactive Chromium journeys;
- **6 / 6** independent Pixel/Chromium and **6 / 6** independent iPhone/WebKit responsive scenarios;
- **18 / 18 tests and 127 assertions** in the focused Batch 5 lifecycle contract;
- the complete AI Support suite with **180 tests / 3,187 assertions**; and
- the isolated Batch 1–5 mass harness with **88 tests / 2,378 assertions**, **64 / 64** Batch 5 phrases, **324 / 324** catalog records, **230 / 230** mappings, and **10 / 10** collision cases.

After the completion commit is deployed, the production recheck is:

1. click **Modify something** and confirm the composer remains focused with no console error;
2. open an exact guided request and confirm navigation, highlight, and telemetry complete with no browser error;
3. repeat the exact archive sentence, confirm the recap says **Archive**, confirm it, and verify the synthetic profile is archived;
4. edit, make ready, make default, archive, and restore a disposable pilot profile through separate recaps and verified receipts;
5. read an eligible request, withdraw it, create a fresh copy, supply a new schedule, publish it, and verify the new request ID differs while the source remains withdrawn;
6. inspect the Admin evidence for the separate publication and lifecycle receipts; and
7. confirm Availability is **Pilot only**, exactly the two approved Family grants remain active, and **Live for everyone** is off.

### Completion-deployment correction — August 19, 2026

Production served the completion bundle from commit `788deecf`, but the Admin AI Support overview returned HTTP 500 while its Availability, Pilot users, Knowledge base, Activity, and ticket-evidence pages remained healthy. The failure was not caused by production data or the Batch 5 runtime. The authoritative Family coverage registry had changed without regenerating the source checksum stored in `resources/ai-support/intents/family-v1.php`; `FamilyIntentCatalog` correctly failed closed on that mismatch.

Commit `463291e5` regenerates the exact checksum and adds a regression assertion for the bounded searchable intent surface. It also keeps the overview lean by aggregating 30-day outcome counts in SQL, loading only the 30 newest outcome rows, and rendering at most 50 matching intent rows at once. Before push, the complete AI Support suite passed **180 tests / 3,188 assertions** and the isolated Family Batch 1–5 harness passed **88 tests / 2,379 assertions**, **137 / 137** routing phrases, **64 / 64** Batch 5 phrases, **324 / 324** catalog records, **230 / 230** KB mappings, and **10 / 10** collision cases with zero provider calls or production writes.

The same authenticated Admin session confirmed **Pilot only**, exactly users `19` and `282`, **Live for everyone off**, 111 published KB entries including the complete Batch 5 package, and then resolved transferred pilot ticket `#33`. After the normal deployment of `463291e5`, the overview loaded its 324-intent / 230-mapping surface and recent outcome aggregates with zero browser errors. The final Family lifecycle production journeys remain and require an authenticated session for one of the two exact pilot users.

### Authenticated Family lifecycle and natural-language correction — August 19, 2026

The `peverelli.t@gmail.com` pilot completed the full disposable profile lifecycle in the production chat with zero browser-console errors: create, **Modify something**, save, edit, make ready, make default, archive, and restore all produced their own recap and checked receipt. The original `Batch Five Test Profile` archive sentence also completed correctly. No active request, visit, payment, or timesheet was changed by those profile checks.

The realistic request sentence `Create a one-time care request for Batch Five Final Profile ...` then exposed a deterministic collision: the terminal word `Profile` inside the recipient name won the broad profile-create matcher, so the assistant asked for a care-receiver name instead of starting the one-time-care path. The same browser round found that ordinary `finish and edit` / `add that` profile wording was less conversational than the explicit `Update ... to say:` form, and the explicit form retained an instructional `say:` prefix in the saved detail.

Commit `aa0a63d1` corrects all three findings and adds recovery for an incomplete profile change:

- explicit one-time and recurring request language resolves before profile-name wording, and the exact production sentence is covered end to end as a care-path choice with no profile preparation or profile write;
- `finish`, `edit`, and `add` keep the user inside the authorized multi-turn profile draft, while a generic edit request asks what should change instead of treating the command itself as profile content;
- an instructional `say:` prefix is removed from the proposed About Them value;
- the composer shows **Cancel current profile change** whenever a profile preparation is active; cancellation saves nothing and invalidates any earlier confirmation for that preparation; and
- cancelled or expired profile preparations are rejected again inside the locked confirmed-action commit.

The post-correction source baseline is **186 / 186 AI Support tests with 3,214 assertions**. The isolated Family Batch 1–5 mass harness passes **94 / 94 tests with 2,405 assertions**, **137 / 137** established phrases, **64 / 64** Batch 5 phrases, **324 / 324** catalog records, **230 / 230** mappings, and **10 / 10** collisions with zero provider calls or production database use. Final closure requires the normal deployment of `aa0a63d1`, cancellation of the mistaken production draft, and repetition of the exact request lifecycle in the authenticated pilot.

## Production deployment

Run the normal deployment first:

```bash
./deploy.sh
```

Then publish the exact Batch 5 knowledge package and activate its actions for the existing two-user pilot:

```bash
php artisan ai-support:import-profile-request-kb \
  --publish \
  --actor-email=test@test.com \
  --reason="Publish approved Batch 5 profile and request lifecycle knowledge for the two-user pilot." \
  --confirm=PUBLISH-PROFILE-REQUEST-KB

php artisan ai-support:activate-batch5-pilot \
  --actor-email=test@test.com
```

The activation command refuses to run if **Live for everyone** is on or if active pilot access is not exactly the configured two-user cohort. It extends only those two grants, enables only the Batch 5 capability and six tools, and prints `Live for everyone: Off` on success.

Optional read-only verification:

```bash
php artisan ai-support:test-family-intents --plan --batch=5
php artisan ai-support:monitor-health
```

No Batch 5 database migration is introduced. The KB and activation commands do not alter Stripe, payments, visits, timesheets, existing profiles, or existing care requests.

## Two-user pilot smoke script

Use both Family pilot accounts across these checks:

1. `Help me create a care profile for Maria.` Add at least two details in separate messages, modify one detail from the recap, confirm, and open the verified profile.
2. Ask what is missing before a draft profile is ready; supply the missing information, confirm **Make ready**, and check the resulting state.
3. Make a different ready profile the default, then archive and restore an eligible non-default profile.
4. Ask for the status of an existing request and how many caregivers applied; open the exact request.
5. Duplicate or reuse a previous request; confirm that the source is disclosed, the original is unchanged, and a fresh schedule is requested where required.
6. Copy an expired or withdrawn request and confirm the old request is not reopened.
7. Withdraw an eligible open request, review the impact recap, confirm, and verify the status receipt.
8. Attempt permanent profile deletion and 24/7 care; both must transfer to a person without an AI write.
9. From the other pilot account, attempt to reference the first account's profile or request; no state or action must be exposed.
10. Confirm Admin can see the conversation, recap/receipt evidence, and published Batch 5 KB entries while Availability remains **Pilot only**.

## Next batch

After the two-user smoke round and correction of any real wording or state failures, proceed to **Batch 6 — Applicants, messaging, and hiring**. That batch should start with governed KB for applicant facts, invitations, message delivery, rejection, hiring prerequisites, booking/payment confirmation, and failure recovery before adding its narrow readers and actions.
