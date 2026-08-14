# Agent Capability Release Readiness: `{CAPABILITY-ID}`

Status: Draft

Release type: `{staff-account/limited/general}` (`DEC-047` prohibits production-conversation shadowing)

Capability version: `{version}`

Target date: `{date}`

Release owner: `{name}`

Incident owner: `{name}`

## Scope

- User roles and states: `{scope}`
- Cohort: `{scope}`
- Exact named pilot grants: `{user references and grant IDs, or N/A}`
- Risk class: `{class}`
- Enabled tools and KB snapshot: `{versions}`
- Explicit exclusions: `{list}`

## Approvals

- [ ] Product
- [ ] Engineering
- [ ] Support operations
- [ ] Design/accessibility
- [ ] Security/privacy when required

## Documentation

- [ ] Capability spec is Approved and linked
- [ ] Capability registry is current
- [ ] Decisions are resolved or explicitly accepted
- [ ] Risk controls and residual risks are recorded
- [ ] KB sources and review dates are current
- [ ] Tool, context, event, and navigation contracts are versioned

## Evidence

- [ ] Deterministic tests pass
- [ ] Critical authorization suite passes 100%
- [ ] Confirmation/idempotency/receipt suite passes 100% when applicable
- [ ] Offline repeated-run evaluation passes
- [ ] No unresolved hard failure
- [ ] E2E desktop/mobile journeys pass
- [ ] Accessibility and older-adult usability gates pass
- [ ] Shadow or prior-cohort evidence reviewed
- [ ] Latency and cost meet the declared budget

Attach exact report links/paths and configuration versions:

`{evidence}`

## Operations

- [ ] Human takeover tested
- [ ] Support staffing and availability copy confirmed
- [ ] Admin timeline and transcript access tested
- [ ] Alerts and dashboards active
- [ ] Master, capability, tool, and commit kill switches tested
- [ ] Production default-off and non-granted-user isolation tested
- [ ] Per-user grant, expiry, immediate revocation, and same-account non-inheritance tested
- [ ] KB admin draft/publish/pause/delete permissions and audit tested
- [ ] Rollback preserves human chat and action evidence
- [ ] On-call/incident contacts confirmed

## Known limitations

`{limitations visible to users/support}`

## Automatic stop conditions

`{capability-specific conditions plus program defaults}`

## Decision

- [ ] Release
- [ ] Hold
- [ ] Narrow scope
- [ ] Pause

Decision rationale and approvers:

`{rationale}`

## Post-release checkpoint

- Review date: `{date}`
- Metrics owner: `{name}`
- Required sample size/duration: `{value}`
- Expansion/hold decision due: `{date}`
