# Care Profiles and Request Lifecycle — Batch 5

Status: Implemented and verified in source; awaiting normal production deployment, KB publication, and exact two-user pilot activation

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

Completed locally on August 18, 2026:

- Batch 5 lifecycle contract: **12 tests / 57 assertions**;
- Batch 5 knowledge package: **3 tests / 112 assertions**;
- Family Batch 1–5 mass harness: **82 tests / 2,308 assertions**;
- complete AI Support feature suite: **174 tests / 3,117 assertions**;
- executable catalog: **324 / 324** records and **230 / 230** explicit KB mappings;
- deterministic routing: **137 / 137** existing phrases plus **63 / 63** Batch 5 lifecycle phrases;
- nearby collision protection: **10 / 10**;
- profile/request KB evaluations: **100 / 100** structurally validated;
- interactive Chromium suite: **5 / 5** scenarios, including the mobile profile recap, confirmation, receipt, and Admin audit;
- responsive support-chat suite: all **6** scenarios passed, with one keyboard-wrap case rerun after an initial timing-only failure;
- production frontend build: passed; and
- provider calls and production database writes during the deterministic mass test: **0**.

The focused Batch 5 tests include multi-turn model extraction, wrong-account denial, stale profile revision, expired recap renewal, duplicate/idempotent behavior, request-with-visit denial, withdrawn/expired copy behavior, permanent-deletion transfer, and exact-pilot-only activation.

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
