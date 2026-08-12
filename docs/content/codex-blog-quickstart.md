# LoLo Care Codex blog quickstart

Use this guide when creating, updating, previewing, auditing, or publishing a LoLo Care article through Codex. The detailed security and operations reference remains in [codex-publishing.md](codex-publishing.md).

Production is `https://carelolo.com`. The Content API base is:

```text
https://carelolo.com/api/content/v1
```

## The three identities to keep separate

1. **Token actor:** the application user whose permissions and audit identity are used by the Content API. For example, `codex-author@carelolo.com`. This must be a real, active application user with a Content role.
2. **Public author:** the person shown to readers in the byline. For LoLo Care articles, select the existing public author profile named **Julie** unless the article brief says otherwise.
3. **Token issuer:** the administrator recorded as issuing or revoking the machine credential.

The token actor does not automatically become the public author. Always tell Codex to use Julie's existing public author profile rather than creating another Julie or publishing under the service user's name.

## Safety rules

- Never put a bearer token in Git, a prompt, a screenshot, a ticket, a Markdown file, `.env.example`, or `.codex/config.toml`.
- Give each workstation its own short-lived token. Revoke it when it is no longer needed.
- Use the MCP tools and Content API workflow. Never edit blog tables, revisions, or managed-media paths directly.
- Keep `publish_article` and `schedule_article` approval-gated. Publishing is always a deliberate external write.
- Codex must not SSH to production. A human administrator performs server commands and deployments.
- Using an already-built local connector does not require deploying the Laravel application.
- Production code deployments use the reviewed `deploy.sh`; do not reproduce its steps manually on the live site.

## One-time application setup

### 1. Create or verify the token actor

In Content administration, verify that the service user exists, is active, and has the required Content role.

- An Author or Editor actor can draft but cannot receive publishing abilities.
- A Publisher or Administrator actor is required for `content:schedule` and `content:publish`.
- The public author profile Julie is separate from this user account.

If the token command reports `No user matched the actor identifier`, stop. Create or activate the application user, assign its Content role, and rerun the command with the exact user ID or email.

### 2. Issue the right token

Run this manually from `/var/www/homecare` on the application host. For drafting only:

```bash
php artisan content:token:issue codex-author@carelolo.com "Codex editorial workstation" \
  --ability=content:read \
  --ability=content:draft \
  --ability=content:media \
  --ability=content:audit \
  --ttl=10080 \
  --issued-by=<administrator-email>
```

For a short-lived actor that is allowed to create and publish, the actor must have the Publisher or Administrator Content role:

```bash
php artisan content:token:issue <publisher-actor-email> "Codex controlled publishing" \
  --ability=content:read \
  --ability=content:draft \
  --ability=content:media \
  --ability=content:audit \
  --ability=content:schedule \
  --ability=content:publish \
  --ttl=10080 \
  --issued-by=<administrator-email>
```

`10080` minutes is seven days. The secret is displayed once. Put it directly into the approved local secret/environment mechanism and do not save it in the repository.

Production currently uses `CONTENT_REQUIRE_INDEPENDENT_REVIEW=false`. Do not add `content:submit` merely out of habit. If that setting is intentionally changed to `true`, issue `content:submit` as needed and restore the separate human-review step.

Useful administrative commands:

```bash
php artisan content:token:list --active
php artisan content:token:list --actor=codex-author@carelolo.com
php artisan content:token:revoke <token-id> --revoked-by=<administrator-email>
```

The list command never displays token secrets or hashes.

## One-time workstation setup

### 1. Build the connector from the repository root

On the current Windows workstation:

```powershell
cd C:\Users\Epicpp\homecare
npm --prefix integrations/lolo-content-mcp ci
npm --prefix integrations/lolo-content-mcp run build
npm --prefix integrations/lolo-content-mcp run check
```

Do not run these commands from `C:\Users\Epicpp` or another directory. The repository must contain `integrations/lolo-content-mcp/package.json` and its committed `package-lock.json`.

### 2. Provide the environment variables

The connector reads exactly two values:

```text
LOLO_CONTENT_API_URL=https://carelolo.com/api/content/v1
LOLO_CONTENT_API_TOKEN=<the one-time bearer token>
```

Provide them through the approved user environment or secret manager before starting Codex. Do not store the token in the TOML configuration. Restart Codex after changing either value so the STDIO process receives the new environment.

### 3. Configure Codex

Codex supports local STDIO MCP servers in user-level `~/.codex/config.toml` or a trusted project's `.codex/config.toml`. The desktop app, CLI, and IDE extension share the configuration for the same Codex host. See the [official Codex MCP documentation](https://developers.openai.com/codex/mcp/).

Use the committed [configuration example](../../integrations/lolo-content-mcp/codex.config.example.toml), or add the following and adjust the absolute path:

```toml
[mcp_servers.lolo_content]
command = "node"
args = ["integrations/lolo-content-mcp/dist/index.js"]
cwd = "C:\\Users\\Epicpp\\homecare"
env_vars = ["LOLO_CONTENT_API_URL", "LOLO_CONTENT_API_TOKEN"]
default_tools_approval_mode = "writes"
startup_timeout_sec = 15
tool_timeout_sec = 90

[mcp_servers.lolo_content.tools.schedule_article]
approval_mode = "prompt"

[mcp_servers.lolo_content.tools.publish_article]
approval_mode = "prompt"
```

Do not change the last two values to `approve`; in Codex, that means pre-approved rather than “show an approval prompt.”

Restart Codex, then type:

```text
/mcp
```

The `lolo_content` server should be enabled and expose its tools. From the CLI, `codex mcp list` provides the equivalent check.

## Normal article workflow

### Step 1: Prepare the brief

Before asking Codex to write, decide:

- the reader and question the article should answer;
- the target location, if local;
- the desired conversion action;
- whether Julie is the correct public author;
- authoritative sources and their access dates;
- existing category and tags;
- suitable owned/licensed media;
- related LoLo Care articles and service pages for internal links.

Do not invent a new category or tag until Codex has listed existing options and confirmed that none fits.

### Step 2: Discover before drafting

Start with this prompt:

> Use the LoLo Care Content MCP. List existing articles to avoid duplication, then list authors, categories, tags, and managed media. Use the existing public author profile Julie. Recommend the best existing taxonomy and internal-link targets for an article about [topic] for [audience/location]. Do not create or publish anything yet.

This uses `list_articles` and `list_content_options` before any write.

### Step 3: Create the draft

After approving the plan:

> Create a draft using the approved outline. Use Julie as the public author, the selected existing category and tags, supported structured Markdown, verifiable sources, and useful contextual internal links. Write a truthful SEO title and meta description. Stop after returning the article ID and current `edit_version`. Do not publish.

Codex uses `create_article_draft`. Save the returned article ID in the task because all later operations refer to it.

### Step 4: Add or select media

For an existing library image, ask Codex to select its managed-media ID. For a new file:

> Inspect and optimize the supplied owned/licensed image, then upload it through `upload_article_media` with descriptive alt text, creator credit, ownership/license information, and a source URL when available. Show me the returned asset ID. Do not publish the article.

Images must be real JPG, PNG, WebP, or GIF files, no larger than 20 MiB, no more than 12,000 pixels per side, and no more than 25 megapixels. The CMS creates managed responsive WebP variants. Never reference a direct server file path in article content.

### Step 5: Preview and audit

> Fetch article [ID], obtain its protected preview, and run the readiness audit. Report every blocking issue and warning. Check the title, slug, Julie byline, category, tags, featured image and alt text, sources, FAQs, canonical URL, internal links, SEO title, and meta description. Do not publish.

The preview URL is private and short-lived. Do not put it in public messages or documentation.

The audit requires, among other things:

- a stable title and slug;
- an 80+ character excerpt and meaningful body content;
- an active public author with a substantive bio;
- a managed featured image with alt text and a recorded license;
- at least one category;
- an SEO title and meta description;
- verifiable sources for guides, research, and case studies;
- valid citations and managed embedded images;
- the seven editorial checklist confirmations.

Warnings should be reviewed rather than ignored. For example, SEO titles over 65 characters, meta descriptions over 165 characters, and high-trust claims may need revision even when they do not block publication.

### Step 6: Resolve conflicts safely

Every edit changes `edit_version`. If Codex reports a `409` conflict, use this prompt:

> Fetch article [ID] again, show me what changed, reconcile the intended update with the latest version, and retry using the new `edit_version` and a new idempotency key. Do not overwrite newer work blindly.

Never ask Codex to bypass optimistic locking.

### Step 7: Publish or schedule deliberately

Current production review policy is disabled, so a ready article does not need submission or a separate review approval. A human still owns the final factual and editorial decision.

For immediate publication:

> Fetch article [ID] again and run a final audit. Show the exact title, slug, public URL, author, readiness result, and intended internal links. If and only if it is ready, ask me for explicit approval before calling `publish_article` with the latest `edit_version`.

For scheduling:

> Fetch article [ID] again and run a final audit. Propose an exact ISO-8601 publication time with timezone, show the article details, and wait for my explicit approval before calling `schedule_article`. Do not publish immediately.

The token needs `content:publish` or `content:schedule`, and its actor needs the Publisher or Administrator role. The tool approval prompt is the final human decision point.

### Step 8: Verify the live result

After publication, ask Codex to verify:

- the public article returns HTTP 200;
- the canonical URL and index directives are correct;
- `BlogPosting`, breadcrumb, and applicable FAQ structured data parse correctly;
- the article appears on `/blog`, `/sitemap.xml`, and `/blog/feed.xml`;
- expected category, topic, author, related-article, and service-page links work;
- `robots.txt` and `llms.txt` still return HTTP 200;
- the CMS article state and immutable published revision match the intended version.

New publication does not guarantee immediate Google or Bing indexation. Submit the sitemap and inspect the URL in the search-engine webmaster tools when indexation matters.

## Tool reference

| Tool | Use |
| --- | --- |
| `list_articles` | Find existing content and current readiness |
| `get_article` | Read the article, sources, relationships, and latest `edit_version` |
| `list_content_options` | Find Julie, taxonomy, and managed media IDs |
| `create_article_draft` | Create a new working draft |
| `update_article` | Update content and metadata using optimistic locking |
| `upload_article_media` | Add validated managed media and responsive variants |
| `preview_article` | Obtain a protected preview URL |
| `audit_article` | Run the attributed readiness audit |
| `submit_article_for_review` | Use only when independent-review policy is enabled |
| `schedule_article` | High-impact write that schedules publication |
| `publish_article` | High-impact write that publishes immediately |

## Troubleshooting

| Symptom | What to do |
| --- | --- |
| `No user matched the actor identifier` | Create/activate the real application user and use its exact ID or email. A public author profile alone is not a token actor. |
| `npm ci` cannot find `package-lock.json` | `cd C:\Users\Epicpp\homecare` first; do not run from the Windows user directory. |
| `/mcp` does not show `lolo_content` | Rebuild the connector, verify the absolute `cwd`, confirm both environment variables exist, restart Codex, and inspect `codex mcp list`. |
| HTTP `401` | The token is missing, expired, malformed, or revoked. Issue/rotate it and restart Codex. |
| HTTP `403` | The token lacks the required ability or its actor lacks the corresponding Content role. Do not broaden permissions unless the operation is intended. |
| HTTP `409` | Refetch the article, reconcile changes, and retry with the newest `edit_version` and a new idempotency key. |
| HTTP `422` | Correct the returned field-level validation or readiness errors. Do not bypass them. |
| Media upload fails | Optimize the file and confirm its real type, size, dimensions, alt text, and license metadata. |
| Publish/schedule tool is denied | Use a short-lived publisher token bound to a Publisher/Administrator actor and retain explicit approval prompts. |
| “Complete an independent editorial review” appears | Verify `CONTENT_REQUIRE_INDEPENDENT_REVIEW=false` in the deployed environment and that Laravel configuration was rebuilt through the normal deployment. Enable review only intentionally. |
| Article is public but not in search results | Confirm HTTP 200, canonical/index directives, sitemap inclusion, and Search Console/Bing submission. New pages can take time to index. |

## Token rotation

1. Issue a replacement token with the minimum needed abilities.
2. Replace the workstation's `LOLO_CONTENT_API_TOKEN` without putting it in Git or TOML.
3. Restart Codex.
4. Run a read-only MCP operation such as `list_articles`.
5. Confirm the new token's last-use time with `php artisan content:token:list --active`.
6. Revoke the old token.

Revocation does not remove article attribution, revisions, published content, or audit history.
