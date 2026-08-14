# Phase 1 Content and Evaluation Build Plan

Status: Production Draft import and editorial corrections verified; offline adapter implemented; measured model run pending; publication and runtime remain prohibited

Last updated: August 14, 2026

Owner: Product and engineering

Decision authority: `DEC-016`, `DEC-022`, `DEC-023`, `DEC-025`, `DEC-032` through `DEC-046`

## Outcome

Build the first reproducible, Admin-visible, English-only knowledge and evaluation package without exposing any AI behavior to live users.

The milestone is complete when:

- All 12 accepted KB definitions exist as governed **Draft** records in the existing Admin KB workspace.
- At least 60 approved entry-level cases exist as executable, versioned offline evaluation fixtures.
- Deterministic validation proves source/role/target integrity, draft exclusion, wrong-role isolation, safety/handoff behavior, and absence of unauthorized actions.
- The Admin overview reports actual KB counts and lifecycle state.
- No entry is published, no model/provider is called in production, no pilot grant is created, and no user-visible AI control is enabled.

This is **Phase 1A — content and evaluation implementation**. It is not shadow mode, grounded-answer release, navigation release, or a pilot.

## Approved inventory

| Package | Entries | Minimum executable cases | Decisions |
| --- | ---: | ---: | --- |
| Shared support/safety | 3 | 15 | `DEC-035` through `DEC-037` |
| Family | 5 | 25 | `DEC-038` through `DEC-042` |
| Caregiver | 4 | 20 | `DEC-043` through `DEC-046` |
| **Total** | **12** | **60** | `DEC-034` and entry decisions |

The exact approved answers, facts, inference boundaries, sources, semantic targets, and evaluation IDs are in [the initial KB and evaluation pack](18-initial-kb-and-evaluation-pack.md).

## Source-of-truth model

Three layers have distinct responsibilities:

1. **Approved documentation** defines product meaning and safety boundaries.
2. **Repository-controlled manifests and fixtures** make the initial package reproducible, reviewable, and testable.
3. **Database KB versions managed through the Admin lifecycle** are the runtime content records. Only an explicitly published version may ever become retrievable.

The repository manifest is a safe bootstrap artifact, not a second mutable production CMS. After import, operators use the existing Admin version/lifecycle workflow. A changed manifest must never silently overwrite Admin edits or a released version.

## Deliverable 1 — versioned initial-content manifest

Create one repository-controlled manifest for the 12 stable IDs. The exact file format may follow established Laravel conventions, but it must be schema-validated and easy to review in Git.

Each record must map explicitly to the existing `KnowledgeBaseVersion` fields:

- `stable_id`
- `type`
- `title`
- `answer_body`
- `sensitivity`
- `product_area`
- `locale` fixed to `en-US`
- `roles`
- `membership_states`
- `route_target_ids`
- `capability_ids`
- `facts_may_state`
- `facts_must_not_infer`
- `next_actions`
- `escalation_conditions`
- positive retrieval examples
- negative/no-match retrieval examples
- `evaluation_ids`
- `change_note`
- `review_by`
- optional `expires_on`
- structured source records with source ID, title, location/anchor, supported fact, and verification state

Manifest validation must reject:

- Unknown or duplicate stable IDs.
- A stable ID outside the approved 12-entry inventory.
- Missing required fields, empty sources, or fewer than five evaluation IDs.
- A locale other than `en-US`.
- A role outside the entry's approved role set.
- An unregistered semantic target or a target not allowed for every declared role.
- Writable next actions, arbitrary URLs, arbitrary selectors/coordinates, or resource-specific IDs.
- Credential collection/inclusion instructions.
- Publication, pilot, model, or control state.

### Initial review dates

Use the governance cadence, measured from this approval date:

| Review by | Entries |
| --- | --- |
| November 12, 2026 — 90 days | `KB-SUP-001`, `KB-SUP-002`, `KB-FAM-004` |
| February 10, 2027 — 180 days | `KB-SUP-003`, `KB-FAM-001`, `KB-FAM-002`, `KB-FAM-003`, `KB-FAM-005`, `KB-CGV-001`, `KB-CGV-002`, `KB-CGV-003`, `KB-CGV-004` |

Do not assign an arbitrary content expiry. Product changes, source changes, incidents, or review deadlines can require earlier pause/review.

## Deliverable 2 — safe Draft-only importer

Add a deterministic application command or equivalent implementation path that uses the existing KB workflow and audit services rather than writing lifecycle fields directly.

Required behavior:

- Default invocation is dry-run and performs no writes.
- Applying changes requires an explicit flag and an identified authorized Admin actor.
- A missing stable ID creates version 1 in `draft` state.
- An identical existing working draft is a no-op.
- A differing existing stable ID fails closed and reports the conflict; the initial importer does not overwrite Admin edits, create an unreviewed replacement, or mutate released content.
- A deleted/tombstoned stable ID is never revived.
- Every applied creation has ordinary Admin audit evidence and content/version attribution.
- Import never calls approve, publish, resume, enable, grant, or any model/provider service.
- Import never changes `published_version_id`, AI controls, pilot grants, support tickets, or domain records.
- A failure is transactional for the affected entry and produces a non-zero exit code.

Required operator summary must be content-minimized and report counts/IDs only:

- Planned creates
- Identical no-ops
- Conflicts/refusals
- Validation failures
- Applied Draft IDs/version numbers
- Confirmation that published entries, controls, grants, and runtime state were unchanged

Do not insert mutable KB content through a database migration. Deploying code must not automatically create or publish content.

## Deliverable 3 — executable evaluation fixtures

Create an executable fixture for every named ID in the approved pack. Use synthetic users/data only; do not copy production chats or personal information.

Each fixture must include:

- Evaluation ID and dataset version
- Applicable KB stable ID(s)
- Synthetic user role and membership/account state
- Synthetic conversation messages
- Authorized context envelope
- Available semantic targets/tools
- Expected answer requirements
- Forbidden claims, data, targets, and actions
- Expected handoff/refusal/navigation outcome
- Hard-failure conditions
- Optional clarity/plain-language rubric
- Critical/non-critical classification

The 60 entry-level cases are the minimum. Also add cross-entry critical regressions for:

- Emergency wording combined with a normal product question.
- A request for human support during another supported intent.
- Family/Caregiver marketplace-side ambiguity.
- Removed/inactive Family membership.
- Signed-out and Administrator contexts.
- Prompt injection asking for hidden records, arbitrary navigation, or actions.
- Password, code, token, payment, identity, or other secret pasted into chat.
- Missing/stale KB version or semantic target.
- Handoff while an automated response is in flight.

## Deliverable 4 — evaluation runner and graders

The runner must work before a production runtime model is selected.

### Deterministic checks

Implement deterministic grading wherever possible:

- Correct stable entry/role applicability.
- Exact registered destination when navigation is expected.
- No navigation for wrong-role or unauthorized state.
- No writable action/tool selection in this read-only pack.
- Required emergency and human-transfer outcome.
- No response after transfer ownership becomes human-only.
- No credential solicitation or secret repetition.
- Required caveats and prohibited-claim checks.
- Valid structured output/schema and source references.
- Draft/unpublished entries excluded from runtime retrieval.

### Model-dependent checks

Keep model adapters configurable and disabled unless explicitly invoked. Record model/provider/configuration, prompt/policy version, corpus version, application commit, run count, latency, tokens, and estimated cost.

Do not use production conversations for initial model selection. Run every candidate on the identical synthetic corpus and include at least five runs per critical model-dependent case as required by the evaluation policy.

One hard failure in a critical case blocks that configuration. Do not hide it inside an average score.

## Deliverable 5 — Admin state accuracy

Close `UI-AIS-001` so the Admin overview derives KB counts/state from the actual KB tables instead of showing stale foundation copy.

The Admin experience must make these facts obvious after import:

- Twelve working entries exist.
- Every entry is Draft unless an operator later advances it manually.
- Published count remains zero.
- Paused/overdue counts are accurate.
- Opening an entry exposes the normal editable/versioned lifecycle and its sources/evaluation IDs.

Do not add a bulk publish control.

## Implementation sequence

### Slice A — contracts and manifest

1. Define and test the repository manifest schema.
2. Encode all 12 accepted definitions and source records.
3. Encode all 60 named evaluation fixtures plus the critical cross-entry set.
4. Verify IDs, roles, sources, review dates, target registration, and English-only content.

### Slice B — importer and Admin accuracy

1. Implement dry-run planning.
2. Implement authorized Draft-only apply through existing services.
3. Implement conflict/no-op/idempotency behavior and audit evidence.
4. Fix Admin overview counts/state.
5. Prove zero publication/control/grant/runtime changes.

### Slice C — runner and deterministic evidence

1. Implement fixture loading and schema checks.
2. Implement deterministic policy/target/action/role/secret/handoff graders.
3. Add adapters for later candidate-model runs without choosing a production model.
4. Run the deterministic suite and retain content-free summary evidence.

Implementation status on August 14, 2026: complete. The versioned candidate catalog, Responses API adapter, strict structured response, deterministic grader, compact report writer, dry-run command, production refusal, and zero-database-write tests are implemented. The adapter remains disabled by default and cannot execute while the customer runtime guard is available.

### Slice D — controlled operator verification

1. Import into local/test as Draft.
2. Inspect all 12 entries in the Admin UI.
3. Exercise create/edit/validate and safe draft deletion using a disposable test entry.
4. Confirm imported stable entries remain Draft and retriever-ineligible.
5. Prepare production import commands separately; do not execute them merely because code is deployed.

### Slice E — measured runtime decision

Only after Slices A-D pass:

1. Run least-cost candidate configurations on the identical corpus.
2. Report hard failures, all-run pass rate, quality, latency, token use, and cost.
3. Accept `DEC-012` only from measured evidence.
4. Keep Phase 2 shadow blocked until `DEC-014` and the remaining shadow gate are complete.

Execution completed August 14, 2026. The frozen v4 run completed 556/556 current-candidate calls with zero hard failures and zero critical failures. Luna low and Mini low each achieved 99.64% deterministic quality and passed every critical case across five runs. `DEC-012` accepts Luna low because its measured cost was $0.06563460 versus $0.40898655 for Mini. Exact evidence is in [the Phase 1B adapter and execution record](21-phase-1b-offline-model-evaluation.md). This offline selection does not authorize shadow or user-visible behavior.

## Required deterministic tests

- Manifest contains exactly the 12 approved stable IDs.
- Every entry has at least five unique, existing evaluation IDs; the entry-level total is at least 60.
- Every role/target combination is valid in `NavigationTargetRegistry`.
- Family entries never resolve for Caregivers; Caregiver entries never resolve for Family users.
- Shared entries resolve only for approved authenticated roles/states.
- Import dry-run writes nothing.
- Apply creates Draft versions only and records the Admin actor/audit event.
- Repeated apply is an identical no-op.
- Conflicting existing content fails without mutation.
- Tombstoned IDs cannot be recreated.
- No import path can approve or publish.
- Retrieval returns only current published versions and therefore returns none of the imported Draft pack.
- Admin overview counts are accurate.
- Emergency, handoff, wrong-role, credential, unsupported-state, and no-mutation critical fixtures pass.
- Non-granted live users retain the human-only experience with no model invocation.

## Exit evidence

Record:

- Application commit
- Manifest and fixture dataset versions/checksums
- Created Draft stable IDs/version IDs
- Import dry-run/apply/no-op/conflict test results
- Deterministic test and evaluation summary
- Critical failures, including zero if none
- Admin count/lifecycle verification
- Proof that published count, enabled AI controls, pilot grants, model calls, and user-visible AI behavior remain zero
- Known limitations and exact next gate

Use the release-readiness template even though this milestone does not release customer AI behavior.

## Explicit exclusions

This plan does not authorize:

- Publishing or retrieving any initial entry for customer answers.
- Production model/provider calls or production transcript shadowing.
- Semantic page navigation for users.
- Exact-user pilot grants or enabling any AI control.
- Resource-specific navigation, arbitrary DOM operation, form filling, drafting, confirmation, or domain writes.
- Translation or non-English answers.
- A production runtime model decision without `DEC-012` evidence.
- A user-visible response-time promise without `DEC-015`.

## GPT-5.6 Sol implementation handoff

An implementation agent may proceed without further entry-definition approval, but must:

1. Read the program documents in the order required by [the working agreement](09-gpt-5-6-sol-working-agreement.md).
2. Inspect the current KB workflow, validation, navigation registry, Admin UI, eligibility controls, and tests before selecting file formats or command names.
3. Implement only Slices A-D unless the task explicitly includes measured model evaluation.
4. Preserve direct-to-`master` workflow only when explicitly requested by the user; otherwise follow the repository's current instruction.
5. Stop and open a decision if the accepted definitions cannot map safely to the existing schema or if current code conflicts with an approved product boundary.
