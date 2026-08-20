# GPT-5.6 Sol Implementation Prompt — Family Goal-Guided Journeys

Use the prompt below as one Codex objective. It authorizes source implementation and tests, not production deployment, KB publication, pilot mutation, or enabling **Live for everyone**.

```text
Implement the complete Family Goal-Guided Journeys Batch 10 in the current LoLo repository in one cohesive pass.

Product outcome

An authenticated Family user should be able to describe an ordinary goal without understanding the app. LoLo Support must read authorized state, explain the next step simply, recommend one-time versus regular care when relevant, navigate and highlight required UI, resume after the UI step, prepare and execute approved actions with confirmation, verify the result, and preserve context when transferring to a human.

Caregiver AI support is explicitly deferred. Do not implement a Caregiver catalog, prompt, reader, target, tool, UI, or rollout in this task.

Required reading

Read these files completely before changing code:

1. docs/product/support-agent/README.md
2. docs/product/support-agent/09-gpt-5-6-sol-working-agreement.md
3. docs/product/support-agent/22-interactive-care-request-expansion.md
4. docs/product/support-agent/23-interactive-assistant-approved-build-contract.md
5. docs/product/support-agent/38-family-intent-action-coverage-registry.md
6. docs/product/support-agent/39-app-aware-guided-assistance.md
7. docs/product/support-agent/44-family-chat-operator-master-plan.md
8. docs/product/support-agent/49-care-profiles-request-lifecycle-batch-5.md
9. docs/product/support-agent/50-applicants-messaging-hiring-batch-6.md
10. docs/product/support-agent/51-visits-regular-care-batch-7.md
11. docs/product/support-agent/52-family-administration-communication-records-batch-8.md
12. docs/product/support-agent/53-continuous-coverage-exceptional-support-batch-9.md
13. docs/product/support-agent/54-family-goal-guided-journeys.md
14. the CARE-INTAKE-001 and CARE-REQUEST-005/006/007 capability specifications

Then inspect the current implementation and tests. In particular, understand and extend rather than replace:

- AiSupportRuntimeService
- FamilyIntentResolver
- FamilyIntentCatalog
- FamilyIntentJourneyService
- FamilyGuidedAssistanceService
- AiSupportGuidedTaskService
- AiSupportPreparationService and its contract registry
- AiSupportRequestDraftService
- AiSupportCareRequestPublisher
- AiSupportRecapService
- AiSupportCompletionVerifierRegistry
- NavigationTargetRegistry
- Family readers and the Family overview
- FamilyLifecycleActionService
- FamilyCareOperationsActionService
- FamilyAdministrationActionService
- AiSupportHandoffService
- ChatWidget, resources/views/livewire/support/chat-widget.blade.php, and resources/js/support-chat.js
- existing Batches 1–9 feature tests, mass intent harness, and knowledge/evaluation catalogs

Before editing, inspect git status and preserve every unrelated user change. The workspace may contain unrelated payment, Stripe, booking-time-correction, view, route, test, and temporary-file work. Do not alter, stage, format, revert, or commit unrelated changes.

Implementation scope

1. Preserve the full Batch 6–9 source implementation and its exact-two-Family-user/default-off rollout controls.
2. Implement the minimal persistent active-journey layer required by document 54. Prefer composing existing conversation actions, preparations, request drafts, guided tasks, confirmations, and receipts. Add schema only if those records cannot safely restore the active goal and current step.
3. Support one active Family goal per automated conversation, with restore after refresh/navigation, explicit cancel, safe expiry, human transfer, and a clear choice when the user introduces a different goal. Never silently merge unrelated tasks.
4. Make care-type selection the first complete journey:
   - one specific visit -> recommend one-time care;
   - repeating weekly care -> recommend regular care;
   - irregular dates -> explain separate one-time visits and help with the first;
   - ambiguous -> ask whether it is one visit or repeats weekly;
   - 24/7 -> context-preserving human transfer;
   - emergency -> call 911 instruction before transfer;
   - medical/clinical -> non-medical boundary and transfer.
5. Explain the recommendation in one short sentence and show explicit choices. Require the user's one-time/regular selection before a request becomes publishable. Allow a natural-language correction to change type, clear incompatible fields, and retain compatible confirmed data.
6. Connect the selected path to the existing one-time/recurring request draft, recap, modify, 30-minute reconfirmation, idempotent publication, verification, and receipt flow. Do not create a second request implementation.
7. Compose the initial journey catalog from existing Batches 1–9 capabilities: care selection/request, care profile, payment method, payment failure recovery, applicant/hiring, visit/hours, regular care, history/rebooking, messages/notifications, and human help.
8. For required UI steps, render an outcome-named action, navigate only through the semantic registry, focus/highlight the exact registered target, retain the active goal, verify authoritative server state, and automatically continue. Page arrival and user text saying “done” are not completion proof.
9. Keep the interaction suitable for older adults: one material question at a time, short wording, obvious buttons, compact progress, latest-message anchoring, immediate composer clearing, Enter-to-send, Shift+Enter newline, focus preservation, and Talk to a person always available.
10. Reuse the current 324 KB mappings. Add or revise only narrowly required journey truth; do not create a redundant broad KB pack. Personalized facts must come from readers or receipts.
11. Record compact journey events and Admin-visible outcomes without copying raw prompts, tool payloads, secrets, or duplicate transcripts. Do not add a large new Admin subsystem.
12. Update the generated catalog/current stages only when the implemented terminal behavior is authorized, repeatable, and verified. Do not label an Explain, Guide, or Human row Complete to improve a metric.

Non-negotiable boundaries

- Family only, authenticated active membership, English only.
- Derive actor and Family Account server-side and reauthorize every read and write.
- No generic database, ORM, SQL, arbitrary URL, arbitrary DOM/selector, browser-control, or unrestricted write tool.
- No card numbers, CVC, passwords, verification codes, tokens, provider secrets, identity documents, or cross-account data in chat/model context.
- Secure card entry and authentication remain in the existing UI.
- Consequential writes require exact resource authorization, deterministic material recap, action-specific confirmation, 30-minute expiry, fresh-state assertion, idempotency, existing domain service, and authoritative receipt.
- Emergency/medical handling precedes ordinary routing.
- No Continuous Coverage mutation. No availability, acceptance, queue, wait-time, or outcome promise.
- No Stripe pricing, fee, authorization, capture, payout, refund, or payment-policy code changes.
- Do not change the configured runtime model.
- Do not enable, simulate, or prepare Live for everyone. All new capability controls default off and remain limited to the existing two Family pilot users when later activated.
- Do not deploy, publish production KB, activate production grants, call production providers, or create production records in this implementation task.

Required tests

Add deterministic coverage for every requirement in document 54, including:

- clear, ambiguous, imperfect, conflicting, and changing-mind care-type language;
- irregular dates, overnight, 24/7, emergency, and medical boundaries;
- explicit path choice before draft publication;
- all-details-at-once and one-answer-at-a-time request creation;
- active journey persistence after refresh and registered navigation;
- different-goal handling without state merging;
- profile and secure-payment detours returning to the request;
- exact registered navigation/highlighting and missing/disabled recovery;
- authoritative verification, stale confirmation, easy recap regeneration, idempotency, and reconciliation;
- wrong-user, wrong-account, inactive-member, revoked-pilot, deleted/stale resource, and human-owned conversation denial;
- deterministic continuation not making provider calls;
- 24/7 and exceptional paths changing no domain records;
- desktop and mobile chat behavior, keyboard/focus, reflow, and accessible names; and
- guaranteed cleanup of every synthetic request or other domain record created by browser/evaluation tests.

Run at minimum:

- the new focused journey tests;
- InteractiveSupportRuntimeTest;
- FamilyGuidedAssistanceTest and FamilyGuidedAssistanceStateMatrixTest;
- Batch5FamilyLifecycleTest;
- Batch67FamilyCareOperationsTest;
- Batch89FamilyAdministrationTest;
- FamilyIntentCoverageTest;
- php artisan ai-support:test-family-intents --plan;
- php artisan ai-support:test-family-intents for the isolated full corpus;
- the complete AI Support feature suite;
- npm build and the applicable browser tests.

Use no production database or production provider in tests. If an existing unrelated failing test blocks verification, prove whether it predates and is unrelated; do not weaken or delete it.

Documentation and handoff

Update document 54 with the exact implementation result, the master plan, the 324-intent coverage registry, affected capability records, and the decision/coverage summaries. Clearly separate source-complete, deployed, KB-published, pilot-activated, and production-browser-audited states.

At the end, report:

1. the exact user journeys delivered;
2. files and architecture changed;
3. authorization, confirmation, privacy, safety, and rollout behavior;
4. before/after Complete, Assisted, Human, and No operational path counts, without metric inflation;
5. focused, mass, full-suite, frontend, and browser test results;
6. any remaining truthful product gaps;
7. exact deployment/publication/activation/browser-audit commands, but do not run them; and
8. confirmation that Live for everyone remains off and Caregiver AI was not implemented.

Once all affected tests pass, commit only the files belonging to this task and push that commit directly to master. Do not create a branch or PR. Never include unrelated dirty-worktree files in the commit.
```
