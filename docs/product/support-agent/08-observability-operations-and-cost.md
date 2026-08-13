# Observability, Operations, and Cost

Status: Proposed

Last reviewed: August 13, 2026

Owner: Engineering and support operations

Required approvers: Engineering, support operations, product, security/privacy for detailed transcript access

## Operational objective

LoLo must be able to reconstruct what the user asked, what the system knew, what the agent proposed, what the application allowed, what actually happened, why a human took over, and what the interaction cost—without storing hidden model reasoning.

## Event taxonomy

Record structured events with stable reason codes.

| Event | Minimum contents |
| --- | --- |
| `conversation_started` | Conversation, actor, role, origin screen |
| `user_message_received` | Message reference and safe metadata |
| `capability_routed` | Capability ID/version, result, reason code |
| `knowledge_retrieved` | KB IDs/versions, applicability result |
| `assistant_message_sent` | Message reference, responder type |
| `navigation_proposed` | Target ID, capability ID |
| `navigation_completed` | Target ID, route result, target-found result |
| `tool_proposed` | Tool/schema version, risk class |
| `tool_denied` | Stable policy/authorization reason code |
| `draft_updated` | Draft version and changed field names, not broad sensitive values |
| `action_previewed` | Preview ID/hash, capability, expiry |
| `action_confirmed` | Preview ID, actor, confirmation action |
| `tool_completed` | Receipt ID, status, duration, idempotency reference |
| `tool_failed` | Error class, retryability, reconciled state |
| `escalation_requested` | Reason, priority, user-requested flag |
| `human_claimed` | Administrator, timestamp, prior owner state |
| `conversation_resolved` | Resolver type and outcome category |
| `pilot_grant_created` | Exact user, capability scope, start/expiry, administrator, reason |
| `pilot_grant_revoked` | Exact user, administrator, reason, in-flight suppression result |
| `pilot_eligibility_denied` | Safe user reference, failed gate, endpoint/capability; no model call |
| `kb_entry_changed` | KB ID/version, lifecycle action, administrator, reason, prior version |

Every model-dependent event also references the agent run containing model/configuration version, prompt version, context-schema version, tool-schema version, KB IDs/versions, token, latency, and cost metadata. It does not contain or point to a separately persisted complete assembled model request. Under `DEC-027`, minimized interaction events expire 24 calendar months after the conversation's most recent final resolution; reopening resets the clock.

Under `DEC-028`, an unconfirmed preview event references short-lived preview state but does not copy its rendered content. Cancelled, replaced, invalidated, or expired preview content is deleted immediately, with a hard 24-hour storage ceiling. Confirmed-action evidence is compact and expires 24 calendar months after the authoritative commit; the linked domain receipt keeps its separate approved schedule.

Under `DEC-029`, pilot grants and effective AI control versions remain for their active/effective lifetime plus 24 calendar months; failed, denied, or cancelled control attempts remain for 24 months. Retained interaction/action/incident evidence or a hold may extend them only until that dependency expires.

## Admin experience

The support inbox remains canonical. Add filters and timeline affordances for:

- Human requested
- Safety escalation
- Automation failed
- Transferred to human; awaiting internal assignment
- AI answered
- AI navigated
- Draft prepared
- Action confirmed/completed
- Agent disabled or downgraded

The administrator can see:

- Current user, family context, and origin
- Agent/human ownership state
- Capability and risk class
- KB evidence
- Tool previews, confirmations, receipts, and failures
- A generated handoff summary
- Exact underlying messages and events

The admin can claim, reply, add a private note, pause the capability for the conversation when authorized, and report a KB or product defect.

The admin application also provides:

- An AI pilot control on the exact user record showing current eligibility, role, grant scope, start/expiry, reason, and audit history.
- Explicit **Enable AI pilot** and **Disable AI pilot** actions for authorized administrators; revocation takes effect immediately.
- A KB workspace listing every entry and version with create-draft, edit, validate, review, publish, pause, supersede, and safe delete/withdraw controls.
- Links between each conversation turn and the exact KB versions, model run, capabilities, and tool receipts it used.
- A global view showing every currently granted pilot user and every expired or revoked grant.
- The applicable retention class, expected deletion date, deletion state, and any authorized legal/security hold for records the administrator may inspect.

## Metrics

### Safety and correctness

- Unauthorized attempts blocked
- Cross-account test and production incidents
- Confirmation invalidations and bypass attempts
- Duplicate-action prevention/reconciliation
- Unsupported-claim review findings
- Critical escalation misses
- Agent/human collision events

### User outcome

- Completion rate by capability
- Turns and time to completion
- Draft correction rate
- Wrong-navigation rate
- Repeated-question rate
- Human handoff and reopen rate
- User satisfaction and “was this completed?” comprehension

### Operations

- Time from transfer to internal assignment and first human reply
- Internal unassigned backlog size and age by transfer reason
- Handoff-summary usefulness
- Support minutes per resolved conversation
- KB gaps and correction backlog

### Reliability

- Model and retrieval latency percentiles
- Tool success, validation failure, and timeout rates
- Invalid structured-output rate
- Provider fallback rate
- Event ingestion failures
- Client navigation acknowledgment failures

### Cost

- Input, cached input, and output tokens
- Model and retrieval calls
- Tool-specific provider charges when applicable
- Cost per turn
- Cost per conversation
- Cost per safely completed capability
- Cost per human-escalated conversation
- Cost by model, prompt, capability, and cohort

## Cost strategy

Use the least costly runtime configuration that passes every applicable safety, accuracy, capability, latency, and elder-usability gate. Cost is the optimization priority among passing configurations, never a reason to lower a required gate. Do not use GPT-5.6 Sol as the production default merely because it is the strongest implementation agent.

Suggested routing strategy:

1. Deterministic application logic for authorization, validation, previews, receipts, and common state messages.
2. Governed retrieval plus concise model generation for approved product answers.
3. An efficient evaluated model for routine intent extraction and bounded tool selection.
4. A more capable model only for an approved ambiguity class when evaluations show a material gain.
5. GPT-5.6 Sol as the implementation/review agent, evaluation challenger, and possible runtime candidate for difficult turns—not an assumed serving choice.

Any more expensive model, reasoning setting, or routing rule must identify the capability-specific evaluation gain that justifies it. Re-test cheaper configurations periodically as models, prompts, retrieval, and workflows improve.

Cost controls:

- Expose only relevant tools and KB entries.
- Send structured semantic context, not raw pages or full histories.
- Keep static instructions stable and cacheable where supported.
- Summarize older context without discarding confirmations or receipts.
- Use deterministic templates for confirmations and action results.
- Bound model turns, output length, retries, and tool calls.
- Back off or hand off rather than loop.
- Measure savings against support time and downstream corrections.

Current GPT-5.6 Sol pricing and supported features are documented by OpenAI and may change; implementation must use current official pricing at decision time rather than copying a permanent number into product assumptions: [GPT-5.6 Sol model documentation](https://developers.openai.com/api/docs/models/gpt-5.6-sol).

## Service objectives

Set SLOs after baseline measurement. Every capability must define at least:

- Maximum acceptable user-visible response latency
- Tool timeout and retry policy
- Handoff queue target during stated staffed hours
- Error-budget threshold that pauses rollout
- Per-conversation cost budget and alert level

Do not claim human response times the team cannot staff.

## Alerts

Page or urgently alert on:

- Authorization or cross-account anomaly
- Class D action without valid confirmation
- Duplicate material action
- Fabricated or mismatched success receipt detection
- Critical escalation miss
- Agent replies after transfer to human support
- Sensitive-data redaction failure
- Event recorder failure during a write

Operational alerts include:

- Provider failure or invalid-output spike
- Tool timeout spike
- Escalation queue age
- KB miss or paused-entry spike
- Navigation-target failure after deployment
- Cost or token anomaly

## Review cadence

- Daily during limited release: failures, escalations, latency, and cost.
- Weekly: risk-weighted transcript review, KB gaps, capability scorecard.
- Monthly: model/prompt/tool/KB comparison, source freshness, support workload.
- Quarterly or after material incident: safety/privacy and program review.

## Incident response

1. Disable the affected capability or enter human-only mode.
2. Preserve transcripts, confirmations, receipts, and event evidence.
3. Determine whether any user, family, caregiver, payment, or care record was affected.
4. Notify the incident owner and required internal stakeholders.
5. Correct user-visible state through the authoritative domain process.
6. Add a regression case and complete the [incident template](templates/incident-review-template.md).
7. Re-release through the appropriate gates; do not simply re-enable after a code change.

## Privacy and access

Detailed transcript and tool-event access is limited to roles with a support, safety, engineering incident, or compliance need. Broad analytics should use minimized metadata. Administrative viewing of sensitive details should itself be auditable.

Observability does not justify retaining every payload. Follow `DEC-024` and [the retention specification](13-data-retention-and-deletion.md): reference canonical support messages instead of copying their text, minimize structured events, automatically delete expired records and derivatives, and retain only content-free proof of deletion. Metrics may remain longer only when they are aggregated or de-identified so they are not reasonably linkable to a person or conversation.
