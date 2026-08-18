# LoLo Intelligent Support Agent Documentation

Status: Active program documentation; simple Pilot / Everyone / Emergency-stop availability model

Established: August 13, 2026

Program owner: Product

Operating model: Either full Administrator may operate AI Support alone; no second-person release approval is required

## Purpose

This directory is the control center for LoLo's intelligent in-app support agent. It defines what the agent may do, how it must behave, how LoLo proves that behavior, how capabilities reach production, and how humans retain control.

The agent is not a general-purpose website operator. It is a controlled product layer over approved knowledge, a semantic navigation registry, and existing LoLo domain services. The language model may interpret and explain; the application remains authoritative for identity, permissions, state, validation, confirmation, and side effects.

The human support-chat product remains the canonical conversation and escalation system. See [the existing live-chat specification](../support-live-chat-spec.md). The AI program may enhance that experience only through the gates defined here.

## Read this first

Anyone planning or implementing this program, including GPT-5.6 Sol, must read these documents in order:

1. [Program charter](00-program-charter.md)
2. [Current state and baseline gaps](01-current-state-and-baseline.md)
3. [Capability and risk framework](02-capability-and-risk-framework.md)
4. [System architecture](03-system-architecture.md)
5. [Knowledge-base governance](04-knowledge-base-governance.md)
6. [Safety, privacy, and human escalation](05-safety-privacy-and-escalation.md)
7. [Evaluation and testing](06-evaluation-and-testing.md)
8. [Historical rollout and release gates](07-rollout-and-release-gates.md)
9. [Observability, operations, and cost](08-observability-operations-and-cost.md)
10. [GPT-5.6 Sol working agreement](09-gpt-5-6-sol-working-agreement.md)
11. [Legacy copilot retirement and data destruction](10-legacy-copilot-retirement-and-data-destruction.md)
12. [Admin control plane, pilot access, and KB workspace](11-admin-control-plane-and-pilot.md)
13. [Admin UX: pilot access, KB management, and conversation evidence](12-admin-ux-specification.md)
14. [Data retention, deletion, and legal holds](13-data-retention-and-deletion.md)
15. [Build readiness and remaining validation ledger](14-build-readiness-ledger.md)
16. [Legacy copilot destruction execution runbook](15-legacy-copilot-execution-runbook.md)
17. [Phase 0-1 foundation build record](16-phase-0-1-foundation-build-record.md)
18. [Production deployment, verification, and next-phase tracker](17-production-deployment-and-next-phase-tracker.md)
19. [Initial governed KB and evaluation pack](18-initial-kb-and-evaluation-pack.md)
20. [Phase 1 content and evaluation build plan](19-phase-1-content-and-evaluation-build-plan.md)
21. [Phase 1A governed content build record](20-phase-1a-content-build-record.md)
22. [Phase 1B offline model evaluation adapter and execution record](21-phase-1b-offline-model-evaluation.md)
23. [Interactive support and care-request expansion](22-interactive-care-request-expansion.md)
24. [Interactive assistant approved build contract](23-interactive-assistant-approved-build-contract.md)
25. [Interactive assistant implementation and release evidence](24-interactive-assistant-implementation-and-release-evidence.md)
26. [Production interactive assistant deployment audit](25-production-interactive-deployment-audit.md)
27. [Production KB publication and Settings verification](26-production-kb-publication-and-settings-verification.md)
28. [Limited-release readiness contract](27-limited-release-readiness-contract.md)
29. [Provider privacy and operations evidence checklist](28-provider-privacy-and-operations-evidence-checklist.md)
30. [Staff rehearsal and rollback runbook](29-staff-rehearsal-and-rollback-runbook.md)
31. [Older-adult usability study kit](30-older-adult-usability-study-kit.md)
32. [Limited-release readiness implementation record](31-limited-release-readiness-implementation-record.md)
33. [Limited-release readiness execution log](32-limited-release-readiness-execution-log.md)
34. [Accelerated two-user pilot decision](33-accelerated-two-user-pilot-decision.md)
35. [Initial two-user Family pilot activation record](34-initial-two-user-family-pilot-activation-record.md)
36. [Two-user Family pilot operations runbook](35-two-user-family-pilot-operations-runbook.md)
37. [Original objective completion audit and expansion work queue](36-original-objective-completion-audit.md)
38. [Simplified AI Support availability](37-simplified-availability.md)
39. [Family intent and AI action coverage registry](38-family-intent-action-coverage-registry.md)
40. [App-aware guided assistance](39-app-aware-guided-assistance.md)
41. [Family Batch 1-2 evaluation harness](40-family-batch-1-2-evaluation-harness.md)
42. [Batch 1-2 production pilot audit and corrective release](41-batch-1-2-production-pilot-audit.md)
43. [Mobile support-chat UX polish](42-mobile-support-chat-ux-polish.md)
44. [Task-first navigation and payment guidance correction](43-task-first-navigation-and-payment-guidance-correction.md)
45. [Family chat operator master coverage and delivery plan](44-family-chat-operator-master-plan.md)
46. [Family Operations Knowledge Base Wave 1](45-family-operations-kb-wave-1.md)

For current operations, read item 38 before the historical limited-release records in items 28–37. `DEC-072` supersedes their approval, evidence, exact-commit, and expansion gates while retaining the implemented safety behavior. Use item 39 as the portfolio tracker for Family-user information and action coverage, item 40 as the implementation contract for reading live state, navigating, highlighting, prefilling, and verifying completion, item 41 to mass-test the exact 40 Batch 1/2 intent rows, item 42 for the latest production pilot findings and deploy/recheck list, item 43 for the current mobile chat interaction contract and regression coverage, item 44 for the task-first navigation correction prompted by the live “another credit card” pilot conversation, item 45 as the master plan for turning the full Family intent portfolio into an end-to-end chat operating layer, and item 46 for the first broad Family operations knowledge package and its production command.

Then consult the live registries:

- [Capability registry](registries/capabilities.md)
- [Decision log](registries/decision-log.md)
- [Source register](registries/source-register.md)
- [Risk register](registries/risk-register.md)

Capability specifications:

- [Human handoff without repetition](capabilities/SUP-HANDOFF-001.md)
- [Recommend and select a care path](capabilities/CARE-INTAKE-001.md)
- [Retrieve authorized Family care context](capabilities/CARE-CONTEXT-001.md)
- [Prepare a one-time or recurring request draft](capabilities/CARE-REQUEST-005.md)
- [Validate and present the request recap](capabilities/CARE-REQUEST-006.md)
- [Publish a confirmed one-time or recurring request](capabilities/CARE-REQUEST-007.md)
- [Transfer a 24/7 coverage need](capabilities/CARE-24H-001.md)

Historical superseded specifications retained for traceability:

- [Original one-time draft specification](capabilities/CARE-REQUEST-001.md)
- [Original one-time publication specification](capabilities/CARE-REQUEST-003.md)

Use the templates for every new unit of work:

- [Capability specification template](templates/capability-spec-template.md)
- [Knowledge entry template](templates/knowledge-entry-template.md)
- [Evaluation case template](templates/evaluation-case-template.md)
- [Release-readiness template](templates/release-readiness-template.md)
- [Agent incident review template](templates/incident-review-template.md)

## Source-of-truth hierarchy

When sources disagree, do not silently choose one. Record the conflict in the decision log and stop any affected release until an owner resolves it.

Use this order:

1. Applicable law, contractual obligations, and approved LoLo safety/privacy policy.
2. Existing authorization policies and approved domain product specifications.
3. Approved decisions in this program's decision log.
4. Approved capability specifications and tool contracts.
5. Approved knowledge-base entries.
6. Implementation code and tests as evidence of current behavior.
7. Draft product documents, historical support conversations, and analytics as discovery inputs only.

Code describes what the application currently does; it does not by itself authorize future agent behavior. A support transcript may reveal a content gap; it never becomes product truth without review.

## Document status vocabulary

Every normative document, capability, decision, KB entry, and release checklist uses one of these statuses:

| Status | Meaning |
| --- | --- |
| Draft | Being developed; not an implementation authority |
| Proposed | Ready for named reviewers; not approved |
| Approved | May guide implementation and evaluation |
| Implementing | Approved and under active delivery |
| Shadow | Running without user-visible answers or side effects |
| Limited release | Available to a controlled cohort |
| Released | Generally available within its declared scope |
| Paused | Disabled pending investigation or a new decision |
| Superseded | Retained for history; replaced by a linked artifact |

## Required traceability

No agent capability is complete unless LoLo can follow this chain in both directions:

> User need -> capability ID -> approved sources -> navigation/tools -> safety controls -> tests -> production metrics

Every material implementation change must name the affected capability and tests. Every production failure that changes expected behavior must add or update a regression case.

## Documentation change process

1. Record a short decision for a material product, safety, data, model, or autonomy change.
2. Update the affected capability, source, tool, and tests with the implementation.
3. Run the affected tests and critical safety regressions.
4. Deploy through the normal `deploy.sh` process and monitor the result.

No separate readiness approval, evidence package, exact-commit decision, or release checklist is required to change between Pilot and Everyone modes.

Material changes include model or provider changes, prompt-policy changes, new tools, new writable fields, new user roles, confirmation changes, KB-source changes, and any change to human escalation.

## Definition of ready

A capability is ready for engineering only when:

- Its capability card has no unresolved safety or authorization question.
- Required domain sources are named.
- Its confirmation and human-transfer behavior are clear.
- Tool inputs, outputs, errors, and idempotency behavior are specified.
- Evaluation cases exist before implementation begins.
- Admin visibility, human takeover, and metrics are defined.
- The capability has a feature flag and rollback/disable path.

## Definition of done

A capability is done only when:

- Affected deterministic tests and agent evaluations pass.
- Relevant mobile and accessibility behavior is verified.
- Event logging and cost attribution work end to end.
- Production monitoring and kill switches are active.
- The capability registry reflects its actual release state.

## OpenAI documentation baseline

The implementation may use OpenAI's Responses API and tool calling, but this documentation is intentionally model-agnostic at the product boundary. GPT-5.6 Sol is suitable for complex implementation and evaluation work; it is not automatically the correct production-serving model for every customer turn. Runtime model selection must be earned through the evaluation and cost process.

Current official reference, checked August 13, 2026: [GPT-5.6 Sol model documentation](https://developers.openai.com/api/docs/models/gpt-5.6-sol).
