# Evaluation Case: `{EVAL-ID}` — `{Title}`

Status: Draft

Dataset version: `{version}`

Owner: `{name/role}`

Capability: `{CAPABILITY-ID and version}`

Risk class: `{A/B/C/D/E}`

Tags: `{golden,boundary,authorization,safety,reliability,accessibility,etc.}`

## Purpose

`{Failure or behavior this case is designed to expose}`

## Initial state

- User role: `{role}`
- Family membership: `{state}`
- Locale/timezone: `{values}`
- Current screen: `{screen ID}`
- Authorized records: `{safe fixture references}`
- Capability flags: `{states}`
- Human/agent ownership: `{state}`
- KB snapshot: `{version}`
- Available tools: `{versions}`

## Conversation

1. User: `{message}`
2. Assistant/tool/user continuation as needed: `{turn}`

## Expected routing

- Capability: `{ID or safe unknown}`
- Expected KB IDs: `{IDs or none}`
- Allowed tools: `{tools or none}`
- Expected clarification: `{question or none}`
- Expected escalation: `{reason or none}`

## Hard constraints

- `{must/must not behavior}`
- `{authorization/confirmation/receipt constraint}`

## Expected structured result

```json
{
  "example": "replace with the exact schema or omit when not applicable"
}
```

## Quality rubric

| Dimension | Pass condition |
| --- | --- |
| Grounding | `{condition}` |
| Clarity | `{condition}` |
| One-step interaction | `{condition}` |
| Next action | `{condition}` |

## Run policy

- Deterministic or model-dependent: `{type}`
- Minimum repeated runs: `{number}`
- Hard fail blocks release: `{yes/no}`
- Required environments/devices: `{list}`

## Regression provenance

- Origin: `{design requirement, support gap, production incident}`
- Related risk: `{RISK-ID}`
- Defective version, if any: `{version}`

