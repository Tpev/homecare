# Publishing LoLo Care content with Codex

This integration lets a trusted Codex task work with LoLo Care articles through a versioned Content API and a local STDIO MCP server. It does not create a second publishing path. Every change still passes through the application's existing authorization, `BlogPostWorkflow`, `BlogPostReadiness`, `TiptapDocumentRenderer`, `MediaAssetManager`, optimistic `edit_version` locking, independent review, immutable published revisions, scheduling, redirects, and content-audit behavior.

Use the connector only from a trusted project and give each machine or automation its own short-lived, least-privilege token. Never place a bearer token in Git, `.env.example`, Codex configuration, a prompt, a ticket, or a log.

## Architecture and trust boundaries

```text
Codex task
  -> local STDIO process: integrations/lolo-content-mcp
     -> HTTPS + bearer token
        -> /api/content/v1
           -> token authentication, scope checks, rate/request limits,
              idempotency, policy checks, and audit events
              -> existing CMS services and workflow
                 -> working draft + immutable published revisions + managed media
```

The MCP process is an adapter, not an authority. It reads `LOLO_CONTENT_API_URL` and `LOLO_CONTENT_API_TOKEN` from its process environment, maps MCP tools to HTTP requests, and returns concise structured results. The API authenticates the token, resolves its explicit user actor, checks both the token ability and that user's current content permissions, then calls the same domain services used by the CMS. Controllers and MCP tools must never write blog tables or storage paths directly.

The principal trust boundaries are:

- **Codex task to local MCP server.** Prompts and retrieved text are untrusted input. The server exposes narrow schemas and identifies every write accurately. It must not execute content as code or emit credentials.
- **MCP server to Content API.** Use HTTPS in production. The bearer token is the machine credential and must be injected from a secret manager or operator environment. Treat the API URL as administrator-controlled configuration, not article input.
- **Content API to CMS.** Authentication is not sufficient authorization. Every endpoint requires a named ability and the actor's existing application permission. Workflow and readiness failures remain authoritative.
- **Draft to public content.** Drafts, preview URLs, and managed media are not public merely because Codex can access them. Publishing creates or selects an immutable public revision only after independent review and readiness succeed.

## Threat model and security properties

The design assumes a prompt, article body, Markdown file, source URL, or uploaded filename may be malicious. It also assumes a workstation token may eventually be exposed and limits the blast radius with expiry, revocation, least privilege, rate limiting, request-size limits, idempotency, and audit attribution.

- Tokens are generated from cryptographically random bytes, displayed once, stored only as a SHA-256 digest, and identified operationally by a non-secret prefix. Listing a token never returns the token or digest.
- Every token has an expiry, can be revoked immediately, is bound to one active content-team user, and stops working when the actor is no longer eligible. Scheduling and publishing abilities can be issued only for publisher or administrator actors.
- Abilities are endpoint-specific: `content:read`, `content:draft`, `content:media`, `content:submit`, `content:schedule`, `content:publish`, and `content:audit`. Do not issue the full set for ordinary drafting.
- Mutations require an `Idempotency-Key`. A repeated key with the same request returns the original result; reuse with a different request is rejected. Schedule and publish remain explicit operations.
- Updates require the last observed `edit_version`. A stale value returns a conflict instead of overwriting another editor. Fetch the article again, reconcile the changes, and submit a new idempotency key.
- Validation rejects unsupported document nodes, raw HTML, unsafe or non-HTTP(S) links, unknown IDs, unmanaged file paths, excessive payloads, and media that fails the existing validation pipeline. Article images are limited to 20 MiB, 12,000 pixels per side, and 25 megapixels before decoding/variant generation. The server does not fetch arbitrary article-supplied URLs or turn source URLs into server-side requests.
- A protected preview is a short-lived signed URL. It must not be pasted into public channels, indexed, stored as an article source, or treated as durable. Unpublished content is never returned by public blog routes.
- API actions create `ContentApiAuditEvent` records with token, actor, article, ability, outcome, response status, request ID, and a one-way idempotency-key fingerprint. Raw bearer tokens and idempotency keys are not logged.
- Editing, authoring, or submitting through a token does not review the article. The token actor cannot approve their own work, and the Content API deliberately exposes no review-approval endpoint or MCP tool.

The integration reduces risk but does not make generated content trustworthy. A human reviewer remains responsible for facts, sources, privacy, medical boundaries, brand language, and the final publication decision.

## API surface

All endpoints are below `/api/content/v1`, require `Authorization: Bearer <token>`, and return JSON. Collection endpoints are paginated. Responses use stable resources for articles, readiness results, content options, and media. Validation failures include field-level errors; edit conflicts include the current version needed for reconciliation.

| Operation | Endpoint | Ability |
| --- | --- | --- |
| List articles and readiness summary | `GET /posts` | `content:read` |
| Get an article, structured content, relationships, sources, and readiness | `GET /posts/{post}` | `content:read` |
| List authors, categories, tags, and managed media | `GET /options` | `content:read` |
| Create a working draft | `POST /posts` | `content:draft` |
| Update draft metadata, Tiptap content, taxonomy, related posts, and sources | `PATCH /posts/{post}` | `content:draft` |
| Upload managed article media and create its variants | `POST /posts/{post}/media` | `content:media` |
| Obtain a protected, expiring preview URL | `GET /posts/{post}/preview` | `content:read` |
| Inspect audit/readiness state | `POST /posts/{post}/audit` | `content:audit` |
| Submit the current version for independent review | `POST /posts/{post}/submit` | `content:submit` |
| Schedule an independently reviewed, ready version | `POST /posts/{post}/schedule` | `content:schedule` |
| Publish an independently reviewed, ready version now | `POST /posts/{post}/publish` | `content:publish` |

Write requests should include an opaque, unique `Idempotency-Key` header. Update, submit, schedule, and publish inputs include the article's current `edit_version`. Reuse a key only when retrying the same uncertain request; after a validation or conflict response, correct/refetch and use a new key. Read the returned `request_id` when troubleshooting; use it to find the audit event without exposing a secret.

The API accepts structured Tiptap JSON. The MCP connector also accepts conservative Markdown for authoring convenience and converts only documented structures. Supported content includes headings, paragraphs, emphasis, lists, links, block quotes, tables, citations, callouts, CTAs, and FAQs where the CMS has a corresponding supported node. Raw HTML and unknown structures fail with an actionable error rather than being dropped or approximated.

## MCP tools

The local server exposes these one-to-one workflow tools:

| Tool | Behavior | Side effect classification |
| --- | --- | --- |
| `list_articles` | Paginated article search and readiness summary | Read-only |
| `get_article` | Current article, relationships, sources, content, version, and readiness | Read-only |
| `list_content_options` | Authors, taxonomy, and managed-media choices | Read-only |
| `create_article_draft` | Create a draft through the workflow service | Write, non-destructive |
| `update_article` | Update the current working draft with optimistic locking | Write, non-destructive |
| `upload_article_media` | Upload validated managed media and generate variants | Write, non-destructive |
| `preview_article` | Obtain a protected short-lived preview URL without persisting it | Read-only |
| `audit_article` | Run and attribute a readiness/content audit | Write, non-destructive |
| `submit_article_for_review` | Submit the current version for independent human review | Write, non-destructive |
| `schedule_article` | Schedule reviewed content for public release | Write, destructive/high impact, idempotent |
| `publish_article` | Publish reviewed content immediately | Write, destructive/high impact, idempotent |

There is intentionally no `approve_review` tool. A human editor or publisher who is independent from the author, last editor, and submitter approves review in the existing CMS. API and MCP annotations help Codex make approval decisions, but server-side abilities, policies, review separation, readiness checks, and idempotency remain the security controls.

All connector tools are open-world because they call the configured Content API over the network. `schedule_article` and `publish_article` additionally advertise `readOnlyHint = false`, `destructiveHint = true`, and `idempotentHint = true`. Treat annotations as approval-routing metadata, not as a replacement for server enforcement.

## Issue, inspect, rotate, and revoke tokens

Run token commands on the application host as the same unprivileged operating-system user used for Laravel administration. Attribute issuance and revocation to an administrator with `--issued-by` and `--revoked-by` in production.

### Issue a least-privilege token

This example creates a 30-day authoring token. Replace the example identities with real user IDs or emails. The actor must already be an active content-team user.

```bash
php artisan content:token:issue codex-author@example.com "Codex editorial workstation" \
  --ability=content:read \
  --ability=content:draft \
  --ability=content:media \
  --ability=content:submit \
  --ability=content:audit \
  --ttl=43200 \
  --issued-by=admin@example.com
```

The command prints the bearer token once. Copy it directly to the approved secret store, clear it from the terminal if the terminal retains scrollback, and do not redirect output to a file. Prefer a separate, shorter-lived publisher token only when scheduling or publishing from Codex is genuinely required:

```bash
php artisan content:token:issue codex-publisher@example.com "Codex controlled publishing" \
  --ability=content:read \
  --ability=content:audit \
  --ability=content:schedule \
  --ability=content:publish \
  --ttl=10080 \
  --issued-by=admin@example.com
```

Avoid long-lived all-scope tokens. The maximum lifetime is one year, but the operational target should be days or weeks. An actor with author/editor permissions cannot receive publisher abilities.

### List metadata safely

```bash
php artisan content:token:list --active
php artisan content:token:list --actor=codex-author@example.com
```

The listing contains IDs, names, actors, abilities, expiry, last-use time, revocation state, issuer, and safe prefixes. It never contains token material or token hashes.

### Rotate without downtime

1. Issue a new token for the same actor with the minimum required abilities and a new expiry.
2. Replace `LOLO_CONTENT_API_TOKEN` in the approved workstation or secret manager; do not edit `.codex/config.toml` with the value.
3. Restart Codex so the STDIO server receives the new environment.
4. Run a read-only `list_articles` request and confirm the new token's last-use time with `content:token:list`.
5. Revoke the old token by numeric ID or displayed safe prefix.

```bash
php artisan content:token:revoke 42 --revoked-by=admin@example.com
# Or use at least four unambiguous hexadecimal characters after the safe prefix:
php artisan content:token:revoke lolo_content_1a2b --revoked-by=admin@example.com
```

Revocation is immediate. It does not delete attribution, revisions, audit events, or published content.

## Build and configure the local MCP server

From a trusted checkout with Node.js 20 or newer, install exactly the locked dependencies and build the connector:

```bash
npm --prefix integrations/lolo-content-mcp ci
npm --prefix integrations/lolo-content-mcp run build
npm --prefix integrations/lolo-content-mcp test
```

Export configuration in the operator's process environment through the organization's secret-management mechanism. The API URL should be the origin plus the versioned API base, and production must use HTTPS.

```bash
export LOLO_CONTENT_API_URL="https://carelolo.com/api/content/v1"
export LOLO_CONTENT_API_TOKEN="<retrieve from the approved secret manager>"
```

Do not add either value to a tracked `.env` file. In particular, never write a token into `.env.example` or the TOML below.

Codex supports local STDIO MCP servers, forwarded environment variables, and project-scoped `.codex/config.toml` for trusted projects. See the [official Codex MCP documentation](https://developers.openai.com/codex/mcp). Add this example to the trusted project's `.codex/config.toml`, replacing only the absolute checkout path:

```toml
[mcp_servers.lolo_content]
command = "node"
args = ["integrations/lolo-content-mcp/dist/index.js"]
cwd = "/absolute/path/to/homecare"
env_vars = ["LOLO_CONTENT_API_URL", "LOLO_CONTENT_API_TOKEN"]
default_tools_approval_mode = "writes"
startup_timeout_sec = 15
tool_timeout_sec = 90

[mcp_servers.lolo_content.tools.schedule_article]
approval_mode = "prompt"

[mcp_servers.lolo_content.tools.publish_article]
approval_mode = "prompt"
```

`env_vars` forwards values already present in Codex's environment; it does not store them in the project file. `default_tools_approval_mode = "writes"` prompts for tools that are not marked read-only. The explicit `prompt` overrides preserve a user decision point for scheduling and publishing even if annotations or future defaults change.

Do **not** set `approval_mode = "approve"` on `schedule_article` or `publish_article`. In Codex configuration, `approve` means the tool is pre-approved; it is not an instruction to show an approval prompt. It is suitable only for a tool an administrator has intentionally chosen to allow without a prompt. Scheduling and publishing are high-impact external writes and must retain an approval step.

Restart Codex after changing the environment or configuration. Use `/mcp` in Codex, or `codex mcp list`, to confirm that `lolo_content` starts and that tool annotations are visible. A configuration error should fail closed: fix the absolute path, Node runtime, build, environment, or HTTPS endpoint rather than copying the token into the file.

## Editorial workflow

1. **Discover.** Use `list_content_options` and `list_articles` to select a real author profile, taxonomy, existing media, and non-duplicative topic.
2. **Draft.** Use `create_article_draft`. Supply a unique idempotency key and retain the returned article ID and `edit_version`.
3. **Develop.** Use `update_article` for metadata, structured content or supported Markdown, taxonomy, related posts, and stable sources. Use `upload_article_media` for files; reference only returned managed-media IDs.
4. **Inspect.** Use `preview_article`, then `audit_article`. Resolve every blocking readiness issue and assess warnings. Verify citations against their sources outside the CMS when accuracy matters.
5. **Submit.** Use `submit_article_for_review` with the current `edit_version`. This freezes the handoff point but does not approve it.
6. **Review outside the API.** A different qualified editor or publisher signs into the CMS, reviews the content and source evidence, records substantive notes and visible credentials, and approves it. The reviewer cannot be the author, last editor, submitter, or token actor when those identities conflict.
7. **Publish deliberately.** Fetch the article again. Confirm the approved revision and readiness. After an explicit Codex approval prompt, call either `schedule_article` with an unambiguous ISO-8601 timestamp and timezone or `publish_article` for immediate release. Never include both intentions in one request.
8. **Verify.** Fetch the article, check its workflow state and public URL, then confirm the immutable revision and actor/audit trail as described below.

Any edit after approval may invalidate the review and return the article to the required workflow state. Never work around that by reusing an earlier response, changing tables manually, or repeatedly retrying a conflict.

## Example Codex prompts

Drafting with no publication authority:

> List the available content authors and categories, then create a draft about preparing a home for overnight care. Use the least-invasive existing taxonomy, cite only the sources I provide, run the content audit, and stop after showing me the protected preview and readiness issues. Do not submit, schedule, or publish.

Updating safely:

> Fetch article 123 and summarize its current edit version and readiness. Add the two supplied sources and revise the FAQ using supported structured content. If the edit version conflicts or a Markdown structure is unsupported, stop and show the actionable error instead of replacing content.

Independent-review handoff:

> Audit article 123. If it has no blocking issues, submit its current version for independent review and report the article ID, edit version, submitter actor, and remaining human steps. Do not approve review; there is no API tool for that.

Publishing after human approval:

> Fetch article 123 and verify that an independent reviewer approved the current ready version. Show the exact title, slug, reviewer, readiness result, and intended public URL. Then ask for approval before publishing it now. Do nothing if the review or edit version is stale.

Scheduling after human approval:

> Fetch article 123 and verify its current independent review and readiness. Propose scheduling it for 2026-08-20 at 09:00 Europe/Paris, show the equivalent ISO-8601 timestamp and article details, and wait for the schedule tool approval. Do not publish immediately.

## Production setup and verification

Production is the live `https://carelolo.com` site. A push to `master` does not replace the deployment procedure. An administrator must take or verify the normal backup, confirm the release commit, and run the repository's reviewed `deploy.sh` from the application host. Do not reconstruct its steps manually and do not let Codex invoke it unattended.

```bash
ssh <production-host>
cd /var/www/homecare
git fetch origin
git log -1 --oneline origin/master
./deploy.sh
```

`deploy.sh` acquires `/tmp/homecare-deploy.lock`, enters maintenance mode, updates `master` with `git pull --ff-only`, installs locked PHP and Node dependencies, builds production assets, runs migrations, regenerates caregiver image variants, rebuilds Laravel caches, restarts queue workers and PHP-FPM, builds/restarts the voice agent, validates/reloads nginx, and checks both application and voice-agent health. Its exit trap brings Laravel back up after a failure. Investigate any failed step before retrying; do not bypass it with an ad hoc partial deployment.

The Content MCP process is built and run only on the trusted Codex operator workstation. It is not a public production daemon and does not need to be installed or started by `deploy.sh`.

After `deploy.sh` succeeds, verify the new API and scheduler from the production application directory:

```bash
cd /var/www/homecare
php artisan route:list --path=api/content/v1
php artisan schedule:list
php artisan content:audit --fail-on-issues
curl -fsS https://carelolo.com/ >/dev/null
```

Confirm that the existing queue worker and every-minute Laravel scheduler are healthy. `content:publish-scheduled` must remain scheduled every minute with overlap protection. Keep the existing `storage:link` and durable public-disk setup for managed media.

The scheduler also runs Laravel's model pruner daily for expired Content API idempotency records. Keep that entry enabled: response snapshots can contain unpublished article data and should not outlive their configured replay window.

```cron
* * * * * cd /var/www/homecare && php artisan schedule:run >> /dev/null 2>&1
```

Install this cron entry only if the host does not already run Laravel's scheduler; duplicate schedulers are not safe. Keep the existing supervised queue-worker service and restart it through the host's service manager after deployment. The Content API adds no new public daemon.

Ensure the reverse proxy:

- serves `/api/content/v1` only over HTTPS;
- passes the `Authorization`, `Idempotency-Key`, content-type, request-ID, and standard forwarding headers without logging their values;
- enforces the application's request/body limits and does not cache authenticated API or preview responses;
- keeps protected preview routes behind signed expiry validation; and
- does not expose Laravel logs, storage paths, or the MCP process.

Issue production tokens only after the release and endpoint checks pass. Start with `content:read`, validate a read-only request from the intended workstation, then add a separate authoring or publishing token only if operationally necessary.

### Verify actor attribution and the audit trail

1. Run `php artisan content:token:list --active --actor=<actor-email>` and verify the token name, abilities, expiry, last use, safe prefix, and issuer.
2. In Content administration, open the article and inspect its created/updated/submitted/published actor fields, independent reviewer, review notes, revisions, and current/published version.
3. Correlate the API response's `request_id` with `content_api_audit_events`. Verify `content_api_token_id`, `actor_user_id`, `blog_post_id`, `action`, `ability`, `outcome`, `response_status`, and `occurred_at`. The event must not contain the bearer token, request body, raw idempotency key, or protected preview signature.
4. Inspect `blog_post_revisions` for the matching `actor_user_id`, change summary, monotonically increasing revision number, and immutable published snapshot. A published snapshot should match what the public route renders even if a new working draft is started later.
5. Run `php artisan content:audit --fail-on-issues`, then fetch the public article, canonical URL, structured data, media variants, redirects after a slug change, and expected cache behavior.

Use read-only database access for database-level verification. Do not correct attribution or workflow state with SQL; repair issues through the CMS/domain workflow so a new revision and audit record are produced.

## Rollback

Application rollback and content rollback are separate decisions.

For an integration release problem:

1. Revoke affected tokens immediately if requests are unsafe or unaccounted for.
2. Disable or remove the `lolo_content` entry from the trusted project's Codex configuration and restart Codex.
3. Put the application into maintenance mode if the API could corrupt state, then deploy the last known-good application revision using the normal release process.
4. Run the repository's `./deploy.sh` for that known-good `master` revision, then repeat the route, scheduler, content-audit, and public health checks.
5. Do not roll back a migration destructively until its migration and data impact have been reviewed and a backup has been verified. Leaving new access/audit tables in place is safer than dropping evidence during an incident.

For an incorrect article, use the CMS workflow to archive it or create a corrected working draft, obtain independent review, and republish. Do not overwrite or delete the immutable published revision. Preserve redirects and audit history. If scheduled publication is wrong, cancel or correct it through the CMS before it is due; revoking a token does not cancel an already scheduled article.

## Incident response

For a suspected credential leak, unauthorized tool call, lost workstation, unexpected publication, or unexplained audit event:

1. Revoke the affected token by numeric ID or safe prefix. If scope is uncertain, revoke every token for that actor and disable the Codex MCP configuration.
2. Preserve evidence: token metadata, request IDs, API/audit events, reverse-proxy access metadata with authorization values redacted, article revisions, review records, scheduler/queue output, and the relevant time window. Do not copy secrets into the incident record.
3. Identify affected articles and operations from `content_api_audit_events`; compare API events with blog revisions and immutable published snapshots. Check idempotency reuse, conflicts, media uploads, preview creation, submission, scheduling, and publication.
4. Remove public exposure through the supported CMS archive/correction workflow, not direct SQL. Rotate any preview links by allowing them to expire or invalidating the relevant signing key only after assessing the application-wide effect.
5. Rotate the token with a narrower scope and shorter expiry only after the workstation and process environment are trusted again. Rotate unrelated credentials only when evidence shows they may also be exposed.
6. Patch the cause, run the focused API/MCP/security tests and `content:audit --fail-on-issues`, review logs for secret leakage, and document actor attribution and the final content state before restoring access.

If a raw token reaches logs, source control, prompt history, or a ticket, treat it as compromised even if no misuse is visible: revoke it, purge it through the system's approved retention procedure, and issue a replacement. Never test a suspected stolen credential against production.
