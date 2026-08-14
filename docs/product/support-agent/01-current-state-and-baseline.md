# Current State and Baseline

Status: Draft inventory

Observed: August 13, 2026

Owner: Engineering

Purpose: Describe current repository behavior and known gaps; this document does not approve new behavior.

## Executive summary

LoLo is not starting from zero. The repository already contains:

1. An authenticated support-chat widget backed by existing support tickets.
2. An administrator support queue, claiming, replies, unread state, and audit activity.
3. A separate legacy AI care-request copilot for family users, now designated for removal under `DEC-005`.
4. Legacy rule-based and OpenAI response paths associated with that copilot.
5. Legacy AI request sessions and messages that must be permanently deleted under `DEC-011`.

The human support chat is the foundation for the new program. The legacy AI care-request copilot is not a foundation for the new implementation and will not be migrated or integrated.

## Human support-chat baseline

The intended human experience is specified in [LoLo In-App Support Chat](../support-live-chat-spec.md). Repository evidence includes:

- `App\Livewire\Support\ChatWidget`
- `App\Services\Support\SupportChatService`
- `App\Services\Support\SupportTicketMessagingService`
- `App\Livewire\Admin\SupportTicketsQueue`
- `App\Livewire\Admin\SupportTicketShow`
- `tests/Feature/Support/SupportChatWidgetTest.php`

Observed behavior includes authenticated conversation selection, chat-sourced tickets, administrator claiming, message idempotency, unread behavior, and user/admin surfaces. Production enablement and deployment state must still be verified operationally; repository presence alone is not proof of production rollout.

The support ticket is the preferred canonical record for user-visible support and human escalation.

## Legacy AI care-request copilot — removal required

The repository currently exposes `family.requests.create_ai` at `/family/requests/create/ai`, guarded by authentication, the family role middleware, and the `ai_request_copilot` feature setting. Product has classified this feature as legacy and decided that it must be removed rather than migrated into intelligent support.

Main components:

- `App\Livewire\Family\AiRequestCopilot`
- `App\Contracts\AiCopilotResponder`
- `App\Services\AiCopilot\OpenAiCopilotResponder`
- `App\Services\AiCopilot\RuleBasedCopilotResponder`
- `App\Services\AiCopilot\CopilotTurnService`
- `App\Services\AiCopilot\DraftNormalizer`
- `App\Services\AiCopilot\MissingFieldsResolver`
- `App\Services\AiCopilot\QualityScorer`
- `App\Services\AiCopilot\SafetyGuard`
- `App\Services\AiCopilot\PublishCareRequestService`
- `App\Models\AiRequestSession`
- `App\Models\AiRequestMessage`

Observed behavior:

- Creates a new AI request session on page mount.
- Starts with a one-time request draft and basic address defaults.
- Accepts natural-language messages and asks one question at a time.
- Combines model output with a heuristic responder.
- Normalizes request types, states, tasks, schedules, and strings.
- Calculates required missing fields and a completeness-style quality score.
- Stores assistant/user messages and structured turn data.
- Stores model name, input/output token counts, and latency when provided.
- Warns that LoLo is for non-medical care when its limited medical-scope detection fires.
- Allows manual draft editing and fallback to the normal request form.
- Publishes a `CareRequest` transactionally when required fields are present.

The current default OpenAI model configuration is `gpt-4.1-mini` unless changed by environment. The code falls back to the rule-based responder when the key is missing or the OpenAI request throws. These implementation details are retirement inventory only and do not establish a target runtime design.

### Required retirement work

The removal change must, subject to repository verification:

- Disable and remove the legacy user entry point and route.
- Remove the legacy Livewire component and view.
- Remove legacy responder bindings, OpenAI/rule-based copilot services, feature configuration, and copilot-specific tests.
- Remove links, buttons, copy, analytics, and operational references that expose the legacy feature.
- Permanently delete existing `AiRequestSession` and `AiRequestMessage` records and their identifiable or content-bearing derivatives under `DEC-011`; do not migrate or export them for reuse.
- Preserve valid published `CareRequest` records and ordinary human-support data; neither is legacy copilot conversation data.
- Preserve deployed migration history; remove the legacy tables only through deliberate follow-up migrations after deletion preflight and write shutdown.
- Verify that the ordinary care-request form and human support chat remain functional.
- Add a regression proving the legacy route and UI are unavailable.

Removing the legacy feature does not authorize implementation of its replacement. New support-agent care-request capabilities follow their own specifications and release gates.

## Existing test baseline

Current tests cover useful units such as draft normalization, required-field resolution, rule-based conversation handling, the combined turn service, a family-user happy-path publication, and denial to a caregiver route.

This is not yet the evaluation system required for an intelligent support agent. Current coverage is primarily deterministic application testing, not a versioned corpus of realistic model conversations scored across safety, grounding, navigation, escalation, accessibility, and repeated-run stability.

## Existing domain authorities

The agent program must preserve these existing product boundaries:

- [Family care receiver workflow](../family-care-receiver-workflow.md)
- [Family account access specification](../family-account-access-spec.md)
- [Care recipient profile specification](../care-recipient-profile-spec.md)
- [Regular care product specification](../regular-care-product-spec.md)
- [Support live-chat specification](../support-live-chat-spec.md)

Notable inherited rules include:

- Active family members may perform day-to-day shared-care actions, but only owners manage payment methods and family invitations.
- Actual acting users must be attributed on sensitive care and payment-related actions.
- Existing policies and `FamilyAccountContext` are the authorization boundary.
- Payment actions must say exactly what will happen.
- Older users should see plain verbs and one primary action per section.
- The support widget must not collect unrelated page content or sensitive query values.

## Legacy retirement and target-system gaps

The following are audit findings and design gaps, not assertions that production is currently unsafe. Each must be resolved or explicitly accepted before the existing copilot is connected to support chat or expanded.

### Product and experience

- The legacy AI request copilot and support chat are separate conversations with separate persistence; the legacy path must be removed, not unified.
- There is no shared human-takeover state between the copilot and support inbox.
- There is no capability registry governing what the model may answer, navigate, draft, or execute.
- There is no semantic page/navigation registry for reliable in-app guidance.
- The legacy AI page and its interaction design are not reused in the compact support surface.

### Knowledge and truth

- The current request copilot does not use a governed product knowledge base.
- There is no source citation/version recorded for product answers.
- Product answers, task playbooks, navigation targets, and escalation instructions are not independently versioned.

### Authorization and action integrity

- Every AI session lookup and write must be re-audited for current-user and family-account scoping, including Livewire property tampering.
- Publication must be re-audited for repeat submission and idempotency; an AI session must never publish multiple requests.
- Domain policy checks must occur again inside each tool/service, not only at route or component entry.
- Confirmation must be represented as a server-verified, action-specific event rather than inferred from conversational language.
- Tool results need explicit receipts suitable for the transcript and administrator timeline.

### Model contract

- The current responder requests JSON-only text but does not use a strict, versioned output schema at the API boundary.
- Confidence and `needs_confirmation` fields are collected but are not yet a complete deterministic control system.
- The broad exception fallback is useful for continuity but does not provide structured failure classification or alerting.
- A runtime model change currently depends on environment configuration; a governed release needs recorded prompt/model/schema versions and full regression evidence.

### Safety and escalation

- Current safety detection is a small medical keyword list plus a model-provided flag.
- The existing response adds a non-medical notice but does not create a human escalation or cover emergency, abuse, fraud, privacy, billing, or account-access cases.
- There is no agent/human collision policy because the two systems are separate.
- There is no capability-level kill switch.

### Evaluation and operations

- No shadow-mode comparison against real support decisions is defined.
- No older-adult usability study is attached to the AI request flow.
- No production transcript review queue, safety dashboard, grounded-answer metric, or cost-per-resolution metric is defined.
- Token and latency fields exist for copilot messages, but model/tool/KB cost attribution is not unified with support conversations.

## Baseline decision status

Track the exact phase blockers in the [build-readiness ledger](14-build-readiness-ledger.md) and normative outcomes in the [decision log](registries/decision-log.md).

| Baseline question | Status on August 13, 2026 |
| --- | --- |
| Initial evaluated serving model/configuration | Accepted: `DEC-012`; `gpt-5.6-luna` at low reasoning is the offline baseline; no production runtime is authorized |
| Draft-only versus confirmed publication | Resolved: `DEC-010` and `DEC-013`; draft in Phase 5, separately gated publication in Phase 6 |
| Retention and redaction | Governing rule and principal data classes resolved in `DEC-024` through `DEC-031`; two remaining packages under `DEC-014` are required before production-data shadow |
| Operational owner, staffed hours, and handoff SLO | Open: `DEC-015`; required before user-visible rollout |
| Supported languages | Open: `DEC-016`; required before capability approval and corpus construction |

## Required stabilization outcome

Before intelligent support reaches users, engineering must produce a current-state audit that:

- Verifies production feature configuration.
- Maps every legacy copilot data dependency and derivative solely for deletion verification; none is migrated into the future conversation/event model.
- Proves session and family authorization under tampered requests.
- Proves publication idempotency and confirmation enforcement.
- Enumerates every model input field and its privacy purpose.
- Captures baseline quality, latency, and cost on the initial evaluation corpus.
- Produces and verifies the complete legacy copilot removal inventory, including route/UI removal and `DEC-011` data destruction.
