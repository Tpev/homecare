# Capability Specification: `CARE-INTAKE-001` — Recommend and Select a Care Path

Status: Implemented and evaluated; release disabled

Version: 1.1

Owner: Family care product

Required release reviewers: Product, engineering, support operations, design/accessibility, security/privacy

Last reviewed: August 20, 2026

Implementation evidence: [Interactive assistant implementation and release evidence](../24-interactive-assistant-implementation-and-release-evidence.md) and [Batch 10 source record](../56-family-goal-guided-journeys-batch-10-implementation-record.md)

## 1. User outcome

An authenticated Family user can describe a non-medical care need in ordinary English, understand which LoLo path fits, and explicitly choose one-time care, regular/recurring care, or human-assisted 24/7 coverage.

The assistant recommends and explains. The user chooses. This capability does not create a draft, publish a request, hire a Caregiver, or authorize payment.

## 2. Supported actor and state

- Active Family Account owner or member authorized for operational care actions
- Exact active pilot grant containing this capability
- Automated conversation ownership
- English input under `DEC-016`

Wrong-role, inactive-member, missing-grant, closed, deleted, or human-owned conversations receive no capability execution.

## 3. Classification contract

| User need | Recommendation |
| --- | --- |
| One specific visit | One-time care |
| Repeated weekly visits | Regular/recurring care |
| Several irregular dates without a weekly pattern | Separate one-time requests, beginning with the first date while retaining the remaining dates |
| Continuous day-and-night or 24/7 coverage | Talk to LoLo Support |
| Immediate danger | Approved emergency instruction, then human transfer |
| Medical/clinical procedure or advice | Explain non-medical boundary, then human transfer |

If the type is ambiguous, ask one short question such as whether the need is a single visit or repeats each week. Do not create a hidden default or infer that **often**, **sometimes**, **for a while**, or **morning help** is a complete schedule.

## 4. User choice

Present plain choices:

- **One-time care**
- **Regular care**
- **Talk to LoLo Support about 24/7 care**, when applicable
- **Talk to a person** always

Explain the recommendation in one short sentence. Do not start `CARE-REQUEST-005` until the server records an explicit user selection. A natural-language correction may change the selection; incompatible draft fields are handled under `DEC-054`.

## 5. Inputs and outputs

Inputs:

- Current user message
- Minimal recent conversation context
- Authenticated role and Family membership state
- Previously selected path, if any
- Approved emergency/non-medical KB versions

Output schema:

- `recommended_path`: `one_time`, `recurring`, `human_24_7`, `emergency_handoff`, `medical_handoff`, or `clarify`
- `reason_code`
- `plain_explanation`
- `clarifying_question`, only for `clarify`
- `allowed_choices`
- `confidence_band`: `clear` or `ambiguous`; never a user-facing percentage

The server validates the enum and allowed choices. The model cannot activate another capability directly.

## 6. Safety and escalation

- Immediate danger overrides ordinary care intake.
- 24/7 language alone is not an emergency.
- Never give medical advice or classify a medical procedure as an ordinary task.
- Never promise Caregiver availability.
- State $30/hour only from the approved pricing source and only after the `DEC-049` pricing activation hold is released, including on the human-assisted 24/7 path.
- Offer transfer after two misunderstandings of the same type question or whenever requested.

## 7. Events and metrics

Record minimized events for route proposed, clarification asked, path selected, selection changed, emergency/medical override, and human transfer. Metrics include correct first-pass routing, clarification rate, correction rate, turns to selection, handoff rate, latency, and cost.

## 8. Evaluation and release

Required cases include single visit, weekly visits, different schedules by day, several irregular dates, temporary repeated care, overnight but not continuous care, explicit 24/7, emergency language, clinical tasks, ambiguous wording, unrelated operational questions, user correction, wrong role, revoked access, and prompt injection. Batch 10 freezes 48 deterministic care-choice cases and proves that established visit/status questions remain routed to their existing readers rather than new-care intake.

Gates:

- 100% emergency, medical, 24/7, role, grant, and human-ownership cases
- At least 98% first-pass supported type routing
- 100% of unresolved ambiguity produces clarification rather than a publishable type
- Zero draft creation before explicit selection

Release through staff accounts, then the exact Family cohort under `DEC-063`.
