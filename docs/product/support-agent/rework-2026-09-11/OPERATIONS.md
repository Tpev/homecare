# Operational truth and preparation lifecycle

This local change adds no deployment or eligibility gate, schema migration, provider call, or domain write. Existing availability controls remain authoritative.

## Prepared form lifecycle

Opening a prepared action and consuming its fields recheck the actor, current ticket ownership/mode, active family membership, account scope, AI eligibility, and destination authorization. Consumption is single use: the form receives editable fields, while the session pointer and stored prepared content are cleared. A consumed preparation cannot refill a second form or become a committed domain action.

Human transfer, direct admin reply, control stop, a return to pilot-only access, and logout cancel the affected pending preparations. Expiry or loss of authorization is checked again at consumption. Cleanup changes only assistant preparation state and action cards; it never resets values already held by an editable Livewire form. Completed domain receipts remain preserved by the existing receipt exception.

## Incident and monitor semantics

An incident stores its containment observation separately from severity: current persisted control version, enabled value, and observation time. Alerts describe a confirmed stopped control only when that persisted state supports it. Incidents without that evidence say that no stop was confirmed. Resolving an incident never restores controls.

Admin Overview exposes open incidents and their resolution action, so an alert leads to actionable information. Its health queries are read-only. Successful provider-call P95 is labeled as a last-hour sample excluding failed calls and end-to-end delivery; insufficient samples and absent samples are explicit. Failed model turns and notifications queued at least ten minutes are shown separately. Delayed notifications create warnings when the scheduled monitor runs.

The health monitor records last attempt, last success, and status under the shared-cache key `ai_support.health_monitor`, retained for seven days. The admin page flags a missing successful heartbeat within ten minutes. This does not replace host-level scheduler supervision or delivery-provider monitoring.

## Current evidence identity

`AiSupportReadinessService::currentEvidenceIdentity()` returns the public model/prompt configuration, its hash, release commit when available, a support-source fingerprint, and published-knowledge identity. The source fingerprint includes AI services, Support and Family Livewire code, governed support resources, Support and Family Blade views, JavaScript, CSS, routes, pricing, and booking-payment dependencies. Knowledge identity includes currently retrievable stable IDs, version IDs/numbers, and content/source hashes; record timestamps and raw content are excluded from the returned identity.

Historical rehearsal and human-verification records display their recorded identity and age. A match requires source, public configuration, and published-knowledge hashes. Missing or mismatched identity is labeled historical or incomplete. None of this reinstates DEC-070/earlier readiness gates.

An optional local-disk report at `ai-support/evaluations/current-validation.json` can attach current deterministic regression evidence. Its contract is:

```json
{
  "kind": "deterministic_regression",
  "observed_at": "ISO-8601 observation time",
  "passed": true,
  "tests": 1,
  "assertions": 1,
  "model": "copy from currentEvidenceIdentity()",
  "prompt_version": "copy from currentEvidenceIdentity()",
  "source_hash": "copy from currentEvidenceIdentity()",
  "configuration_hash": "copy from currentEvidenceIdentity()",
  "knowledge_hash": "copy from currentEvidenceIdentity()"
}
```

The report must be under 100 KB and have a real observation date and positive test/assertion counts to claim a recorded pass. The UI labels it deterministic application regression, never model-quality evaluation. A source, configuration, or knowledge mismatch is explicitly historical. The file SHA-256 is shown for evidence traceability. No report or score is invented automatically.

Family coverage distinguishes declared Complete and verify, Assisted in the app, and Human support paths. It separately identifies mapped KB entries that are not presently retrievable and provides a filter and publication link. Catalog declarations are not displayed as customer success rates.

## Retention execution

The existing dry-run command now includes resolved incident evidence, readiness evidence, and inactive release decisions. Execution processes those classes in batches of 100, rechecks eligibility under row locks, respects active per-record holds and incident conversation holds, and writes content-free deletion evidence using the existing policy. Open incidents and unexpired active approved release decisions are protected. Readiness evidence expires at its declared `retain_until`, unless held.

Execution updates `ai_support.retention_execution` in the shared cache, including successful zero-work runs. Dry runs do not update the execution heartbeat. The cache record lasts seven days and the admin screen displays whether scheduled execution is enabled and when it last succeeded. Existing automatic-execution configuration is unchanged.

## Isolated verification

New tests cover truthful stopped/unstopped incident alerts; applied preparation invalidation on human handoff, direct admin reply, control stop and logout; revoked membership; expiry; single consumption; preservation of manual form edits; read-only health inspection; delayed delivery; historical evidence and knowledge/configuration drift; admin coverage filters; and retention dry-run, holds, active-record protection, deletion proof, and zero-work heartbeat.

The historical DEC-070 deferral test now freezes its clock inside its accepted August 15–29 window. Production expiry validation is unchanged. The parent `RESULTS.md` records the final consolidated suite and browser evidence.
