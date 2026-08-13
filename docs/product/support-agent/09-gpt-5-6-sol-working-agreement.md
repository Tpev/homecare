# GPT-5.6 Sol Working Agreement

Status: Proposed

Last reviewed: August 14, 2026

Owner: Engineering

Purpose: Instructions for GPT-5.6 Sol or another coding agent working on the LoLo intelligent support program.

## Mission

Implement only the capability and phase explicitly authorized by the current task. Preserve human support, domain authorization, user control, accessibility, auditability, and rollback.

Do not interpret “build the support agent” as permission to implement every candidate capability.

## Required reading before planning

Read, in order:

1. [Directory index and source hierarchy](README.md)
2. [Program charter](00-program-charter.md)
3. [Current state](01-current-state-and-baseline.md)
4. [Capability framework](02-capability-and-risk-framework.md)
5. The affected capability registry entries and full capability specifications
6. [System architecture](03-system-architecture.md)
7. [Safety and escalation](05-safety-privacy-and-escalation.md)
8. [Evaluation](06-evaluation-and-testing.md)
9. [Admin control plane, pilot access, and KB workspace](11-admin-control-plane-and-pilot.md)
10. [Admin UX specification](12-admin-ux-specification.md) when the task affects admin or KB experiences
11. [Data retention, deletion, and legal holds](13-data-retention-and-deletion.md) when the task stores, copies, exports, logs, sends, backs up, or deletes customer or agent data
12. [Build readiness and remaining validation ledger](14-build-readiness-ledger.md) to confirm which phase is authorized and which later decisions must remain disabled
13. [Phase 0-1 foundation build record](16-phase-0-1-foundation-build-record.md) to avoid rebuilding completed foundations or enabling excluded behavior
14. [Production deployment, verification, and next-phase tracker](17-production-deployment-and-next-phase-tracker.md) for the live status, open defects, next gate, and work ordering
15. [Initial governed KB and evaluation pack](18-initial-kb-and-evaluation-pack.md) when authoring knowledge, evaluations, or the first role-aware answer/navigation runtime
16. [Phase 1 content and evaluation build plan](19-phase-1-content-and-evaluation-build-plan.md) when implementing the approved initial content/evaluation milestone
17. The applicable domain product specifications in the [source register](registries/source-register.md)

Then inspect current code, tests, migrations, routes, policies, and feature configuration. Documentation describes intent; repository inspection establishes the current baseline.

## Before changing code

Confirm and report:

- Capability IDs in scope
- Current lifecycle state and risk class
- Approved roles and account states
- Writable side effects
- Required confirmation
- Approved KB and navigation sources
- Tool/service and policy boundaries
- Required evaluation IDs and release gate
- Feature flag and rollback behavior
- Exact live-user eligibility and admin-control implications
- Any unresolved decision or documentation conflict
- Every affected data class, canonical record, duplicate/derivative, deletion trigger, backup/provider copy, and hold behavior

If a material decision is missing, update the draft documentation and ask for product approval rather than inventing it in code or prompts.

## Implementation rules

- Extend the existing support-ticket conversation unless an approved decision says otherwise.
- Use existing policies and `FamilyAccountContext`; reauthorize inside tools/services.
- Derive actor and family context server-side.
- Never trust model or client-supplied ownership identifiers.
- Keep the model away from direct database and arbitrary DOM access.
- Use strict, versioned structured contracts for model and tool outputs.
- Expose the minimum capability-specific tool set.
- Generate material previews deterministically from validated server data.
- Require action-specific confirmation for Class D.
- Make writes transactional and idempotent.
- Reconcile timeouts by idempotency key before retrying or reporting failure.
- After the deterministic transfer confirmation, stop all public model replies as soon as a conversation transfers to human support; do not wait for staff claim.
- Record observable events, not private chain-of-thought.
- Do not persist the complete assembled model request or a duplicate raw prompt/context payload. Reference the canonical transcript and record only approved compact version/evidence metadata under `DEC-025`.
- Preserve a safe manual flow and human escalation.
- Keep all new AI UI and execution off for non-granted live users; enforce eligibility on the server before any customer-facing model call.
- Never implement account-wide, role-wide, percentage, wildcard, or client-only pilot eligibility.
- Treat pilot revocation as immediate and suppress any undelivered in-flight automated response.
- Keep drafts and non-published KB states out of customer-facing retrieval; published KB edits create new versions rather than mutating history.

## Legacy AI copilot removal rule

The existing `AiRequestCopilot` is legacy and must be removed. Do not migrate, integrate, wrap, or reuse its AI-specific components in the new support agent.

Before removal, inventory its routes, UI, bindings, services, configuration, tests, analytics, stored session/message data, and derived copies. Execute `DEC-011`: destroy the legacy session/message data and content-bearing derivatives, preserve valid published care requests and ordinary support records, and retain only the content-free destruction audit. Preserve deployed migration history and use follow-up migrations for schema removal. Verify that the manual care-request form and human support chat continue to work after removal.

## Prompt rules

The system/developer prompt should be lean and capability-oriented. It should define:

- User outcome
- Approved context
- Allowed sources and tools
- Required evidence
- Clarification and stopping behavior
- Safety and escalation boundaries
- Output schema

Do not repeat deterministic policy in many prompt sections. Policy enforcement belongs outside the model.

Do not rely on “be accurate,” confidence scores, or model self-review as authorization or confirmation controls.

## Model-selection rule

Preserve an explicitly approved runtime model. Do not silently upgrade to GPT-5.6 Sol or another model. A model/configuration change requires the affected evaluation corpus and release evidence.

GPT-5.6 Sol can be used to implement, analyze, and review this program. Its use as a customer-facing runtime model remains an evaluation and cost decision.

Official reference checked August 13, 2026: [GPT-5.6 Sol model documentation](https://developers.openai.com/api/docs/models/gpt-5.6-sol).

## Test and verification rules

Before handoff:

- Run deterministic unit/feature/integration tests for affected boundaries.
- Run the exact affected offline evaluation slice with version metadata.
- Run critical authorization, confirmation, idempotency, escalation, and human-claim regressions.
- Prove non-granted-user isolation and exact-user pilot grant/revocation behavior.
- Prove KB admin authorization, draft exclusion, lifecycle, version, pause, and safe deletion behavior.
- Exercise the user journey on required mobile viewports and accessibility settings.
- Verify admin timeline, handoff summary, feature disable, and rollback.
- Report quality, latency, token, and cost results against the baseline.

Do not declare success because the model produced a good sample conversation.

## Documentation requirements for every implementation

Update in the same change:

- Capability registry state and implementation references
- Decision log for material choices
- Risk register controls/status
- Source or KB entries when behavior changed
- Evaluation cases
- Release-readiness evidence
- Operational runbook or alerts when applicable

## Final handoff format

Report:

1. Outcome delivered
2. Capability IDs and exact scope
3. User and admin experience
4. Authorization, confirmation, privacy, and safety controls
5. Data/model/tool/KB versions
6. Tests and evaluations run, including hard failures or exclusions
7. Latency and cost evidence
8. Known limitations and unsupported intents
9. Rollback/kill-switch instructions
10. Documentation updated

Never hide a failed gate behind an overall pass percentage.
