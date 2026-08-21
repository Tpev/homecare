# Hosted LoLo Content MCP: administrator and Charles setup

Use this guide to let Charles create, edit, audit, schedule, and publish LoLo Care articles from Codex on his Mac without installing Node, cloning the repository, or receiving a Content API bearer token.

Production is `https://carelolo.com`. The MCP endpoint is `https://carelolo.com/mcp/content`. Codex opens the normal LoLo Care login page and uses OAuth; every CMS action is attributed to the LoLo Care user who approved that connection.

This is a one-time administrator setup followed by a short per-user setup. Run server commands yourself in an existing production terminal. Codex must never open an SSH connection or run deployment commands.

## What is being installed

```text
Charles's Codex on macOS
  -> HTTPS /mcp/content with short-lived OAuth token
     -> Nginx
        -> Node MCP on 127.0.0.1:8090
           -> OAuth introspection in Laravel
           -> signed actor delegation + separate server-only API token
              -> /api/content/v1
                 -> existing authorization, readiness, CMS workflow, and audit
```

Security boundaries:

- OAuth authorization code flow requires PKCE `S256`. Codes live five minutes and work once.
- Access tokens live 15 minutes; rotating refresh tokens live 30 days. Codes, access tokens, refresh tokens, and the service credential are random and hashed at rest.
- Dynamic clients accept only explicit `http://127.0.0.1:<port>/callback...`, `localhost`, or IPv6 loopback callbacks. Arbitrary web redirect URIs are rejected.
- The incoming OAuth token is audience-bound to `https://carelolo.com/mcp/content` and is never sent to the Content API.
- The server-only Content API credential cannot call an API endpoint without a valid signed delegation. The API resolves Charles as the actor and limits the request to the intersection of his OAuth scopes, current content role, and service-token abilities.
- The Node service binds loopback only. Nginx exposes one exact MCP location with a 30 MiB request limit; Laravel remains responsible for the 20 MiB decoded media limit and media safety.
- If the optional MCP service fails during a deployment, `deploy.sh` reports it but leaves the healthy live Laravel/booking/payment/voice release active.

## A. Prepare Charles's LoLo Care account

1. Create or locate Charles's normal LoLo Care user in the admin UI.
2. Verify his email and ensure he can log in at `https://carelolo.com/login`.
3. In Admin → Users, set his content role:
   - `author` for draft/edit/media/audit/submit only;
   - `publisher` if he must also schedule and publish.
4. Do not share an administrator password or the server-only token with Charles.

The public byline remains the selected CMS author profile (for example Julie). The logged-in OAuth user is the accountable editor/publisher in the audit trail; these are intentionally separate concepts.

## B. Deploy the application release first

Before the first deployment, add these non-secret values to `/var/www/homecare-deploy/shared/.env` (or verify that `APP_URL` and the defaults already produce the same values):

```dotenv
APP_URL=https://carelolo.com
CONTENT_MCP_PUBLIC_URL=https://carelolo.com/mcp/content
CONTENT_MCP_SERVICE_PORT=8090
```

From the existing production terminal:

```bash
cd /var/www/homecare
./deploy.sh
```

The inactive release build now runs the connector's locked `npm ci` and TypeScript production build. On the first run it logs that the optional systemd unit is not installed; that is expected. The application migration adds only new OAuth tables and one default-false Content API token column, so the previous release remains compatible during the atomic switch.

Verify the Laravel side before installing the service:

```bash
cd /var/www/homecare
php artisan route:list --path=oauth
php artisan route:list --path=.well-known
php artisan schedule:list | grep content-mcp
curl -fsS https://carelolo.com/.well-known/oauth-authorization-server
curl -fsS https://carelolo.com/.well-known/oauth-protected-resource/mcp/content
```

## C. Issue the server-only delegation credential

Use an existing administrator as both actor and attributed issuer. The credential is never Charles's token and must include `--hosted-mcp-service`. The maximum one-year TTL is appropriate only because this credential stays on the server and OAuth still limits each human session; rotate it sooner after any concern.

```bash
cd /var/www/homecare
php artisan content:token:issue test@test.com "Hosted Content MCP resource service" \
  --ability=content:read \
  --ability=content:draft \
  --ability=content:media \
  --ability=content:audit \
  --ability=content:submit \
  --ability=content:schedule \
  --ability=content:publish \
  --ttl=525600 \
  --issued-by=test@test.com \
  --hosted-mcp-service
```

The secret is displayed once. Do not paste it into chat, a prompt, Git, shell history, or the shared Laravel `.env`.

Create an isolated root-owned service environment file, then edit it interactively so the token is not a command-line argument:

```bash
sudo install -m 640 -o root -g www-data /dev/null /var/www/homecare-deploy/shared/content-mcp.env
sudo nano /var/www/homecare-deploy/shared/content-mcp.env
```

Enter exactly these five settings, replacing only the token value:

```dotenv
LOLO_CONTENT_API_URL=https://carelolo.com/api/content/v1
LOLO_CONTENT_API_TOKEN=<paste the one-time hosted service token>
LOLO_CONTENT_MCP_PUBLIC_URL=https://carelolo.com/mcp/content
LOLO_CONTENT_MCP_OAUTH_ISSUER=https://carelolo.com
LOLO_CONTENT_MCP_PORT=8090
```

Save, close, and verify permissions without printing the file:

```bash
sudo chown root:www-data /var/www/homecare-deploy/shared/content-mcp.env
sudo chmod 640 /var/www/homecare-deploy/shared/content-mcp.env
sudo stat -c '%U %G %a %n' /var/www/homecare-deploy/shared/content-mcp.env
```

Expected owner/mode: `root www-data 640`.

## D. Install the additive systemd service

```bash
cd /var/www/homecare
sudo install -m 644 docs/deployment/homecare-content-mcp.service /etc/systemd/system/homecare-content-mcp.service
sudo systemctl daemon-reload
sudo systemctl enable --now homecare-content-mcp
sudo systemctl status homecare-content-mcp --no-pager
curl -fsS http://127.0.0.1:8090/healthz
```

The health response should identify `lolo-content-mcp`. The unit runs as `ubuntu:www-data`, has no write access requirement, binds only loopback, restarts on failure, and uses systemd hardening. Future `./deploy.sh` runs rebuild and restart it after the atomic release switch; MCP restart/health failure cannot roll back an otherwise healthy live site.

If Node is not `/usr/bin/node`, find it with `command -v node`, edit only `ExecStart=` in the installed unit, then run `sudo systemctl daemon-reload` and restart the service.

## E. Add the exact Nginx location

In the existing `carelolo.com` HTTPS server block, add the `/mcp/content` location from `docs/deployment/nginx-carelolo.conf` before the generic `location /` block. Do not replace an active production Nginx file wholesale.

```nginx
location = /mcp/content {
    client_max_body_size 30M;
    proxy_pass http://127.0.0.1:8090;
    proxy_http_version 1.1;
    proxy_set_header Connection "";
    proxy_set_header Host $host;
    proxy_set_header Origin $http_origin;
    proxy_set_header X-Forwarded-Proto https;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_buffering off;
    proxy_cache off;
    proxy_read_timeout 120;
    proxy_send_timeout 120;
}
```

Validate before reloading:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

Then verify the public boundary:

```bash
curl -i -X POST https://carelolo.com/mcp/content \
  -H 'Content-Type: application/json' \
  --data '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}'
```

Expected: `401 Unauthorized` with a `WWW-Authenticate: Bearer` header pointing at `/.well-known/oauth-protected-resource/mcp/content`. A 502 means the loopback service is not running; a 404 means the Nginx location was not added to the active server block. Neither condition requires changing Laravel, payments, or booking configuration.

## F. Charles's Mac setup (the short part)

Charles only needs Codex and his LoLo Care login:

1. Install/open Codex on the Mac and sign into his OpenAI account.
2. Open Codex Settings → MCP servers → Add server.
3. Name it `lolo_content` and enter `https://carelolo.com/mcp/content` as a Streamable HTTP server URL.
4. Choose OAuth/Authenticate. Codex opens a browser.
5. Log into LoLo Care as Charles, review the exact scopes, and click **Allow Codex**.
6. Return to Codex and restart it once if the tool list does not refresh immediately.
7. In `/mcp`, confirm that the server is connected and shows 11 tools.

No Node installation, repository checkout, terminal environment variable, API token, or personal config file is required.

If he uses the Codex config file instead of the Settings UI, this is the complete server entry:

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

Never change schedule/publish to `approval_mode = "approve"`; in Codex that means pre-approved, not “ask me for approval.”

## G. First safe verification

Start read-only:

> Use LoLo Content MCP to list content options and the five most recent articles. Do not create or change anything. Tell me which LoLo Care user is accountable for this session if the tool reports it.

Then create a draft, without publishing:

> Create a short test draft titled “Hosted MCP connection test,” assign a real author/category only after listing options, run its audit, and stop. Do not submit, schedule, or publish.

Verify on the server:

```bash
php artisan content-mcp:session:list --active --actor=<charles-email>
php artisan content:token:list --active
```

The OAuth session list identifies Charles, the Codex client, scopes, expiry, and last use without revealing credentials. The Content API token list shows the separate `hosted MCP service` credential. In the CMS/content audit event, the actor is Charles; metadata contains a non-secret OAuth session UUID and the token relation identifies the hosted service.

Delete/archive the connection-test draft in the CMS if it is not useful. Do not publish it merely to test the connector.

## Rotation, revocation, and incident response

Immediately stop Charles's hosted access while preserving all article/audit history:

```bash
php artisan content-mcp:session:revoke <charles-email> --revoked-by=test@test.com
```

Removing his `content_role` in Admin → Users also makes all current OAuth access ineligible. Restoring the role does not resurrect revoked sessions; he authenticates again.

To rotate the server-only credential:

1. Issue a new `--hosted-mcp-service` token with the same or narrower abilities.
2. Interactively replace only `LOLO_CONTENT_API_TOKEN` in `/var/www/homecare-deploy/shared/content-mcp.env`.
3. Run `sudo systemctl restart homecare-content-mcp` and verify loopback/public health.
4. Revoke the old Content API token by its numeric ID with `content:token:revoke`.

For suspected compromise, revoke the affected user's OAuth sessions, revoke/rotate the hosted service token if it may be exposed, stop the MCP service if necessary, and inspect `content_api_audit_events` by actor/request ID before restoring access. Stopping `homecare-content-mcp` affects only Codex publishing; it does not stop the website, CMS UI, scheduler, bookings, messages, Stripe, or voice agent.

Rollback uses the normal `./deploy.sh --rollback`. Database migrations are additive and are intentionally not reversed. The previous application ignores the new tables/column safely. The systemd service follows the `/var/www/homecare` release symlink and restarts against whichever release is active.
