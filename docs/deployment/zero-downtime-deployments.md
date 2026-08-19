# Atomic Production Deployments

Status: Current production deployment procedure

Owner: Engineering

Last updated: August 19, 2026

## Outcome

`deploy.sh` builds a complete release while the current application remains live, then switches `/var/www/homecare` to the prepared release atomically. It never runs `php artisan down`, so ordinary web and Livewire requests do not receive a deployment-long maintenance 503.

Nginx, the Laravel scheduler, queue configuration, and the voice-agent service may keep their existing `/var/www/homecare` paths. After the first deployment, that path is a stable symlink to the active release.

## Release layout

```text
/var/www/homecare -> /var/www/homecare-deploy/releases/<active-release>
/var/www/homecare-deploy/
  repository.git/       local deployment mirror
  releases/             prepared and retained application releases
  shared/.env           production environment file
  shared/storage -> ... durable uploads, logs, sessions, and framework state
  previous -> ...       immediately preceding release
```

The deployment keeps at least five recent releases. It never deletes the active release, the previous release, or a directory containing non-symlinked durable storage.

## First deployment

The server currently has an in-place Git checkout, so obtain the new deployment script once:

```bash
cd /var/www/homecare
git pull --ff-only origin master
./deploy.sh
cd /var/www/homecare
```

The first run:

1. copies `.env` to the protected shared directory;
2. establishes a stable shared-storage pointer without copying or recreating production data;
3. builds and validates the new release away from the live path;
4. uses Linux `RENAME_EXCHANGE` to atomically exchange the original directory and the prepared symlink;
5. keeps the original checkout as the previous release and durable-storage owner; and
6. gracefully reloads PHP-FPM before checking Laravel and voice-agent health.

The final `cd` is needed only because the shell that launched the first deployment still has the original directory as its working directory after the atomic exchange.

## Normal deployment

Every later deployment is one command:

```bash
cd /var/www/homecare
./deploy.sh
```

The script performs these operations in order:

1. acquires an exclusive deployment lock;
2. fetches the latest `master` commit into the local mirror;
3. creates a new inactive release;
4. links the protected `.env` and durable storage;
5. installs production Composer and npm dependencies;
6. builds frontend and voice-agent artifacts;
7. retains the previous hashed frontend assets for in-flight browser requests;
8. runs database migrations and photo generation while the old release remains live;
9. builds Laravel configuration, route, and view caches inside the inactive release;
10. validates Laravel routes, migration state, the Vite manifest, the voice binary, and Nginx configuration;
11. atomically changes the active symlink;
12. gracefully reloads PHP-FPM, signals queue workers after their current jobs, and restarts the voice agent;
13. checks `https://carelolo.com/up` through local Nginx and the local voice-agent health endpoint; and
14. removes only eligible old releases.

The script no longer calls the unavailable Horizon command unless Horizon is actually installed.

## Status and rollback

Read the active layout without changing it:

```bash
./deploy.sh --status
```

Return to the immediately preceding application release:

```bash
./deploy.sh --rollback
```

Rollback atomically changes the symlink, gracefully reloads PHP-FPM, restarts the runtime workers, and repeats both health checks. It intentionally does **not** reverse database migrations.

If a failure happens before activation, the live release is untouched and the failed release is retained for inspection. If a failure happens after activation, the script automatically points the application back to the prior release and reloads the runtime.

## Required migration rule

Every migration executed by this deployment must be compatible with both the old and new application release during the short overlap. Use expand-and-contract changes:

1. add new tables, columns, or indexes and deploy code that tolerates both schemas;
2. migrate or backfill data separately when needed; and
3. remove obsolete schema only in a later deployment after no active release uses it.

Do not combine a destructive rename/drop with code that requires the new schema in one deployment. A symlink rollback cannot undo a database migration.

## What users may notice

The website remains available and no maintenance page is enabled. A request already being processed finishes on its existing PHP-FPM worker; new requests use the new release after the graceful reload. Existing sessions and uploads remain in shared storage.

The voice agent is still restarted after the application switch, so an inbound voice request arriving at that exact moment may see a very short interruption. This does not make the website unavailable.

## Configuration

Defaults match the current production host. Operators may override them with scoped environment variables such as `HOMECARE_KEEP_RELEASES`, `HOMECARE_FPM_SERVICE`, or `HOMECARE_GO_BIN`. Do not repurpose system environment variables or put secrets in the script; production secrets remain only in the shared `.env`.
