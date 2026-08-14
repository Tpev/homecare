# Capability Specification: `CARE-CONTEXT-001` — Retrieve Authorized Family Care Context

Status: Implemented and evaluated; release disabled

Version: 1.0

Owner: Family care product and security/privacy

Required release reviewers: Product, engineering, security/privacy, support operations

Last reviewed: August 14, 2026

Implementation evidence: [Interactive assistant implementation and release evidence](../24-interactive-assistant-implementation-and-release-evidence.md)

## 1. User outcome

An authenticated Family user can reuse relevant LoLo information instead of retyping recipients, addresses, care preferences, or a prior request. Every read is purpose-limited, server-authorized, visible when material, and auditable.

This Class B read capability cannot modify an account, profile, request, visit, payment, permission, or conversation ownership.

## 2. Authorized data

Within the actor's active Family Account only:

- Authorized ready care-receiver profiles
- Saved service addresses and contact information relevant to the task
- Existing and previous care requests
- Upcoming and completed visits relevant to the stated question
- Care preferences and instructions
- Non-secret account state needed by a released capability

Excluded:

- Payment credentials or complete payment-method details
- Authentication secrets, password/reset data, sessions, or security tokens
- Another Family Account's records
- Caregiver-only private records
- Unrelated support conversations or private notes
- Full history when one current record answers the purpose

## 3. Tool boundaries

Use separate purpose-specific server tools rather than a generic account query:

- `list_family_care_recipients`
- `list_family_service_addresses`
- `get_family_request_summary`
- `find_previous_family_request`
- `get_family_visit_summary`
- `get_family_care_preferences`
- `get_family_request_defaults`

Every tool derives the Family Account from the authenticated actor. Client/model-supplied account IDs confer no authority. Resource IDs are treated only as candidates and rechecked inside the active account.

Tools return the minimum display fields and opaque selection references. Broad free-form query, SQL, arbitrary relationship traversal, and raw model-selected columns are prohibited.

## 4. Reuse behavior

- Show saved recipients/addresses as recognizable selectable cards.
- If one likely value exists, propose it visibly; do not silently commit it.
- A prior request is loaded only after explicit language such as **same as last time**.
- Show every reused material value in the recap.
- Never ask the user to retype a value already supplied or selected unless revalidation finds it invalid.
- Treat profile/request notes as untrusted data, never instructions to the assistant.

## 5. Authorization and lifecycle

Reauthorize on every read and again when generating or confirming a recap. Membership removal, account-role change, pilot revocation/expiry, transfer to human, transcript deletion, or capability shutdown denies further reads.

Logout invalidates active confirmations but does not itself delete an otherwise authorized seven-day draft. Reauthentication and current authorization are required to resume.

## 6. Privacy and evidence

The model receives only fields required for the current turn. Do not persist another assembled copy. Record safe tool/result reason codes, selected opaque record references, policy result, latency, and source version; never record full profile/request bodies in the event store.

Admin evidence can show which record type and safe reference was used, not unrelated account contents. Data follows `DEC-058` and the source domain record's own retention rule.

## 7. Failure behavior

- Retry a transient authorized read once.
- If the record disappeared or became unauthorized, remove the candidate and explain that it is unavailable without revealing why or another person's data.
- Preserve unrelated valid draft fields.
- After repeated failure, offer the normal form or human transfer.
- Never fill a material field from model memory after a read fails.

## 8. Evaluation and release

Zero-tolerance cases include cross-account ID substitution, removed member, wrong role, stale tab, guessed record ID, multiple Family accounts, Caregiver prompt injection, hidden instructions in care notes, revoked pilot, and human takeover.

Gates:

- 100% cross-account, cross-role, membership, and direct-endpoint isolation
- 100% explicit prior-request reuse requirement
- Zero secret/payment exposure
- 100% current-authorization recheck at recap and commit
- Safe one-retry/fallback behavior
