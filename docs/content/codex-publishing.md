# Publishing LoLo Care content with Codex

For the short, repeatable operator workflow, start with [codex-blog-quickstart.md](codex-blog-quickstart.md). This document is the detailed architecture, security, deployment, and incident-response reference.

This integration lets a trusted Codex task work with LoLo Care articles through a versioned Content API and either the preferred hosted Streamable HTTP MCP server or the maintained local STDIO fallback. It does not create a second publishing path. Every change still passes through the application's existing authorization, `BlogPostWorkflow`, `BlogPostReadiness`, `TiptapDocumentRenderer`, `MediaAssetManager`, optimistic `edit_version` locking, immutable published revisions, scheduling, redirects, and content-audit behavior. Independent review is an optional deployment policy controlled by `CONTENT_REQUIRE_INDEPENDENT_REVIEW`; LoLo Care currently defaults it off. For exact server and Charles setup, use [hosted-content-mcp.md](hosted-content-mcp.md).

Use the connector only from a trusted project and give each machine or automation its own short-lived, least-privilege token. Never place a bearer token in Git, `.env.example`, Codex configuration, a prompt, a ticket, or a log.

## Architecture and trust boundaries

```text
Codex task
  -> hosted HTTPS/OAuth MCP (preferred), or local STDIO fallback
     -> signed user delegation + separate server-only bearer
        -> /api/content/v1
           -> token authentication, scope checks, rate/request limits,
              idempotency, policy checks, and audit events
              -> existing CMS services and workflow
                 -> working draft + immutable published revisions + managed media
```

The MCP process is an adapter, not an authority. Hosted mode introspects the user's audience-bound OAuth bearer, then calls the Content API with a separate server-only credential plus a short-lived signed actor delegation. Local STDIO reads `LOLO_CONTENT_API_URL` and `LOLO_CONTENT_API_TOKEN` from its environment. In both modes the API resolves the accountable user actor, checks effective scope and current content permission, and calls the same domain services used by the CMS. Controllers and MCP tools must never write blog tables or storage paths directly.

The principal trust boundaries are:

- **Codex task to MCP server.** Prompts and retrieved text are untrusted input. The server exposes narrow schemas and identifies every write accurately. It must not execute content as code or emit credentials. Hosted users authenticate with OAuth/PKCE; the local fallback uses an environment-injected Content API token.
- **MCP server to Content API.** Use HTTPS in production. The bearer token is the machine credential and must be injected from a secret manager or operator environment. Treat the API URL as administrator-controlled configuration, not article input.
- **Content API to CMS.** Authentication is not sufficient authorization. Every endpoint requires a named ability and the actor's existing application permission. Workflow and readiness failures remain authoritative.
- **Draft to public content.** Drafts, preview URLs, and managed media are not public merely because Codex can access them. Publishing creates or selects an immutable public revision only after readiness succeeds and an authorized publisher explicitly approves the high-impact operation. When independent-review policy is enabled, that review is an additional gate.

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
- Editing, authoring, or submitting through a token never fabricates a review record. The Content API deliberately exposes no review-approval endpoint or MCP tool. If independent-review policy is enabled, the token actor cannot approve their own work.

The integration reduces risk but does not make generated content trustworthy. With the current review policy disabled, the human publisher is responsible for facts, sources, privacy, medical boundaries, brand language, readiness, and the final publication decision.

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
| Submit the current version when optional independent review is enabled | `POST /posts/{post}/submit` | `content:submit` |
| Schedule a ready version | `POST /posts/{post}/schedule` | `content:schedule` |
| Publish a ready version now | `POST /posts/{post}/publish` | `content:publish` |

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
| `submit_article_for_review` | Submit the current version when optional independent review is enabled | Write, non-destructive |
| `schedule_article` | Schedule ready content for public release | Write, destructive/high impact, idempotent |
| `publish_article` | Publish ready content immediately | Write, destructive/high impact, idempotent |

There is intentionally no `approve_review` tool. When `CONTENT_REQUIRE_INDEPENDENT_REVIEW=true`, a human editor or publisher who is independent from the author, last editor, and submitter approves review in the existing CMS. With the default `false` setting, submission and approval are skipped; abilities, readiness checks, explicit publish approval, immutable revisions, idempotency, and audit attribution remain active controls.

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

## Configure the hosted MCP (preferred)

The hosted service is deployed at `https://carelolo.com/mcp/content`. Each operator uses the Codex Settings UI to add that URL and selects OAuth/Authenticate. The browser redirects to the LoLo Care login/consent page; no Node installation, checkout, environment variable, or Content API token belongs on the operator's computer. See [hosted-content-mcp.md](hosted-content-mcp.md) for the exact administrator, Nginx, systemd, rotation, and Charles steps.

Equivalent Codex configuration is:

```toml
[mcp_servers.lolo_content]
url = "https://carelolo.com/mcp/content"
auth = "oauth"
default_tools_approval_mode = "writes"
startup_timeout_sec = 20
tool_timeout_sec = 120

[mcp_servers.lolo_content.tools.schedule_article]
approval_mode = "prompt"

[mcp_servers.lolo_content.tools.publish_article]
approval_mode = "prompt"
```

The hosted service follows the current official [MCP Streamable HTTP transport](https://modelcontextprotocol.io/specification/2025-11-25/basic/transports) and [MCP authorization](https://modelcontextprotocol.io/specification/2025-11-25/basic/authorization) specifications. Laravel provides OAuth authorization-server and protected-resource metadata, dynamic loopback-only client registration, authorization code + PKCE, rotating refresh tokens, revocation, and authenticated introspection. The Node process binds only to loopback behind Nginx.

## Build and configure the local STDIO fallback

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

Codex also supports local STDIO MCP servers, forwarded environment variables, and project-scoped `.codex/config.toml` for trusted maintainer projects. See the [official Codex MCP documentation](https://learn.chatgpt.com/docs/extend/mcp?surface=cli). Add this fallback example to the trusted project's `.codex/config.toml`, replacing only the absolute checkout path and using a different name if hosted mode is also configured:

```toml
[mcp_servers.lolo_content_local]
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

Restart Codex after changing the environment or configuration. Use `/mcp` in Codex, or `codex mcp list`, to confirm that the server starts and tool annotations are visible. A configuration error should fail closed: fix the URL/OAuth connection or, for fallback mode, the path, Node runtime, build, environment, or HTTPS endpoint rather than copying a token into the file.

## Editorial workflow

The default LoLo Care workflow uses `CONTENT_REQUIRE_INDEPENDENT_REVIEW=false`. Set it to `true` and rebuild the Laravel config cache to restore the separate submit-and-review steps.

1. **Discover.** Use `list_content_options` and `list_articles` to select a real author profile, taxonomy, existing media, and non-duplicative topic.
2. **Draft.** Use `create_article_draft`. Supply a unique idempotency key and retain the returned article ID and `edit_version`.
3. **Develop.** Use `update_article` for metadata, structured content or supported Markdown, taxonomy, related posts, and stable sources. Use `upload_article_media` for files; reference only returned managed-media IDs.
4. **Inspect.** Use `preview_article`, then `audit_article`. Resolve every blocking readiness issue and assess warnings. Verify citations against their sources outside the CMS when accuracy matters.
5. **Decide deliberately.** A human publisher previews the article and accepts responsibility for its sources, claims, scope, brand language, and current readiness result.
6. **Publish deliberately.** Fetch the article again and confirm its latest readiness. After an explicit Codex approval prompt, call either `schedule_article` with an unambiguous ISO-8601 timestamp and timezone or `publish_article` for immediate release. Never include both intentions in one request.
7. **Optional review mode.** When `CONTENT_REQUIRE_INDEPENDENT_REVIEW=true`, use `submit_article_for_review` after the audit and have a different qualified editor or publisher approve it in the CMS before publication.
8. **Verify.** Fetch the article, check its workflow state and public URL, then confirm the immutable revision and actor/audit trail as described below.

Every edit changes the working version and readiness must be checked again. Never work around the workflow by reusing an earlier response, changing tables manually, or repeatedly retrying a conflict.

## Example Codex prompts

Drafting with no publication authority:

> List the available content authors and categories, then create a draft about preparing a home for overnight care. Use the least-invasive existing taxonomy, cite only the sources I provide, run the content audit, and stop after showing me the protected preview and readiness issues. Do not submit, schedule, or publish.

Updating safely:

> Fetch article 123 and summarize its current edit version and readiness. Add the two supplied sources and revise the FAQ using supported structured content. If the edit version conflicts or a Markdown structure is unsupported, stop and show the actionable error instead of replacing content.

Optional independent-review handoff when that deployment policy is enabled:

> Audit article 123. If it has no blocking issues, submit its current version for independent review and report the article ID, edit version, submitter actor, and remaining human steps. Do not approve review; there is no API tool for that.

Publishing after readiness and human publisher approval:

> Fetch article 123 and verify its current readiness. Show the exact title, slug, readiness result, and intended public URL. Then ask for explicit approval before publishing it now. Do nothing if the edit version is stale.

Scheduling after human publisher approval:

> Fetch article 123 and verify its current readiness. Propose scheduling it for 2026-08-20 at 09:00 Europe/Paris, show the equivalent ISO-8601 timestamp and article details, and wait for the schedule tool approval. Do not publish immediately.

## Production setup and verification

Production is the live `https://carelolo.com` site. A push to `master` does not replace the deployment procedure. An administrator must take or verify the normal backup, confirm the release commit, and run the repository's reviewed `deploy.sh` from the application host. Do not reconstruct its steps manually and do not let Codex invoke it unattended.

`CONTENT_REQUIRE_INDEPENDENT_REVIEW=false` is the current default. It removes review from readiness and lets an authorized publisher publish a ready draft directly. Set it to `true` to restore the independent-review gate; after changing it, run the normal deployment so `config:cache` is rebuilt.

From an existing administrator terminal on the production host, run only the reviewed deployment entry point:

```bash
cd /var/www/homecare
./deploy.sh
```

`deploy.sh` acquires `/tmp/homecare-deploy.lock`, fetches `master` into its local mirror, constructs an inactive release, installs locked PHP/root Node/connector Node dependencies, builds frontend/hosted-MCP/voice artifacts, runs backward-compatible migrations, rebuilds Laravel caches, validates the release and Nginx configuration, then switches `/var/www/homecare` atomically without maintenance mode. It reloads PHP-FPM, restarts queue/voice runtimes, and restarts the Content MCP only when its systemd unit is installed. Laravel and voice health remain deployment gates; an additive MCP restart/health failure is reported without taking the healthy live site down.

The preferred Content MCP is a production daemon bound to `127.0.0.1:8090` and exposed only at the exact authenticated Nginx location `/mcp/content`. The local STDIO process remains a maintainer fallback.

After `deploy.sh` succeeds, verify the new API and scheduler from the production application directory:

```bash
cd /var/www/homecare
php artisan route:list --path=api/content/v1
php artisan route:list --path=oauth
php artisan route:list --path=.well-known
php artisan schedule:list
php artisan content:audit --fail-on-issues
curl -fsS http://127.0.0.1:8090/healthz
curl -fsS https://carelolo.com/ >/dev/null
```

Confirm that the existing queue worker and every-minute Laravel scheduler are healthy. `content:publish-scheduled` must remain scheduled every minute with overlap protection. Keep the existing `storage:link` and durable public-disk setup for managed media.

The scheduler also runs Laravel's model pruner daily for expired Content API idempotency records. Keep that entry enabled: response snapshots can contain unpublished article data and should not outlive their configured replay window.

```cron
* * * * * cd /var/www/homecare && php artisan schedule:run >> /dev/null 2>&1
```

Install this cron entry only if the host does not already run Laravel's scheduler; duplicate schedulers are not safe. Keep the existing supervised queue-worker service and restart it through the host's service manager after deployment. The hosted MCP adds one loopback-only Node daemon; Nginx is its sole public boundary.

Ensure the reverse proxy:

- serves `/api/content/v1` only over HTTPS;
- passes the `Authorization`, `Idempotency-Key`, content-type, request-ID, and standard forwarding headers without logging their values;
- enforces the application's request/body limits and does not cache authenticated API or preview responses;
- keeps protected preview routes behind signed expiry validation; and
- proxies only the exact `/mcp/content` endpoint to the loopback MCP process, validates Nginx configuration before reload, and never exposes Laravel logs or storage paths.

Issue the hosted server-only delegation token only after the release and OAuth metadata checks pass, then keep it solely in `/var/www/homecare-deploy/shared/content-mcp.env`. Human hosted users receive OAuth scopes through consent and their current content role. Normal actor-bound Content API tokens remain available only for the local STDIO fallback.

### Verify actor attribution and the audit trail

1. For hosted mode, run `php artisan content-mcp:session:list --active --actor=<actor-email>` and verify the OAuth session, Codex client, scopes, expiry, and last use. Separately run `php artisan content:token:list --active` and verify exactly one intended `hosted MCP service` credential. For local mode, filter that token list by actor.
2. In Content administration, open the article and inspect its created/updated/published actor fields, revisions, and current/published version. If optional review mode was used, also inspect its submitter, reviewer, and review notes.
3. Correlate the API response's `request_id` with `content_api_audit_events`. Verify `content_api_token_id`, `actor_user_id`, `blog_post_id`, `action`, `ability`, `outcome`, `response_status`, and `occurred_at`. The event must not contain the bearer token, request body, raw idempotency key, or protected preview signature.
4. Inspect `blog_post_revisions` for the matching `actor_user_id`, change summary, monotonically increasing revision number, and immutable published snapshot. A published snapshot should match what the public route renders even if a new working draft is started later.
5. Run `php artisan content:audit --fail-on-issues`, then fetch the public article, canonical URL, structured data, media variants, redirects after a slug change, and expected cache behavior.

Use read-only database access for database-level verification. Do not correct attribution or workflow state with SQL; repair issues through the CMS/domain workflow so a new revision and audit record are produced.

## Rollback

Application rollback and content rollback are separate decisions.

For an integration release problem:

1. Revoke affected tokens immediately if requests are unsafe or unaccounted for.
2. Revoke affected hosted user sessions and stop `homecare-content-mcp` if necessary; this leaves the site, CMS UI, scheduler, bookings, payments, and voice agent running. For local mode, disable/remove the STDIO server configuration and restart Codex.
3. Deploy the last known-good application revision using the normal atomic release process. Do not put the live site into maintenance mode merely for an MCP incident.
4. Run the repository's `./deploy.sh` for that known-good `master` revision, then repeat the route, scheduler, content-audit, and public health checks.
5. Do not roll back a migration destructively until its migration and data impact have been reviewed and a backup has been verified. Leaving new access/audit tables in place is safer than dropping evidence during an incident.

For an incorrect article, use the CMS workflow to archive it or create a corrected working draft, re-run readiness, and republish deliberately. Do not overwrite or delete the immutable published revision. Preserve redirects and audit history. If scheduled publication is wrong, cancel or correct it through the CMS before it is due; revoking a token does not cancel an already scheduled article.

## Incident response

For a suspected credential leak, unauthorized tool call, lost workstation, unexpected publication, or unexplained audit event:

1. In hosted mode, run `content-mcp:session:revoke <actor> --revoked-by=<admin>` and remove the actor's content role if account compromise is suspected. If the server credential may be exposed, rotate/revoke it and restart the MCP service. In local mode, revoke the affected token by numeric ID/safe prefix and disable that Codex configuration.
2. Preserve evidence: token metadata, request IDs, API/audit events, reverse-proxy access metadata with authorization values redacted, article revisions, review records, scheduler/queue output, and the relevant time window. Do not copy secrets into the incident record.
3. Identify affected articles and operations from `content_api_audit_events`; compare API events with blog revisions and immutable published snapshots. Check idempotency reuse, conflicts, media uploads, preview creation, submission, scheduling, and publication.
4. Remove public exposure through the supported CMS archive/correction workflow, not direct SQL. Rotate any preview links by allowing them to expire or invalidating the relevant signing key only after assessing the application-wide effect.
5. Rotate the token with a narrower scope and shorter expiry only after the workstation and process environment are trusted again. Rotate unrelated credentials only when evidence shows they may also be exposed.
6. Patch the cause, run the focused API/MCP/security tests and `content:audit --fail-on-issues`, review logs for secret leakage, and document actor attribution and the final content state before restoring access.

If a raw token reaches logs, source control, prompt history, or a ticket, treat it as compromised even if no misuse is visible: revoke it, purge it through the system's approved retention procedure, and issue a replacement. Never test a suspected stolen credential against production.
