# Provider Privacy and Operations Evidence Checklist

Status: Approved checklist; execution in progress

Last updated: August 15, 2026

Owner: Either full administrator

## Purpose

Close the provider, destination, monitoring, and alert portions of `DEC-067` without copying credentials, customer content, complete prompts, or unnecessary personal information into evidence.

The Admin [Release readiness](/admin/ai-support/readiness) workspace is the canonical evidence register. Either full administrator may complete every item. Recording evidence never changes a deployment guard, stored control, or pilot grant.

## Provider project checklist

Record `provider_project_configuration` for the initial pilot only after verifying:

- The currently configured OpenAI API credential authenticates to the standard provider destination without being displayed.
- The credential is stored only as a server secret and never enters browser state, database content, evidence, logs, source control, or build artifacts.
- Exact dedicated-project identity and restriction evidence are recommended before expansion but deferred for the initial pilot under `DEC-068`.
- `AI_SUPPORT_SAFETY_IDENTIFIER_SECRET` is a separate random secret of at least 32 characters and is not the API key or application key.
- The release preflight reports the credential and safety-secret prerequisites as present without displaying either value.

Safe evidence: project name or non-secret reference, checked date, operator, restriction summary, and source system/reference. Never paste the API key or safety secret.

### Approved current-key route

The initial pilot may use the currently configured `OPENAI_API_KEY`. Run the content-free standard-key check after deployment:

```bash
php artisan ai-support:verify-provider-project --current-key-only
```

This sends only `GET /v1/models` to the exact configured standard destination, prints no credential, changes no provider state, and does not require an Admin API key. A passing result proves that the configured credential authenticates. It does not prove exact project identity, current data-sharing settings, or provider retention settings; do not claim those facts from this mode. The `$25` provider alert is optional and is not checked or required.

The documented Admin Audit Logs schema was also checked on August 15, 2026. Its organization and project update events do not expose the model-improvement sharing setting, so audit logs are not a substitute for account-owned evidence: <https://developers.openai.com/api/reference/resources/admin/subresources/organization/subresources/audit_logs/methods/list>.

### Server-only verification route

The operator has directed that this release exercise must not connect to the OpenAI website. Use content-free production configuration checks and, when available, the provider Admin API or an account-owned export/receipt. Do not weaken the evidence claim to accommodate that constraint.

- A successful standard API request proves that a credential authenticates to the configured destination. A project-scoped `sk-proj-` key shape is supporting evidence, not proof of the intended project identity.
- Exact project identity requires a non-secret intended-project ID/name matched to provider-account evidence. Under `DEC-068`, that stronger identity evidence is deferred before expansion and does not prevent the current-key route from satisfying the initial-pilot credential item.
- Confirming model-improvement sharing and project retention settings requires project-level account evidence. The documented default policy does not prove the project's current opt-in state.
- Creating or verifying the `$25` monthly spend alert requires project-level account evidence. The application's `$25` configuration value alone does not prove that the provider alert exists.
- Never paste an Admin API key, project API key, safety secret, full provider response, customer content, or prompt into the terminal transcript or readiness evidence.

The official Admin API reference checked on August 15, 2026 now documents server-side endpoints that can close part of this evidence gap when an organization-owned Admin API credential is available:

- `GET /v1/organization/projects/{project_id}` retrieves the intended non-secret project record;
- `GET /v1/organization/projects/{project_id}/api_keys` lists that project's API-key records with redacted values;
- `GET /v1/organization/projects/{project_id}/data_retention` retrieves the configured project retention type;
- `GET /v1/organization/data_retention` resolves the effective retention type when the project reports `organization_default`;
- `GET /v1/organization/projects/{project_id}/spend_alerts` lists project spend alerts;
- `POST /v1/organization/projects/{project_id}/spend_alerts` creates an alert. The approved `$25` monthly alert uses `threshold_amount: 2500`, `currency: USD`, `interval: month`, and the approved email recipients.

Use a separate, ephemeral `OPENAI_ADMIN_KEY` for these Administration endpoints. Do not store it in application configuration, pass it as a visible command argument, or substitute the production project key. Verify an existing alert before creating one to prevent duplicates, and require an explicit confirmation before the provider write. The documented Admin API surface does not expose the model-improvement data-sharing opt-in state; that setting still requires account-owned evidence and must remain Pending if none is supplied.

### Optional stronger Admin API runbook

After deploying the verifier, discover the active non-secret project IDs and names, select the intended dedicated AI Support project, and then run verification in read-only mode. Enter the organization Admin API key only at the hidden prompt; do not add it to `.env`, command history, Admin evidence, or chat. Project discovery never needs or prints the configured production project key.

```bash
read -s -p "OpenAI Admin API key: " OPENAI_ADMIN_KEY
printf '\n'
export OPENAI_ADMIN_KEY
php artisan ai-support:verify-provider-project --list-projects
read -r -p "Intended OpenAI project ID (proj_...): " AI_SUPPORT_PROVIDER_PROJECT_ID
php artisan ai-support:verify-provider-project \
  --project-id="$AI_SUPPORT_PROVIDER_PROJECT_ID"
unset OPENAI_ADMIN_KEY AI_SUPPORT_PROVIDER_PROJECT_ID
```

Read-only mode retrieves the exact active project, matches the configured production key to exactly one redacted project-key record, makes a content-free `GET /models` request with the intended project header, observes the project retention type and its effective organization type when inherited, and checks for an existing `$25` monthly email alert. It refuses any configured destination other than exactly `https://api.openai.com/v1`, never prints either credential or recipient addresses, and never changes provider state.

If the optional Admin mode reports the alert as `OPTIONAL - missing` and the operator later chooses to add the defense, run the confirmed creation mode with the approved recipients. Recipient input is hidden and ephemeral, and every supplied address must be valid and unique or the command fails before provider access. The command creates only a `2500`-cent USD monthly email alert, supplies an idempotency key, and re-lists alerts before claiming success.

```bash
read -s -p "OpenAI Admin API key: " OPENAI_ADMIN_KEY
printf '\n'
export OPENAI_ADMIN_KEY
read -s -p "Comma-separated alert recipient emails: " AI_SUPPORT_SPEND_ALERT_RECIPIENTS
printf '\n'
export AI_SUPPORT_SPEND_ALERT_RECIPIENTS
read -r -p "Intended OpenAI project ID (proj_...): " AI_SUPPORT_PROVIDER_PROJECT_ID
php artisan ai-support:verify-provider-project \
  --project-id="$AI_SUPPORT_PROVIDER_PROJECT_ID" \
  --create-spend-alert \
  --confirm=CREATE-25-MONTHLY-SPEND-ALERT
unset OPENAI_ADMIN_KEY AI_SUPPORT_SPEND_ALERT_RECIPIENTS AI_SUPPORT_PROVIDER_PROJECT_ID
```

The verifier output is content-free operational evidence, but it does not alter the Admin readiness register. Record only the observed project match, retention type, alert status, date, and operator after reviewing the output. Keep model-improvement sharing Pending until separate account-owned evidence proves the project's actual setting.

## Data-use and retention checklist

Record `provider_data_controls` only after verifying:

- API content is not used for training unless LoLo opts in, and LoLo has not opted in.
- Every runtime and synthetic-evaluation Responses request sets `store: false`.
- No application state is created through provider conversations, files, vector stores, background mode, hosted tools, or provider memory.
- Default abuse-monitoring retention is documented as no more than 30 days, within `DEC-058`.
- The current official source is [OpenAI platform data controls](https://developers.openai.com/api/docs/guides/your-data#default-usage-policies-by-endpoint).

Record `provider_deletion` with the same official source plus code/test evidence for `store: false` and excluded stateful endpoints.

Record `provider_zdr_request` as Passed, Pending, or Failed using only a safe request/reference ID. ZDR is tracked but is not a release blocker for the bounded non-medical pilot.

## Destination and contract checklist

Record `provider_destination_contract` only after verifying:

- the actual endpoint configured for this phase;
- whether standard processing or an approved regional project applies;
- the applicable agreement/privacy terms and subprocessor reference;
- no claim of regional residency is made unless the project and endpoint prove it;
- any legal/privacy conclusion required for the initial non-medical pilot is documented outside source code.

The approved initial choice is standard provider processing without the paid residency uplift. A legal or contractual requirement overrides that choice and returns to Product.

## Versioned cost evidence

The runtime price version is `openai-gpt-5.6-luna-2026-08-14`:

| Token class | USD per million |
| --- | ---: |
| Uncached input | $0.20 |
| Cached input | $0.02 |
| Output | $1.20 |

Official source: [GPT-5.6 Luna model record](https://developers.openai.com/api/docs/models/gpt-5.6-luna).

Record `cost_monitoring` after verifying:

- $0.03 conversation warning and $0.05 conversation stop;
- $2 synthetic-rehearsal daily stop;
- $5 two-user-pilot daily stop;
- 50 model-assisted turns per pilot user per day;
- five-second conversation and eight-second tool P95 monitors over at least 20 observations.

The `$25` provider-project monthly billing alert remains recommended operational defense, but it is not required for the initial pilot under `DEC-068`.

## Operations alert evidence

Plan without mutation:

```bash
php artisan ai-support:test-operations-alert
```

Send the content-free test only after choosing the recording administrator:

```bash
php artisan ai-support:test-operations-alert \
  --send \
  --actor-email=<full-admin-email> \
  --confirm=SEND-CONTENT-FREE-ALERT
```

The command records Pending when both channel dispatches are accepted. Both administrators must personally confirm the in-app notification and email before one administrator records `operations_alert_delivery` as Passed. A failed channel opens an Admin incident. A queued or accepted email delivery is not inbox-receipt evidence.

## Downstream extinction evidence

Record `downstream_extinction_restore` only after the content-free evidence covers:

- primary database and replicas;
- database backups and snapshots;
- analytics or warehouse destinations;
- search/vector indexes and caches;
- logs and error monitoring;
- manual exports and production-derived fixtures;
- deletion propagation within the `DEC-058` maximums;
- a restore that reapplies deletion before restored data becomes accessible.

This item can reference the independent legacy extinction program, but it must distinguish current AI Support destinations from already destroyed legacy primary data.

### Structured closeout record

Copy [the pending downstream-extinction JSON template](templates/downstream-extinction-record.template.json) to a restricted working location. It requires both `retired_legacy_copilot` and `current_ai_support` across all nine categories: primary database, replicas, backups/snapshots, analytics/warehouse, search/vector indexes, caches, logs/error monitoring, manual exports/workstations, and production fixtures/clones. Use only content-free evidence references; do not add record IDs, user references, customer content, database names, credentials, excerpts, paths, or free-form notes.

Each scoped destination may pass only as `not_present`, `verified_zero`, `destroyed`, or `expired_and_verified`. These statuses apply only to the in-scope legacy or current AI Support content classes, never to every record in a shared database, log service, or backup. A controlled future expiry is still Pending and must not be represented by a complete status until the expiry and absence are actually verified. Current AI Support primary-database evidence must be `verified_zero` for customer AI content before the pilot; the retired legacy primary database must be `verified_zero` or `destroyed`.

The same record requires an isolated restore/re-deletion rehearsal proving that the restored environment remained inaccessible, retirement code and the deletion manifest were applied before access, target zero and protected-domain preservation were verified, and human support remained available after release.

Validate the completed record against the exact candidate commit:

```bash
php artisan ai-support:validate-downstream-extinction \
  /safe/path/downstream-extinction.json \
  --expected-commit=<full-40-character-release-commit>
```

The shipped template deliberately fails. The command is read-only, verifies that the commit exists in the repository, rejects missing/extra/duplicate scope-category records and every Pending state, outputs only aggregate counts and a SHA-256 record reference, and writes no Admin evidence, application record, control, grant, or provider state. Passing structure validates the operator record; it does not inspect an external system or replace source-system evidence.

## Final preflight

Expansion remains the fail-closed default:

```bash
php artisan ai-support:release-preflight
```

After `DEC-070` is deployed, inspect the exact initial-pilot scope separately:

```bash
php artisan ai-support:release-preflight --scope=initial-pilot
php artisan ai-support:release-preflight --scope=expansion
```

The initial-pilot command may report six `DEFERRED BEFORE EXPANSION` checks and still become ready. The expansion command must remain Blocked until each deferred obligation is replaced by real Passed evidence. Neither command changes evidence, controls, grants, provider state, or customer data.

Record the six accepted deferrals only through the bounded command:

```bash
php artisan ai-support:record-option-b-deferrals

php artisan ai-support:record-option-b-deferrals \
  --apply \
  --actor-email=<full-admin-email> \
  --confirm=DEFER-SIX-GATES-BEFORE-EXPANSION
```

The first invocation is plan-only. The applying invocation refuses a Failed item, requires both guards off, fail-closed stored controls, and zero grants, and records only the six fixed `DEC-070` items as Deferred through August 29. It never marks an item Passed.

When the initial-pilot preflight is green, the separate release decision remains plan-only unless `--approve` and the literal confirmation are supplied:

```bash
php artisan ai-support:approve-initial-pilot-release \
  --actor-email=<full-admin-email> \
  --release-commit=<deployed-40-character-commit> \
  --reason="Exact two-user DEC-070 pilot after green initial-pilot preflight."

php artisan ai-support:approve-initial-pilot-release \
  --approve \
  --actor-email=<full-admin-email> \
  --release-commit=<deployed-40-character-commit> \
  --reason="Exact two-user DEC-070 pilot after green initial-pilot preflight." \
  --confirm=APPROVE-EXACT-TWO-USER-PILOT
```

Approval verifies that the supplied commit equals deployed `HEAD`, stores the content-free preflight hash and exact user/date boundary, and changes no control or grant. Grant and exposure-opening control services fail closed until this record is effective.
