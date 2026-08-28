# Family Experience and Knowledge-Base Realignment

Status: Deployed and published for the two-user Family pilot; August 28 browser corrections pending deployment

Implemented: August 27, 2026; production package published August 28, 2026

Scope: Family AI Support only; pilot membership and Live for everyone are unchanged

## Why this batch exists

The Family product changed after the original support knowledge was written. The separate Family Home dashboard was retired, Home and Care were consolidated, the Care workspace gained Overview, Actions, Schedule, Arrangements, and History, the visible term became **Recurring care**, marketplace pricing was released, notification preferences moved to Notifications, and public caregiver discovery became a first-class destination.

Routes still existed for several old destinations, so route-existence tests alone incorrectly treated stale guidance as valid. This batch aligns answers, buttons, highlights, executable intent contracts, and evaluations with what a Family user now sees.

## Current Family navigation contract

| User goal | AI target | Product destination |
| --- | --- | --- |
| Understand or open the Family home | `family.care_requests` | Care Overview |
| See decisions or problems needing attention | `family.care_actions` | Care Actions |
| Review upcoming visits | `family.care_schedule` | Care Schedule |
| Review recurring care or care being arranged | `family.care_arrangements` | Care Arrangements |
| Follow one request end to end | `family.request.journey` | Exact request Care Journey |
| Follow one recurring plan end to end | `family.recurring_care.journey` | Exact recurring-care journey |
| Browse marketplace caregivers | `family.caregivers` | Find Caregivers |
| Open one current public caregiver | `family.caregiver_profile` | Exact public profile by slug |
| Review notification state and preferences | `family.notifications` | Notifications |

`family.dashboard` and `family.regular_care` remain compatibility aliases for already-published tasks, but both resolve to current Family experiences. New intent and KB content uses the current targets.

Every destination above has a matching `data-ai-target` marker. Exact request, plan, and caregiver-profile targets are server-authorized before URL generation. An inactive or removed caregiver profile cannot be opened through an AI task.

## Knowledge corrections

The source catalogs were audited entry by entry. The realignment package contains all 51 definitions changed by the audit, including:

- Family home and Care orientation;
- one-time versus recurring-care selection and intake;
- the released pricing truth: $30 care plus $1 processing, $31 Family total, $27 caregiver gross less actual Stripe processing fees, and $4 LoLo gross portion per worked hour;
- Notifications as the location for marketplace notification preferences;
- Find Caregivers as the generic caregiver-browsing destination;
- exact caregiver-profile and recurring-care destinations from Care History;
- human Support as the Family personal-account deletion path;
- **Recurring care** wording across Family KB answers, titles, facts, examples, and product areas.

The package reads the canonical definitions from the existing source catalogs rather than duplicating them. On production it:

1. creates any canonical package entry that is absent from an older production import;
2. updates the never-published legacy pricing draft in place;
3. creates a new governed version for each changed published entry;
4. refuses to restore deleted entries or overwrite any unrelated Admin draft already in progress;
5. validates every changed version;
6. publishes the package in one transaction;
7. becomes an exact no-op when run again.

It never changes AI availability, pilot grants, users, care records, payment records, or notification records.

Production publication created the one missing canonical entry, updated the one unpublished draft, revised 49 published entries, and published all 51 definitions. An immediate second plan returned 51 exact no-ops and zero conflicts. The executable intent plan returned 324 of 324 registry intents and 324 of 324 explicit KB mappings.

## Intent and evaluation changes

- The executable registry remains exactly 324 of 324 explicitly KB-mapped intents.
- `FAM-START-003` and `FAM-START-004` now describe and open the current Care Overview.
- Generic account-attention guidance opens Care Actions.
- Generic recurring-care guidance opens Arrangements.
- Generic caregiver discovery opens Find Caregivers.
- History intents 008 and 009 now open an authorized public caregiver profile or exact recurring-care journey when those records exist.
- The deep deterministic corpus is refreshed to August 27 and now contains 47 cases, including three phrases each for Care Overview explanation and navigation.

## Production operation

Deploy normally, then inspect the package:

```bash
php artisan ai-support:realign-family-kb
```

The plan should report updates/revisions or exact no-ops and zero conflicts. Publish it with the existing Administrator account:

```bash
php artisan ai-support:realign-family-kb \
  --publish \
  --actor-email=test@test.com
```

Then run the read-only mass plan:

```bash
php artisan ai-support:test-family-intents --plan
```

Expected invariants are 324/324 registry intents, 324/324 explicit KB mappings, 47 deterministic runtime cases, a passing routing precheck, and zero provider calls.

## Regression contract

The automated suite now verifies more than route existence:

- each current Family page renders its registered highlight marker;
- the retired dashboard alias resolves to Care Overview;
- exact request and recurring-plan journeys include their required static route type;
- caregiver-profile routing uses the current public slug and rejects inactive profiles;
- Family targets are rejected for Caregiver users;
- the KB package handles published revisions, the unpublished pricing draft, publication, and idempotent reruns;
- source catalogs, generated intent mappings, and the refreshed routing corpus remain internally consistent.

Source verification completed on August 27:

- complete AI Support feature suite: 243 tests and 6,007 assertions passed;
- current Family Care, Care History, and Caregiver discovery regressions: 35 tests and 259 assertions passed;
- mass intent plan: 324/324 catalog intents, 324/324 explicit KB mappings, 47/47 deterministic runtime cases, 86/86 Batch 6–7 cases, 118/118 Batch 8–9 cases, routing precheck passed, and zero provider calls.

After deployment and KB publication, use a pilot account to ask where the Family home is, open Actions, Schedule, Arrangements, Find Caregivers, Notifications, and one exact Care Journey. This browser pass must not create, publish, hire, approve, or pay for anything.
