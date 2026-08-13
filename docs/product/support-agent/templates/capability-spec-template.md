# Capability Specification: `{CAPABILITY-ID}` — `{Outcome}`

Status: Draft

Version: 0.1

Owner: `{name/role}`

Required approvers: `{roles}`

Last reviewed: `{date}`

Review by: `{date}`

## 1. User outcome

Describe the single outcome in one sentence.

User-facing promise:

> `{What the user may reasonably expect}`

## 2. Scope

### Supported users

- `{role and membership state}`

### Supported resource states

- `{state}`

### Explicitly unsupported neighboring intents

- `{intent and safe response}`

## 3. Risk and autonomy

- Risk class: `{A/B/C/D/E}`
- Rationale: `{impact and reversibility}`
- Confirmation behavior: `{none/navigation/draft review/bound action confirmation/human}`
- Financial, care, access, privacy, or notification consequences: `{list}`

## 4. Trigger language

Representative supported examples:

- `{example}`

Confusing near-neighbors:

- `{example -> expected route/escalation}`

## 5. Required authorized context

| Field | Purpose | Server source | May enter model context? | Retention |
| --- | --- | --- | --- | --- |
| `{field}` | `{purpose}` | `{source}` | `{yes/no/minimized}` | `{rule}` |

Client or model-supplied ownership identifiers are prohibited.

## 6. Approved knowledge

| KB/source ID | Purpose | Required conditions |
| --- | --- | --- |
| `{ID}` | `{fact/playbook}` | `{role/state}` |

If no applicable source is found: `{exact safe behavior}`.

## 7. Conversation behavior

- First response: `{behavior}`
- Questions, in required order: `{one at a time}`
- Facts that may be inferred: `{list}`
- Facts that must be confirmed: `{list}`
- Maximum clarification/retry behavior: `{limit}`
- Plain-language and accessibility requirements: `{requirements}`

## 8. Navigation and tools

### Navigation

| Target ID | When allowed | Arrival signal | Fallback |
| --- | --- | --- | --- |
| `{ID}` | `{condition}` | `{client event}` | `{safe parent/handoff}` |

### Tools

| Tool/version | Read/write | Policy | Input schema | Output/receipt | Retry/idempotency |
| --- | --- | --- | --- | --- | --- |
| `{tool}` | `{read/write}` | `{policy}` | `{reference}` | `{reference}` | `{rule}` |

## 9. Preview and confirmation

Material preview fields:

- `{field}`

Exact primary action label:

> `{Create this care request}`

Confirmation binding, expiry, invalidation, and duplicate behavior:

`{details}`

## 10. Results and receipts

### Success

- Authoritative result: `{record/state}`
- Receipt fields: `{fields}`
- User message: `{deterministic structure}`
- Next action: `{link/navigation}`

### Correctable failure

`{behavior}`

### Unknown or timeout

`{reconciliation and safe wording}`

## 11. Safety and escalation

| Trigger | Immediate user response | Priority | Human context |
| --- | --- | --- | --- |
| `{trigger}` | `{response}` | `{priority}` | `{context}` |

## 12. Events and metrics

- Required event types: `{list}`
- Success metric: `{metric}`
- Correction metric: `{metric}`
- Safety metric: `{metric}`
- Latency/cost budget: `{budget or decision ID}`

## 13. Evaluation requirements

| Evaluation ID | Layer | Expected result | Gate |
| --- | --- | --- | --- |
| `{ID}` | `{deterministic/offline/E2E/usability/shadow}` | `{result}` | `{threshold}` |

Include role permutations, ambiguity, changes of mind, retries, timeouts, human claim, mobile, and accessibility.

## 14. Rollout and rollback

- Dependencies: `{capability IDs/platform components}`
- Feature flags: `{flags}`
- Shadow cohort: `{scope}`
- Limited cohort: `{scope}`
- Automatic pause conditions: `{conditions}`
- Rollback behavior: `{behavior}`
- Operational owner: `{owner}`

## 15. Open decisions

- `{decision ID and question}`

## 16. Change history

| Version | Date | Author | Change | Evaluation impact |
| --- | --- | --- | --- | --- |
| 0.1 | `{date}` | `{author}` | Initial draft | New corpus required |

