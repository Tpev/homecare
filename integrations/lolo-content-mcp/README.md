# LoLo Care Content MCP

The connector supports two maintained transports over the same 11 Content API tools:

- **Hosted Streamable HTTP (preferred):** `https://carelolo.com/mcp/content`, with OAuth 2.1, PKCE, dynamic client registration, per-user scopes, and no local install.
- **Local STDIO (maintainer fallback):** a project-local Node process using an expiring Content API bearer from environment variables.

Neither transport reads Laravel tables or media storage directly, and neither exposes a review-approval tool. Drafting, media, readiness, scheduling, and publishing still use the versioned Content API and its existing workflow services.

Use [hosted-content-mcp.md](../../docs/content/hosted-content-mcp.md) for the one-time production setup and Charles's short Mac setup. Use [codex-blog-quickstart.md](../../docs/content/codex-blog-quickstart.md) for editorial operations and [codex-publishing.md](../../docs/content/codex-publishing.md) for the security/incident runbook.

## Build and checks

Node.js 20 or newer is required on the application server or a local maintainer workstation.

```sh
cd integrations/lolo-content-mcp
npm ci
npm run check
npm run build
npm audit
```

Locked runtime dependencies include the official MCP TypeScript SDK. `npm start` runs STDIO; `npm run start:http` runs the loopback-only hosted service. `deploy.sh` builds the hosted artifact in the inactive release.

## Hosted configuration

The HTTP process binds only `127.0.0.1:8090`; Nginx publishes the exact `/mcp/content` endpoint. Its protected environment file contains:

- `LOLO_CONTENT_API_URL=https://carelolo.com/api/content/v1`
- `LOLO_CONTENT_API_TOKEN`, a one-time-issued `--hosted-mcp-service` credential (never commit it)
- `LOLO_CONTENT_MCP_PUBLIC_URL=https://carelolo.com/mcp/content`
- `LOLO_CONTENT_MCP_OAUTH_ISSUER=https://carelolo.com`
- `LOLO_CONTENT_MCP_PORT=8090`

The inbound Codex OAuth bearer is introspected for every MCP request and is never forwarded to the Content API. Instead, the service credential signs a 30-second, path/method-bound, one-use delegation containing the opaque OAuth session ID. Laravel rechecks that session, the user's current content role, the effective scope, and the nonce before resolving the user actor. Content API audit events therefore identify the human actor and the hosted service credential separately.

Hosted media cannot reference a path on the server or the user's Mac. The tool accepts a safe filename and canonical Base64 bytes, validates the actual PNG/JPEG/WebP/GIF signature and 20 MiB limit, then sends normal managed-media multipart input to the API.

## Local STDIO fallback

STDIO reads exactly `LOLO_CONTENT_API_URL` and `LOLO_CONTENT_API_TOKEN`. HTTP is accepted only for loopback development; production API URLs require HTTPS. The token must be a normal actor-bound Content API token, not the hosted delegation credential.

Copy the commented STDIO example in `codex.config.example.toml`, set both variables in the environment that launches Codex, build the package, and restart Codex. Never put the bearer in TOML, Git, prompts, logs, or shell arguments.

## Tools and safety

The server exposes `list_articles`, `get_article`, `list_content_options`, `create_article_draft`, `update_article`, `upload_article_media`, `preview_article`, `audit_article`, `submit_article_for_review`, `schedule_article`, and `publish_article`.

Writes use API idempotency keys; edits require current `edit_version`; preview stays protected; schedule/publish are annotated as destructive, idempotent, high-impact writes and retain explicit Codex approval prompts. The connector cannot approve review or bypass CMS readiness.

Conservative Markdown conversion supports documented headings, paragraphs, lists, safe links, quotes, tables, citations, callouts, CTAs, and FAQs. Unsupported or unsafe structures fail clearly instead of being silently changed.

All connector tests mock outbound HTTP. They never contact carelolo.com or another real Content API.
