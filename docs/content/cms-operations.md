# Content CMS operations

## Deploy

1. Run `php artisan migrate --force`.
2. Ensure `php artisan storage:link` has been run and the public disk is durable in production.
3. Run both a queue worker and `php artisan schedule:run` every minute. The scheduler publishes due articles, prunes analytics, and runs the weekly audit.
4. IndexNow uses a stable public key derived from the configured application host when `INDEXNOW_DERIVE_HOST_KEY=true` (the production default). Set `INDEXNOW_KEY` only when an explicit public-key override is needed; it is not a secret. Host-key derivation defaults off in the test environment to prevent accidental network requests.
5. `content:verify-public --fail-on-issues` runs daily at 09:20 in the application timezone, after the editorial publication slot. It verifies every live article's HTTP response, canonical, article schema, sitemap and `llms.txt` presence, and article-body internal links. Results are appended to `storage/logs/content-public-verification.log`.
5. Configure `CONTENT_ANALYTICS_RETENTION_DAYS` and `CONTENT_ANALYTICS_IDENTITY_RETENTION_DAYS` to match the approved privacy policy.
6. Build production assets with `npm ci && npm run build`.
7. Run `php artisan content:audit --fail-on-issues` as a release gate.

## Roles and workflow

An administrator assigns Author, Editor, or Publisher from Content -> Authors & Taxonomy. Authors create and revise. Editors can independently review when that deployment policy is enabled. Publishers schedule, publish, archive, merge taxonomy, and maintain public author profiles. Only administrators can change content-team permissions.

Independent review is controlled by `CONTENT_REQUIRE_INDEPENDENT_REVIEW` and currently defaults to `false`. When enabled, the CMS requires submission before review, prevents the author, last editor, or submitter from approving it, and requires review notes and reviewer credentials. With the policy disabled, an authorized publisher may publish a ready draft directly. In both modes, the publish gate is checked again when a scheduled article becomes due, a stale browser tab cannot overwrite a newer edit, and autosave does not create permanent revision spam.

Draft changes never replace an immutable public revision. Republishing creates the public revision, updates the truthful modification date, creates redirects for changed slugs, clears public caches, and queues IndexNow submission.

## Legacy migration

Use `content:import-docx` only for article directories. New imports are quarantined as noindex drafts. A forced re-import of an existing public article updates only its working draft and preserves its live revision, public slug, and indexability until it is republished and, when the deployment policy requires it, independently reviewed.

## Analytics and privacy

Article events use a random first-party visitor token, not an IP address or raw user-agent fingerprint. Events are deduplicated daily; obvious bots are excluded. Direct user attribution is removed after the identity-retention period, and events are deleted after the general retention period by `content:prune-events`.

The dashboard reports daily visitors, engaged reads, CTA activity, attributed registrations, and AI referrals from recognized referrers or UTM sources. Treat these as directional first-party analytics, not a replacement for consent-aware product analytics.

## Search and AI visibility

- Verify the domain in Google Search Console and Bing Webmaster Tools, then submit `/sitemap.xml`.
- Keep `/robots.txt`, `/llms.txt`, `/sitemap.xml`, and `/blog/feed.xml` reachable without authentication or bot challenges.
- If a CDN or WAF is used, explicitly allow verified search crawlers needed by the business, including OpenAI's search crawler, rather than trusting user-agent text alone.
- Keep author identity references, citations, canonical URLs, structured data, article dates, and any reviewer credentials shown when review mode is enabled accurate. Do not create special AI-only page content.
- Use the optional `php artisan content:audit --check-links` check periodically; it performs outbound requests and is intentionally not part of the minute-by-minute scheduler.

## Routine checks

- `php artisan content:audit --fail-on-issues`
- Review Content dashboard 30-day views, completed reads, CTA clicks, attributed signups, and AI referrals.
- Review overdue articles and source access dates monthly.
- Confirm `/sitemap.xml`, `/blog/feed.xml`, `/robots.txt`, `/llms.txt`, canonical tags, and structured data after changes to delivery templates.
- Investigate any failed `content:publish-scheduled` run; validation failures return the affected article to draft while later due articles continue processing.
