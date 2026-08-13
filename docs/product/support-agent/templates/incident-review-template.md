# Agent Incident Review: `{INCIDENT-ID}` — `{Title}`

Status: Open

Severity: `{level}`

Started: `{timestamp}`

Owner: `{name}`

Affected capabilities/configurations: `{IDs and versions}`

## User impact

State what happened in user terms. Include affected roles, records, actions, and duration. Do not minimize uncertainty.

## Detection

- Detection source: `{alert, support, review, user}`
- First signal: `{timestamp}`
- Why existing controls did or did not detect it earlier: `{analysis}`

## Immediate containment

- Capability/tool/model flags changed: `{details}`
- Human-only mode: `{status}`
- Evidence preserved: `{details}`
- User/account remediation: `{details}`

## Timeline

| Time | Event |
| --- | --- |
| `{time}` | `{event}` |

## Configuration and evidence

- Application commit: `{commit}`
- Model/provider: `{exact ID}`
- Prompt/policy version: `{version}`
- Tool schemas: `{versions}`
- KB snapshot/entries: `{versions}`
- Conversation/event/receipt references: `{safe references}`

Do not include private chain-of-thought.

## Root causes and contributing factors

Analyze separately:

- Product specification
- Knowledge/retrieval
- Model/prompt
- Authorization/policy
- Tool/domain service
- Confirmation/idempotency
- UI/accessibility
- Observability/operations
- Evaluation gap

## Corrective actions

| Action | Owner | Due | Verification | Status |
| --- | --- | --- | --- | --- |
| `{action}` | `{owner}` | `{date}` | `{test/eval}` | Open |

## Regression evidence

- New evaluation IDs: `{IDs}`
- Defective version fails: `{yes/evidence}`
- Corrected version passes: `{yes/evidence}`
- Critical cross-capability regressions: `{result}`

## Re-release decision

Use a new [release-readiness checklist](release-readiness-template.md). Record approvers, cohort, monitoring period, and rollback owner.

