# Provider Privacy and Operations Evidence Checklist

Status: Approved checklist; execution in progress

Last updated: August 15, 2026

Owner: Either full administrator

## Purpose

Close the provider, destination, monitoring, and alert portions of `DEC-067` without copying credentials, customer content, complete prompts, or unnecessary personal information into evidence.

The Admin [Release readiness](/admin/ai-support/readiness) workspace is the canonical evidence register. Either full administrator may complete every item. Recording evidence never changes a deployment guard, stored control, or pilot grant.

## Provider project checklist

Record `provider_project_configuration` only after verifying:

- AI Support uses a dedicated OpenAI project rather than an unrestricted organization-wide key.
- The server credential is restricted to the required Responses operation and is stored only as a server secret.
- Model-improvement data sharing is not enabled for this project.
- The credential does not appear in browser state, application logs, database content, Admin evidence, source control, or build artifacts.
- `AI_SUPPORT_SAFETY_IDENTIFIER_SECRET` is a separate random secret of at least 32 characters and is not the API key or application key.
- The release preflight reports the credential and safety-secret prerequisites as present without displaying either value.

Safe evidence: project name or non-secret reference, checked date, operator, restriction summary, and source system/reference. Never paste the API key or safety secret.

### Server-only verification route

The operator has directed that this release exercise must not connect to the OpenAI website. Use content-free production configuration checks and, when available, the provider Admin API or an account-owned export/receipt. Do not weaken the evidence claim to accommodate that constraint.

- A successful standard API request proves that a credential authenticates to the configured destination. A project-scoped `sk-proj-` key shape is supporting evidence, not proof of the intended project identity.
- Project identity requires a non-secret intended-project ID/name matched to provider-account evidence. If no Admin API credential or account-owned evidence is available, keep `provider_project_configuration` Pending.
- Confirming model-improvement sharing and project retention settings requires project-level account evidence. The documented default policy does not prove the project's current opt-in state.
- Creating or verifying the `$25` monthly spend alert requires project-level account evidence. The application's `$25` configuration value alone does not prove that the provider alert exists.
- Never paste an Admin API key, project API key, safety secret, full provider response, customer content, or prompt into the terminal transcript or readiness evidence.

The official Admin API reference checked on August 15, 2026 now documents server-side endpoints that can close part of this evidence gap when an organization-owned Admin API credential is available:

- `GET /v1/organization/projects/{project_id}` retrieves the intended non-secret project record;
- `GET /v1/organization/projects/{project_id}/api_keys` lists that project's API-key records with redacted values;
- `GET /v1/organization/projects/{project_id}/data_retention` retrieves the configured project retention type;
- `GET /v1/organization/projects/{project_id}/spend_alerts` lists project spend alerts;
- `POST /v1/organization/projects/{project_id}/spend_alerts` creates an alert. The approved `$25` monthly alert uses `threshold_amount: 2500`, `currency: USD`, `interval: month`, and the approved email recipients.

Use a separate, ephemeral `OPENAI_ADMIN_KEY` for these Administration endpoints. Do not store it in application configuration, pass it as a visible command argument, or substitute the production project key. Verify an existing alert before creating one to prevent duplicates, and require an explicit confirmation before the provider write. The documented Admin API surface does not expose the model-improvement data-sharing opt-in state; that setting still requires account-owned evidence and must remain Pending if none is supplied.

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
- $25 provider-project monthly billing alert;
- five-second conversation and eight-second tool P95 monitors over at least 20 observations.

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

## Final preflight

Run the read-only preflight at any time:

```bash
php artisan ai-support:release-preflight
```

A non-zero result means at least one release gate remains blocked. The command never changes evidence, controls, grants, provider state, or customer data.
