# Screening index retention

Daily source refreshes retain every imported version. DAC contains millions of rows, so historical searchable indexes need deliberate maintenance. The private ZIP archives, import metadata and screening evidence have separate retention requirements and are never removed by this command.

Preview first:

```sh
php artisan screening:prune-indexes
php artisan screening:prune-indexes nc_dac
```

To execute the same policy:

```sh
php artisan screening:prune-indexes nc_dac --execute
```

Only `screening_record_names` and `screening_records` belonging to eligible historical imports are deleted. Eligibility requires an inactive, validated, previously activated `ready` import. Active, importing, failed and unvalidated imports are protected. The newest previous validated ready version of each source is always retained. Other inactive versions must have been activated and last updated at least 48 hours ago. Import deactivation updates `updated_at`; later metadata changes conservatively restart that grace period.

The command uses the same shared per-source cache lock as the importer. Every deletion batch rechecks lock ownership, current import state and the protected previous version while holding database row locks. Names are removed before records, in transactions of at most 1,000 rows. An interrupted run can be repeated; it removes only the remaining eligible rows. Preview counts can change before execution, so execution always rechecks eligibility.

Searches use active versions and revalidate those versions before accepting a result. Decisions also require the currently active versions. Keeping the recent and immediately previous versions protects in-flight reads during normal activation. Historical candidate details remain available from `record_snapshot`; audit events retain identities, matched evidence, source versions and coverage. Import metadata, raw archives, cases, candidates, audit database rows and JSONL audit files are preserved unchanged, including after a partial cleanup. An old index can be rebuilt from its retained archive through a new validated import; this command does not reactivate old versions.

There is no automatic cleanup schedule. Review deployment storage, shared cache configuration, import runtime and database load before opting into execution or scheduling this command operationally. Bounded deletes make space reusable inside the database; they do not necessarily reduce the physical database file. Archive storage continues to grow and requires a separate backup and retention decision.
