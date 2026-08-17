# Simplified AI Support Availability

Status: Implemented; awaiting normal production deployment

Accepted: August 17, 2026

Decision: `DEC-072`

## Outcome

AI Support now has three operating states:

1. **Pilot only** — at most two exact users with active pilot grants can use AI Support.
2. **Live for everyone** — one Administrator switch makes AI Support available to every supported Family and Caregiver user without individual grants.
3. **Emergency stop** — Human Only immediately stops automated replies while the same chat remains available to users and human support.

This is the complete release workflow. Readiness evidence, preflight output, release approvals, exact-commit decisions, and expansion checklists no longer authorize or block these operating states. Existing records remain historical and may still help investigations, but they are not operating gates.

## Administrator experience

Open **Admin → AI Support → Availability**.

- In **Pilot only**, select **Make live for everyone** to enable general availability.
- In **Live for everyone**, select **Switch back to pilot only** to restore the exact-user boundary.
- Select **Stop AI now** at any time to stop automation without removing human chat.
- Select **Resume automation** to return to the selected Pilot or Everyone mode.

The old Release Readiness URL redirects to Availability. There is no approval phrase, evidence form, preflight command, or second release action in the normal workflow.

## What the Everyone switch configures

The application opens the master and user-visible controls, both supported roles, governed support/navigation capabilities, Family care-request intake/draft/recap/publication capabilities, and both the one-time and recurring publication tools. It removes Human Only last.

General availability does not grant every capability to every role:

- Family users receive the approved Family support plus one-time and recurring request workflows.
- Caregiver users receive the approved Caregiver answers and navigation workflow.
- A Caregiver cannot use Family context, intake, draft, recap, or publication capabilities.

## Safeguards retained

This simplification removes release administration, not product safety:

- runtime and provider configuration must still be present;
- unsupported users and role-inappropriate capabilities are denied;
- emergency and medical messages transfer to a person;
- 24/7 coverage requests transfer to a person;
- request publication still requires a deterministic recap and explicit confirmation;
- stale or invalid confirmation cannot publish;
- human takeover suppresses further automated replies;
- cost, turn, and runtime failures can stop automation;
- Admin activity and conversation records remain visible;
- Human Only remains the immediate rollback control.

## Deployment behavior

`general_release_enabled` defaults to Off. Deploying this change therefore preserves the current two-user pilot. No migration and no release preflight are required.

After deployment, an Administrator may leave the two-user pilot running or use the Availability page to make AI Support live for everyone. Switching back to Pilot only transfers non-pilot automated conversations to human support while preserving the two pilot users and completed receipts.

## Historical documents

Documents `27` through `36` accurately describe the earlier gated release process and its evidence. Their release-gate requirements are superseded by this decision. Safety findings and completed production evidence in those documents remain valid historical records.
