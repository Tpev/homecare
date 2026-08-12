# LoLo Care Content MCP

A local STDIO MCP server that gives Codex scoped access to the versioned LoLo Care Content API. It never reads Laravel's database or storage directly and has no review/approval tool.

## Requirements and build

- Node.js 20 or newer
- A Content API actor token with only the scopes needed for the intended workflow
- An HTTPS Content API URL (plain HTTP is accepted only for `localhost`, `127.0.0.1`, or `::1` development)

```sh
cd integrations/lolo-content-mcp
npm ci
npm run build
```

For the first dependency install by a maintainer, use `npm install` and commit the generated `package-lock.json`. Start the built server with `npm start`.

The process reads exactly these connector settings from its environment:

- `LOLO_CONTENT_API_URL`, such as `https://care.example.com` (a URL already ending in `/api/content/v1` is also accepted)
- `LOLO_CONTENT_API_TOKEN`, the bearer credential issued by the Laravel application

Do not place the token in a committed file, command argument, prompt, log, or source code.

## Codex configuration

Copy the relevant contents of `codex.config.example.toml` into the trusted project's `.codex/config.toml`. Set the two environment variables outside Codex, build this package, and restart Codex. The example forwards variable names rather than embedding their values and requires approval for writes, with explicit approval for schedule and publish.

Official Codex MCP configuration supports project-scoped `.codex/config.toml`, STDIO commands, forwarded `env_vars`, server instructions, and per-tool approval modes.

## Tools and workflow

The server exposes exactly:

- `list_articles`, `get_article`, `list_content_options`
- `create_article_draft`, `update_article`, `upload_article_media`
- `preview_article`, `audit_article`, `submit_article_for_review`
- `schedule_article`, `publish_article`

Get the latest article immediately before changing it and supply its `edit_version`. Every mutation sends an `Idempotency-Key`; provide an `idempotency_key` UUID and reuse it when retrying an uncertain request. The returned structured result includes the key actually used. A conflict response tells the caller to refetch and merge rather than overwrite.

Run `audit_article`, fix readiness findings, and then use `submit_article_for_review`. A different authorized human must review the work in the CMS. This connector deliberately cannot review or approve an article. `schedule_article` and `publish_article` are destructive/high-impact writes and require explicit user approval.

## Conservative Markdown

`create_article_draft` and `update_article` accept either `content_json` or `markdown`, never both. Conversion is deterministic and supports:

- h2-h4 headings, paragraphs, horizontal rules, flat ordered/unordered lists, and blockquotes
- bold (`**text**`), italic (`*text*`), underline (`__text__`), strike (`~~text~~`), and inline safe links
- GitHub-style callouts using `> [!NOTE]`, `TIP`, `WARNING`, or `IMPORTANT`, followed by quoted body lines
- GFM pipe tables with a header separator and at least one body row
- stable citations: `{{cite:123e4567-e89b-42d3-a456-426614174000}}` or `{{cite:UUID|label}}`
- a CTA on its own line: `:::cta label="Find care with LoLo" url="/register" variant="primary"`
- an FAQ section headed `## Frequently asked questions` (or `## FAQ`), with each plain-text question as h3 and its answer below

When creating a draft, every citation UUID in Markdown must appear in the request's `sources`. Preserve those UUIDs on later source edits and reordering. The converter rejects h1/h5/h6, raw HTML, code, footnotes, reference links, Markdown images, task/nested lists, unsafe URLs, malformed directives, and unknown structures instead of silently dropping them. Upload JPG, PNG, WebP, or GIF images through `upload_article_media`, including rights/source metadata when applicable, then use the returned managed asset in structured content. Images may be at most 20 MiB, 12,000 pixels per side, and 25 megapixels.

## Development checks

```sh
npm run check
npm run build
npm audit
```

Tests use mocked fetch implementations and do not contact a real Content API.
