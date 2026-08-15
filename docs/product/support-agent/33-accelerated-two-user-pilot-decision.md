# Accelerated Two-User Pilot Decision

Status: Proposed; not accepted; no production authority

Prepared: August 15, 2026

Decision owner: Product

## Why this decision exists

Product wants the first Family pilot to start August 15. Production readiness is currently 15 of 21 checks passing with zero incidents, zero warnings, both deployment guards off, only human-only enabled, and zero grants. Six checks remain open:

1. provider no-training and retention controls;
2. provider destination and contract;
3. downstream extinction and restore rehearsal;
4. staffed human-takeover and rollback rehearsal;
5. five-person older-adult usability study;
6. real screen-reader completion within accessibility verification.

Those checks cannot truthfully be marked Passed from code, automated tests, a standard API-key authentication call, or Product urgency. The release therefore needs one explicit choice rather than six implicit exceptions.

## Option A - Keep the accepted release gates

The two-user pilot remains scheduled for August 15-29 but does not activate until all six evidence items Pass and the final read-only preflight is green. No policy or readiness implementation changes.

This is the current authorized state unless Product explicitly accepts Option B.

## Option B - Permit the bounded pilot and move unresolved evidence before expansion

Accept the following residual-risk boundary for exactly the initial two-user Family pilot. None of these items is called Passed; each remains visible as `Deferred before expansion` under a new readiness policy state.

| Current open item | Evidence already established | Deferred obligation |
| --- | --- | --- |
| Provider no-training and retention controls | Application requests use `store:false`; no provider conversations, files, vector stores, hosted tools, or background mode; the documented API baseline is no training unless opted in and up to 30-day abuse-monitoring retention | Obtain account-owned proof of model-improvement sharing and effective retention before any expansion |
| Provider destination and contract | The configured credential authenticated through `https://api.openai.com/v1`; standard processing was selected with no residency claim | Product explicitly acknowledges the applicable account terms/privacy/subprocessor position before activation; retain the contractual reference before expansion |
| Downstream extinction and restore | Primary legacy copilot data was destroyed and current AI Support has bounded retention/deletion behavior | Complete both 9-destination inventories and isolated restore/re-deletion before expansion; keep this as an open Phase 0 operational obligation |
| Staff takeover and rollback | Exact live-model rehearsal Passed; operations alerts reached both Administrators; 47 tests/301 assertions cover the safety matrix; 22 tests/123 assertions cover the final release path | Both Administrators are online for launch; perform and record the witnessed drill during the controlled pilot before a third user or capability expansion |
| Five-person older-adult study | Approved protocol, strict validator, automated interaction/accessibility coverage, and two exact Family candidates | Complete five qualifying non-team sessions, address comprehension failures, and revalidate before adding a third user |
| Real screen reader | Automated accessible-name/state, keyboard/focus, zoom/reflow, contrast, touch-target, draft-preservation, and cross-browser checks Passed | Complete and record NVDA, VoiceOver, or equivalent human screen-reader verification before adding a third user |

## Non-negotiable Option B release boundary

If accepted, the accelerated pilot is still limited to all of the following:

- production Family IDs `282` and `19` only;
- August 15, 2026 activation and automatic expiry no later than August 29, 2026;
- `family_support_v1` bundle only;
- support answers, registered semantic navigation, authorized Family context, care-path intake, safe draft, recap, one-time confirmed publication, and 24/7 human transfer;
- `commit.one_time` and `tool.care-request.publish.one-time` only;
- recurring publication, Caregiver AI, pricing, payment behavior, and every other user remain off;
- human support stays available and either Administrator may take over immediately;
- both Administrators monitor email and in-app alerts during launch;
- every pilot interaction and any resulting request is reviewed;
- the `$0.05` conversation, `$5` daily, 50-turn daily, latency, incident, and automatic-stop controls remain active;
- any isolation, authorization, confirmation, duplicate, fabricated-success, emergency, handoff, privacy, or repeated-provider failure triggers immediate human-only rollback and grant revocation.

## Required implementation if Option B is accepted

Do not overwrite Pending evidence with a false Passed result. Implement a distinct, visible `Deferred before expansion` state and an initial-pilot readiness policy that:

1. names the accepted Product decision and all six deferred items;
2. keeps the items blocking every expansion beyond the exact two-user boundary;
3. allows the initial-pilot preflight to become green only when every non-deferred gate passes, both guards are still off, only human-only is on, and zero grants exist;
4. requires a separate explicit release record before any grant or control mutation;
5. preserves the existing full-gate preflight for expansion;
6. records the exact activation order and rollback owner without storing customer content.

After implementation, deploy through `deploy.sh`, run the read-only initial-pilot preflight, inspect both exact users again, record the explicit release decision, then create only the two expiring grants and enable only the minimum approved deployment/stored controls.

## Decision response

Product must choose exactly one:

- `KEEP OPTION A` - retain all six pre-pilot gates; or
- `APPROVE OPTION B` - accept the bounded residual risk and authorize implementation of the visible deferred-before-expansion policy.

Silence, the planned start date, documentation, tests, or a successful provider request is not Option B approval.
