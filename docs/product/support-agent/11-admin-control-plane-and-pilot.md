# Admin Control Plane, Pilot Access, and KB Workspace

Status: Accepted product requirements; implementation specification

Accepted: August 13, 2026

Owner: Product

Required reviewers: Engineering, support operations, design/accessibility, and security/privacy

Detailed interaction design: [Admin UX: pilot access, KB management, and conversation evidence](12-admin-ux-specification.md)

## Objective

Give LoLo administrators precise control over who can access the AI assistant during the pilot and over which knowledge the assistant can use, while guaranteeing that every non-granted live user receives the existing human-support experience without visible or executable AI behavior.

## Non-negotiable invariants

- New AI support is off by default in production and on every fresh or incomplete configuration.
- Deployment alone never enables AI for a live user.
- During pilot, only an exact user with an active server-side grant can see or invoke AI.
- A grant never transfers to another family member, caregiver, account member, session, or role.
- The server rechecks eligibility before every retrieval, model call, navigation proposal, draft update, preview, and action.
- A non-granted user causes no customer-facing model cost: no model call is made.
- Disabling or revoking access never removes human support or the existing conversation.
- Draft, paused, superseded, expired, withdrawn, and deleted KB entries are never eligible for customer-facing retrieval.
- Every grant and KB mutation is authorized, attributable, timestamped, and auditable.

## 1. Live-user isolation

### Effective eligibility

AI may run for a customer-facing turn only when all of the following are true:

```text
production master on
AND user-visible AI on
AND exact authenticated user has an active grant
AND current server-derived role is allowed
AND requested capability is released for that grant
AND required tool is enabled, when applicable
AND conversation is automated, not human-only
```

Any false, missing, expired, unreadable, or contradictory value denies AI and preserves ordinary human support. The browser may use the eligibility result for rendering, but it is never the enforcement authority.

### Non-granted user experience

A non-granted live user:

- Sees the existing support-chat entry point and human-support interface.
- Sees no AI badge, greeting, toggle, suggestion, disabled menu item, waitlist, preview, or promotional copy.
- Cannot reach AI through a copied URL, modified Livewire property, stale page, direct request, or another account member's session.
- Sends no customer-facing request to the model provider.
- Retains normal access to manual care-request and other ordinary product workflows.

### Shadow processing

User-invisible shadow evaluation is not enabled merely by a pilot grant. It requires its own environment/control setting, documented privacy basis, restricted transcript access, zero public output, and zero navigation or data-changing side effect.

## 2. Per-user pilot administration

### Admin user panel

An authorized administrator can search for and open an exact user. The AI pilot card shows:

- Stable user reference and current role
- Relevant family/account reference without exposing unnecessary sensitive data
- Current effective status: Not eligible, Scheduled, Enabled, Expired, or Revoked
- Grant ID, capability scope, start time, optional expiry, and reason
- Which higher-level switch currently blocks or allows effective access
- Creator, revoker, timestamps, and complete grant history
- Last AI interaction time and a link to eligible conversation evidence

### Enable flow

**Enable AI pilot** requires:

1. Confirming the exact user and current role.
2. Selecting an approved capability bundle; arbitrary tool selection is not allowed.
3. Selecting a start time and expiry under `DEC-021`: 14 days after activation by default, another chosen expiry, or explicitly acknowledged **No expiry**.
4. Recording a reason or pilot cohort note.
5. Reviewing that access applies only to this user.
6. Explicitly confirming the grant.

The server creates an immutable grant event. A grant does not override global, role, capability, tool, safety, or conversation-ownership controls.

### Disable flow

**Disable AI pilot** requires an explicit confirmation and reason. Revocation:

- Takes effect on the server immediately.
- Suppresses any model reply that has not yet been publicly delivered.
- Prevents new retrieval, model, navigation, draft, preview, and action activity.
- Leaves the support conversation and prior audit evidence intact.
- Places an active automated conversation into human-only support when necessary so the user is not left with an unowned AI flow.

Natural grant expiration also takes effect immediately. It does not erase the conversation or audit history. Continued access requires a new grant rather than extending or rewriting the historical grant.

### Pilot restrictions

- No account-wide, family-wide, caregiver-wide, role-wide, bulk, percentage, or wildcard grant during pilot.
- No self-enrollment or user-controlled enable switch.
- No client-only, cookie-only, URL, or analytics-cohort eligibility.
- An administrator cannot grant unreleased capabilities or bypass current role authorization.
- Role or account-membership changes are effective immediately because current authorization is re-evaluated on each turn.

Broader enablement requires a later product decision, its own release evidence, and an updated control contract.

## 3. KB admin workspace

### Entry list

The KB landing page lists every entry and every version the administrator is authorized to see. It supports search, pagination, sorting, and filters for:

- Status: Draft, In review, Approved, Published, Paused, Superseded, Withdrawn, or Deleted
- Entry type, stable KB ID, version, and title
- Family/care-receiver or caregiver roles and applicable account states
- Product area, route, semantic target, capability, and jurisdiction
- Sensitivity, owner, optional reviewer/contact, source, effective date, and review-by date
- Missing metadata, failed validation, conflicts, usage, retrieval misses, and overdue review

The default view highlights drafts, paused content, overdue reviews, conflicts, and entries blocking a capability.

### Create and edit

**New KB entry** always creates a Draft. The editor exposes every required field from the KB governance contract and provides:

- Plain-language content editor and older-adult preview
- Role/applicability builder
- Source and capability selectors
- Facts-allowed, facts-prohibited, escalation, and sensitivity fields
- Effective/review dates
- Navigation/route validation
- Related evaluation selection and validation results
- Autosave or explicit save with conflict detection
- Change notes and a side-by-side version comparison

Editing published content creates a new draft version. It never mutates the released snapshot in place.

### Lifecycle actions

Authorized actions are:

- **Save draft** — not retrievable by customer-facing AI.
- **Submit for review** — locks or marks the review candidate according to the final workflow.
- **Approve** — records the authorized operator's review decision; under `DEC-022`, the operator may be the author and may publish afterward.
- **Publish** — atomically releases the approved immutable version and records its effective snapshot.
- **Pause** — immediately removes an entry from retrieval without waiting for deployment.
- **Supersede** — publishes a replacement and links both versions.
- **Delete draft** — permanently removes a never-released, dependency-free draft after confirmation.
- **Delete released entry** — immediately withdraws it from retrieval and starts the `DEC-030` historical-retention lifecycle. The full released version remains for 36 calendar months after retirement, extended only for retained dependencies, followed by a content-free tombstone for 24 additional months. Normal UI deletion cannot erase protected interaction evidence, and stable IDs are never reused.

A separate security/privacy purge procedure may be defined for content that legally must be erased. That procedure is outside ordinary KB editing and must reconcile conversation evidence without making prior answers appear to have used a different version.

### Publication gate

Publish is allowed only when:

- Required metadata and an authoritative active source are present.
- Role and state applicability are explicit.
- Required domain and sensitive-content checks are complete and recorded by an authorized KB operator; under `DEC-022`, no different actor is required.
- Related evaluation cases exist and pass.
- Route and semantic targets resolve when referenced.
- The version has no unresolved applicability conflict.
- The effective date is not in the future; `DEC-023` permits manual **Publish now** only in the first release.
- The publisher has the required permission and sees the exact release diff.

No transcript, model suggestion, import, or prior agent response can satisfy these gates automatically.

## 4. Permissions

The implementation should define discrete permissions equivalent to:

| Permission | Allows |
| --- | --- |
| `ai_pilot.view` | View pilot status and history |
| `ai_pilot.manage` | Create and revoke exact-user grants; during the initial pilot, `DEC-020` additionally restricts this to existing full administrators |
| `kb.view` | View entries and versions |
| `kb.edit` | Create and edit drafts |
| `kb.review` | Record domain review |
| `kb.publish` | Publish, pause, and supersede allowed entries |
| `kb.delete` | Delete drafts or withdraw released entries |
| `kb.sensitive_approve` | Approve declared sensitive content |

Under `DEC-022`, LoLo's two designated KB operators each receive the complete `kb.*` permission set and may author, self-review, approve, publish, pause, supersede, withdraw, or delete alone. The permissions remain distinct policy checks for auditability and future delegation; they do not impose separation of duties today.

Use existing admin authentication and authorization infrastructure. A broad support-inbox permission must not silently confer pilot-management or KB-publication authority.

## 5. Audit and observability

Record at minimum:

- Grant created, scheduled, expired, revoked, or denied
- Effective eligibility result and stable denial reason before any attempted model call
- KB draft created, edited, submitted, approved, rejected, published, paused, superseded, withdrawn, or deleted
- Actor, target, prior/new version, reason, timestamps, and success/failure
- Conversation and agent-run references to exact KB IDs and versions

Do not put full sensitive content in general analytics. Detailed content and transcript access follows the dedicated retention and privacy decision.

Under `DEC-029`, grant records remain while scheduled/active and for 24 calendar months after expiry/revocation. Effective production control versions remain while effective and for 24 months after replacement/deactivation; failed, denied, and cancelled control attempts remain for 24 months. A retained interaction, action, incident, or hold dependency can extend those periods only until the dependency expires. Reasons remain concise and never contain transcript or sensitive care content.

Admin dashboards show all active grants, scheduled expirations, recent revocations, eligibility anomalies, published KB changes, paused entries, overdue reviews, and failed mutations.

## 6. Failure behavior

| Failure | Required behavior |
| --- | --- |
| Eligibility cannot be resolved | Deny AI; preserve human support; make no model call |
| Grant is revoked during a turn | Suppress undelivered AI output; enter human-only behavior |
| Admin grant write conflicts | Show actual current state; do not overwrite silently |
| KB save conflicts | Preserve both drafts or require explicit merge; do not lose work |
| KB publish validation fails | Keep draft; show exact blocking fields; publish nothing |
| KB store or retrieval state is uncertain | Do not use the uncertain entry; pause affected capability or transfer to human |
| Published entry is paused/withdrawn mid-turn | Recheck before delivery/action; suppress unsupported material response |

## 7. Required tests and acceptance gates

Before the first named live pilot user is enabled:

- 100% of non-granted-user UI, endpoint, model-call, navigation, draft, and action isolation tests pass.
- 100% of grant create, schedule, expiry, revoke, role-change, same-account non-inheritance, tampering, and concurrency tests pass.
- Fresh deployment, missing configuration, cache failure, and control-store failure all resolve to AI off.
- Revocation during an in-flight turn suppresses public automated delivery.
- Admin permissions deny every unauthorized grant and KB mutation path.
- Draft, paused, superseded, expired, withdrawn, and deleted KB versions are never retrieved.
- Publish, pause, supersede, draft deletion, released-entry withdrawal, version conflict, and audit tests pass.
- Support chat and ordinary manual workflows pass for both granted and non-granted users.
- The admin UI is usable on its supported devices and exposes clear irreversible-action confirmations.

Any AI exposure or model invocation for a non-granted live user is an automatic pilot stop condition.

## 8. Build sequence

1. Define persistence, authorization permissions, audit events, and fail-closed eligibility service.
2. Build and test master/user-visible/capability controls plus exact-user grant APIs and admin user card.
3. Prove non-granted-user isolation before connecting any customer-facing model.
4. Build KB persistence, versioning, lifecycle state machine, validation, and retrieval filtering.
5. Build the KB list/editor/review/publish/pause/delete admin experience.
6. Connect conversation evidence to exact KB versions.
7. Run admin E2E, security, concurrency, accessibility, and failure tests.
8. Enable only staff test users; then individually grant approved pilot users after release sign-off.
