# Rollout and Release Gates

Status: Accepted phased plan; Phase 0-1 foundation deployed fail-closed

Last reviewed: August 13, 2026

Owner: Product and engineering

Required approvers: Product, engineering, support operations; security/privacy for Classes D and E

## Rollout principle

Phase the platform and each capability separately. A platform phase makes a class of behavior possible; an individual capability still moves through offline evaluation, shadow, internal use, limited release, and general release.

Never enable every tool because the architecture supports tools.

## Current program position

As of August 13, 2026, the Phase 0-1 model-independent foundation is deployed to production from `master` at `338a9db25d98eff6ce096a92dda05d4a1878bee2`. The deployment runtime guard is off, all missing database controls fail closed, human-only is on by default, no exact-user pilot grant is active, and no customer model-call path exists.

This production deployment does not mean Phase 1 or the intelligent agent is complete. The current position is:

- Phase 0 application/runtime retirement: deployed.
- Phase 0 primary legacy database destruction: executed with content-free evidence; derived-target and containing-backup extinction remains an operational closeout item until verified complete.
- Phase 1 control plane, KB workspace, contracts, handoff, evidence, and retention foundation: deployed.
- Phase 1 initial approved KB content, evaluation corpus/runner, runtime baseline, and first capability approvals: not complete.
- Phase 2 production-conversation shadowing and every user-visible phase: not authorized.

The live status and next work are tracked in [the production deployment and next-phase tracker](17-production-deployment-and-next-phase-tracker.md).

## Role release sequence

The shared conversation, authenticated role resolution, KB governance, safety, transfer-to-human, observability, and evaluation foundation must support both family/care-receiver and caregiver tracks from the start. User-visible capabilities release in this order:

1. Family/care-receiver approved answers, semantic navigation, and then the separately gated one-time care-request workflow.
2. Caregiver approved answers and semantic navigation using caregiver-only sources, routes, states, and evaluations.
3. Caregiver operational capabilities released individually after capability specification, authorization review, offline and end-to-end evaluation, shadow review, and limited rollout.

No release for one role exposes that role's knowledge, records, routes, or tools to the other role.

## Program phases

### Phase 0: Stabilize human support and retire legacy AI

Scope:

- Verify the support-chat deployment and operating workflow.
- Disable and remove the legacy AI request copilot from user-facing and application execution paths.
- Inventory legacy routes, UI, services, bindings, configuration, tests, analytics, and stored session/message data.
- Execute the `DEC-011` destruction plan for legacy sessions, messages, derivatives, replicas, and backup/restore handling without rewriting deployed migration history.
- Establish global and capability-level disable controls.
- Establish the production default-off invariant: non-granted live users receive the existing human-support UI and cannot reach AI endpoints.

Exit evidence:

- Human support and takeover work end to end.
- Current production feature configuration is documented.
- The legacy AI route and UI are unavailable, its runtime bindings are removed, and regression coverage proves it cannot execute.
- The content-free destruction audit verifies `DEC-011`; no legacy session/message data remains accessible in active systems or approved derivatives, and backup extinction/restore controls are recorded.
- Baseline quality, latency, and cost are recorded.
- Automated tests prove a fresh deployment or missing configuration exposes no AI behavior to live users.

### Phase 1: Documentation, KB, contracts, and evaluation foundation

Scope:

- Approve the charter and first capabilities.
- Build the initial source register and KB.
- Define semantic navigation targets.
- Define context, tool, event, and confirmation contracts.
- Create the offline evaluation corpus and admin review surface.
- Build the admin control plane for named pilot grants and the governed KB workspace.

Exit evidence:

- First capability cards are approved.
- Critical deterministic tests exist.
- Evaluation runner produces versioned reports.
- Support and security/privacy approve escalation and data handling.
- Pilot grant and KB mutation permissions, audit events, fail-closed behavior, and disable paths pass deterministic tests.

### Phase 2: Shadow agent

Scope:

- Run against eligible real support conversations without user-visible output.
- Generate intent, KB, answer, navigation/tool proposal, and escalation recommendation.
- Have support review risk-weighted samples.

Exit evidence:

- Shadow metrics meet capability gates.
- No unresolved critical safety failure.
- Reviewers agree the proposed handoff contains enough context.
- Cost and latency fit the limited-release budget.

### Phase 3: Grounded answers

Scope:

- User-visible Class A answers for a small approved topic set.
- No navigation or writes.
- Always-visible human option.
- Internal users, then a small controlled cohort.

Exit evidence:

- Grounding and applicability gates pass in production sampling.
- No material unsupported policy answer.
- User comprehension and handoff experience are acceptable.

### Phase 4: Semantic navigation

Scope:

- Class B **Take me there** and **Show me** actions.
- Approved routes and semantic highlights only.
- Start with family dashboard, care requests, care history, messages, and support.

Exit evidence:

- Registered routes and targets pass contract tests.
- No wrong-role or wrong-resource navigation.
- Older-adult mobile and accessibility studies pass.

### Phase 5: Draft assistance

Scope:

- Begin the first approved Class C/D workflow under `DEC-010` as draft-only assistance.
- Class C one-time care-request drafting.
- Collect one detail at a time.
- Reuse authorized recipient/address information only after showing it to the user.
- User reviews in the normal product surface.

Exit evidence:

- Draft field and schedule accuracy meet gates.
- Correction rate is acceptable.
- Draft persists and transfers to a human correctly.
- Existing manual form remains available.

### Phase 6: Confirmed bounded execution

Scope:

- Complete the first approved Class C/D workflow under `DEC-010` with Class D publication of the already-supported one-time care-request draft.
- Deterministic preview, explicit action button, transactional idempotent commit, and receipt.

Exit evidence:

- Zero confirmation, authorization, duplicate, or fabricated-success failures.
- Production limited cohort meets quality, usability, operational, and cost gates.
- Kill-switch rehearsal succeeds.

### Phase 7: Capability expansion

Candidate sequence:

- Explain request status and next action.
- Open a relevant message conversation.
- Prepare **Book again** with existing recipient, address, tasks, and caregiver.
- Prepare regular care without committing payment-affecting steps.
- Add caregiver approved answers and semantic navigation.
- Add caregiver operational capabilities one at a time after separate approval.

Classes D and E require new approval. Do not inherit approval from care-request publication.

## Cohort sequence

For each user-visible capability:

1. Staff test accounts
2. Named internal accounts for the declared target role
3. Individually named, opt-in pilot users enabled through admin grants
4. Additional individually named pilot users
5. Broader cohort or percentage rollout only after a separate product release decision
6. General release for the declared roles and states only after its release approval

During the pilot, an entry in a cohort list is insufficient by itself. Every live pilot user requires an active per-user grant. No account-wide, role-wide, percentage, or inherited enablement is allowed.

Use capability-specific feature flags. A single global `ai_request_copilot` switch is insufficient for the target program.

## Required feature controls

- Master agent switch
- User-visible AI reply switch
- Shadow mode switch
- Capability switch
- Tool switch
- Class D commit switch
- Role/cohort switch
- Per-user pilot grant with immediate revocation
- Model/provider switch with safe fallback
- KB entry pause state
- Navigation-target pause state
- Human-only mode

The safest rollback should not require a deployment. Disabling intelligence must leave human support usable.

Production eligibility is conjunctive: the master, user-visible, capability, role, exact-user grant, and conversation-ownership controls must all allow the request. Any missing or unreadable control denies AI. Neither the client nor a direct endpoint call can bypass this decision.

## Release gate meeting

Every limited or general release has a written checklist using the [release-readiness template](templates/release-readiness-template.md).

Reviewers inspect:

- Capability scope and exclusions
- Open decisions and risks
- Deterministic tests
- Offline and repeated-run evals
- Shadow or prior-cohort evidence
- Usability/accessibility evidence
- Privacy/context inventory
- Exact pilot-user list and grant audit
- Proof that all non-granted production users receive no AI UI, model call, navigation, draft, or action
- Support readiness and business hours
- Monitoring, alerts, cost budget, and rollback
- Incident owner

An aggregate green score cannot hide a critical failure.

## Automatic stop conditions

Pause the affected capability immediately for:

- Unauthorized data access or action
- Unconfirmed Class D action
- Duplicate material action
- Fabricated successful action
- Critical escalation failure
- Agent reply after human takeover
- Any AI visibility, model call, or action for a non-granted live user
- Systematic wrong navigation
- Material privacy leakage
- Cost or latency circuit-breaker breach that harms the user experience

Support may also force human-only mode during operational overload, provider instability, or an unresolved product incident.

## Rollback behavior

Rollback must preserve:

- Support transcript
- User messages and human access
- Valid completed actions and receipts
- Pending drafts when safe
- Audit events

Rollback must invalidate pending confirmations, stop new automated writes, and route unresolved conversations to human support. Do not delete evidence during an incident rollback.

## Post-release review

At the end of each cohort:

- Compare metrics with the prior cohort and human-only baseline.
- Review every hard failure and a risk-weighted sample.
- Identify KB, navigation, tool, or UX corrections.
- Add regression cases.
- Decide expand, hold, narrow, or pause.
- Update the capability registry and decision log.
