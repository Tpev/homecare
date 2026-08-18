# Family Operations Knowledge Base Wave 1

Status: Implemented in source; deploy and run the exact publication command before claiming production coverage

Approved: August 18, 2026

Decision: `DEC-075`

## Outcome

This wave gives the Family assistant a broad, governed day-to-day operating manual before additional write tools are added. It contains 50 new stable entries and one corrective revision to `KB-FAM-004`. The package links 217 entry-to-intent relationships across 190 unique rows in the 324-intent Family registry and carries 255 deterministic knowledge evaluations.

It does not enable the assistant for more users, change the Pilot/Everyone setting, add a domain mutation, quote pricing, or change payment processing. The existing two-user pilot remains unchanged.

## Package inventory

| Domain pack | Entries | Covered subjects |
| --- | ---: | --- |
| Billing, care payments, and payment recovery | 9 | Secure card changes, permissions, authorization/capture, pending/failure states, paying submitted hours, receipts, refunds, and disputes |
| Requests, applicants, messages, and hiring | 10 | Statuses, applicant review, comparison, invitations, messaging, hiring, editing, withdrawal, duplication, expiry, and availability boundaries |
| Visits, problems, and submitted hours | 10 | Visit state, changes, caregiver changes, late/no-show, safety, reviewing and correcting hours, approval, review, and rebooking |
| Care profiles | 8 | Purpose, readiness, identity, non-medical notes, contacts, keeping information current, defaults/archive, and reuse |
| Family access | 3 new plus one revision | Roles, invitations, removal/leaving, ownership boundaries, and the current active-member secure payment-method flow |
| Messages and notifications | 4 | Conversation location, safe communication, unread state, and preferences |
| Regular care | 4 | Status, offers, changes, pause/resume, and ending care |
| History and rebooking | 2 | Understanding history, rebooking, documents, and correction handling |

The executable source is `resources/ai-support/knowledge-base/family-operations-v1.php`. The exact stable-ID inventory is enforced by `FamilyOperationsKnowledgeBaseCatalog`; missing, extra, reordered, malformed, non-Family, or unresolved-navigation definitions fail closed.

## Corrected Family payment permission

The previous `KB-FAM-004` text said only the Account owner could change the saved payment method. That is stale. The current deployed product deliberately lets any active Family member enter the secure shared Family payment-method flow. The correction preserves the boundaries that only the owner manages invitations, removal of other members, ownership, and closure.

Chat may use only safe card metadata. Full card numbers, CVCs, payment tokens, bank-authentication details, passwords, and verification secrets never enter chat. Opening the secure flow is not proof of success; a fresh authorized state read is required.

## Pricing hold

This package does not map `FAM-PAY-028` or `FAM-PAY-029`, does not publish `KB-CARE-006`, and does not authorize an hourly-price or fee answer. The existing pricing reconciliation remains separate.

## Evaluation contract

Every one of the 51 definitions has exactly five linked cases:

1. a grounded Family answer;
2. a near-boundary request that must not be over-answered;
3. a Caregiver wrong-role request;
4. a removed-member request that must expose no private state; and
5. an explicit request for a person.

The frozen catalog is `resources/ai-support/evaluations/family-operations-kb-v1.php`. It contains 255 unique cases. The feature tests also prove every linked Family intent exists in the human registry and that the held pricing intents are absent.

Source verification on August 18, 2026 passed the complete AI Support suite at 139 tests / 1,277 assertions. The existing Batch 1-2 plan also remained green at 40 intents, 122 phrases, 10 collision cases, zero provider calls, and an isolated in-memory database.

## Deployment and publication

Deploy the committed source through the normal `deploy.sh` process. Then inspect the read-only plan:

```bash
php artisan ai-support:import-family-operations-kb
```

Expected on the first production run is 50 creates, one revision, and zero conflicts. If an Admin has already created or edited one of these exact stable IDs, the command refuses to overwrite it and identifies the conflict.

Publish the exact approved package in one operation:

```bash
php artisan ai-support:import-family-operations-kb \
  --publish \
  --actor-email=test@test.com \
  --reason="Publish approved Family Operations KB Wave 1." \
  --confirm=PUBLISH-FAMILY-OPERATIONS-KB
```

The command creates validated drafts, creates the corrective `KB-FAM-004` version, runs the normal submit/approve/publish lifecycle under the named Administrator, and publishes all exact package entries. A repeated exact run is a no-op. The command changes no availability control, pilot grant, or Family domain record.

## Verification

After publication:

- Admin Knowledge Base should show 50 new Published stable entries and a new Published version of `KB-FAM-004`;
- `KB-CARE-006` must remain in its existing held state;
- Availability must still say Pilot only unless an Administrator separately changes it;
- both pilot users should receive grounded answers for supported day-to-day questions without invented current state;
- personalized claims must still come from the Batch 2 authorized readers, not KB prose; and
- mutation requests without an implemented confirmed tool must navigate/guide or transfer, never claim completion.

Only after this production verification should the Explain column in the Family coverage registry be promoted for the mapped rows. Action coverage does not change merely because knowledge was published.

## Next implementation slice

Proceed to Batch 3A: executable intent catalog, universal active-task follow-ups, personalized task launcher, explicit intent-to-KB retrieval, outcome verification interface, unmatched/loop telemetry, and mass tests that combine the 40 existing read/guide intents with this 190-intent knowledge map.
