# Risk Register

Status: Draft registry

Last updated: August 14, 2026

Owner: Security/privacy and product

| Risk ID | Scenario | Severity | Primary controls | Required evidence | State |
| --- | --- | --- | --- | --- | --- |
| `RISK-AUTH-001` | Agent exposes or changes another family's record | Critical | Server-derived context, policy recheck in every tool, ID-tampering tests | 100% critical authorization suite | Open |
| `RISK-ACT-001` | Material action occurs without understood confirmation | Critical | Risk classes, deterministic preview, bound confirmation | 100% Class D confirmation suite | Open |
| `RISK-ACT-002` | Retry creates duplicate care request or action | Critical | Idempotency key, transaction, timeout reconciliation | Repeated submit and timeout suite | Open |
| `RISK-TRUTH-001` | Agent invents policy, status, or success | Critical/high | Approved KB, authoritative tool receipts, hard-fail graders | Zero fabricated success; grounded-answer gates | Open |
| `RISK-KB-001` | Outdated or wrong-role KB entry is used | High | Applicability filtering, review dates, pause state, conflict checks | KB contract/retrieval tests | Open |
| `RISK-NAV-001` | Agent sends an older user to the wrong or inaccessible page | High | Semantic registry, route/policy checks, arrival acknowledgment | 100% registered navigation corpus | Open |
| `RISK-SAFE-001` | Emergency or medical request receives routine automation | Critical | Restricted domain rules, deterministic backstop, critical corpus, human escalation | 100% defined critical escalation corpus | Open |
| `RISK-HUMAN-001` | Human and agent reply or act simultaneously | High | Atomic transfer to human-only responder mode, in-flight reply suppression, and in-flight action rules | Concurrency and takeover E2E tests | Open |
| `RISK-PRIV-001` | Sensitive care/payment/account data enters unnecessary model context or analytics | Critical/high | Context allowlist, redaction, access/retention policy | Field inventory and privacy tests | Open |
| `RISK-INJECT-001` | User or record text changes agent policy/tool behavior | High | Untrusted-data boundary, capability tools, server validation | Adversarial prompt-injection corpus | Open |
| `RISK-UX-001` | Older user misunderstands the action or completion state | High | One decision at a time, explicit preview/button/receipt, usability research | Older-adult completion/comprehension gate | Open |
| `RISK-OPS-001` | Human transfer is not seen or receives a false queue/time promise | High | Both-admin in-app/email alerts, either-admin claim, persistent incident banner, human-only mode, no queue/time promise | Content-free alert receipt and human-handoff rehearsal | Open |
| `RISK-COST-001` | Long conversations or loops create excessive cost/latency | Medium/high | Context limits, one-call default, bounded retry, $0.03/$0.05 conversation gates, $2/$5 daily gates, 50-turn user ceiling, P95 monitoring | Versioned Luna price catalog and deterministic monitor tests pass; final local 56-case rehearsal cost $0.0182 and P95 5.468 seconds, so the approved latency warning remains open for exact-release/infrastructure remeasurement | Open |
| `RISK-MODEL-001` | Model/prompt change silently degrades behavior | High | Versioned candidate/prompt/schema/corpus/grader, repeated critical runs, compact report, full regression and staff-account/pilot gate | Frozen-v4 Luna/Mini report: 556/556 calls, zero hard/critical failures, exact hashes; repeat on governed changes and complete `DEC-064` evidence | Open |
| `RISK-EVENT-001` | Action succeeds but audit event is missing | Critical/high | Transactional/outbox event strategy, reconciliation alerts | Event-integrity suite | Open |
| `RISK-PILOT-001` | Unapproved live user sees or invokes unfinished AI behavior | Critical | Default-off controls, exact-user server grant, direct-endpoint denial, immediate revocation | 100% non-granted-user isolation and grant-enforcement suite | Open |
| `RISK-KB-002` | Unapproved draft, edit, or deletion changes customer-facing truth | Critical/high | Draft retrieval exclusion, RBAC, approval/version workflow, pause, tombstones, audit | 100% KB lifecycle and admin-authorization suite | Open |
| `RISK-PROVIDER-001` | Provider content is retained, trained on, or sent to a stateful destination beyond the approved contract | Critical/high | Dedicated restricted project, no training opt-in, `store: false`, HMAC safety identifier, no conversations/files/vector stores/background/hosted tools, 30-day maximum | Provider project, data-control, destination, and deletion evidence in Admin readiness | Open |
| `RISK-ALERT-001` | Automatic stop or human handoff alert fails silently | Critical/high | Forced in-app and email channels, delivery ledger inspection, persistent open incident banner, scheduled failure monitor | Both-admin content-free receipt confirmation and failure-path tests | Open |
| `RISK-READINESS-001` | Deployment or an evidence entry is mistaken for pilot authorization | Critical | Separate read-only readiness page, computed blockers, no activation action, guards/controls/grants independently off, explicit later release decision | Admin authorization/UI tests and read-only preflight evidence | Mitigated; release gate remains open |

## Risk acceptance

Critical risks cannot be accepted solely because occurrence is unlikely. Any residual critical risk requires written security/privacy, engineering, and product approval and a user-protective operational control.

Closing a risk means its specified controls and evidence are implemented for a declared scope. It does not remove the risk from ongoing monitoring.
