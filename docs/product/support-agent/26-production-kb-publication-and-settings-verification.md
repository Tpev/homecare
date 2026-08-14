# Production KB Publication and Settings Verification

Status: Complete; limited release remains disabled

Evidence date: August 14, 2026

Owner: Product and engineering

Operator scope: Approved production deployment verification and publication of reviewed non-pricing knowledge only

## Outcome

The Shadow operator-control cleanup is deployed and verified. Admin Settings contains no `shadow_enabled` state row or selectable option. The runtime and provider deployment guards remain off, all customer AI controls remain off, human-only remains on, and there are zero exact-user pilot grants.

Twenty-three approved non-pricing Version 1 KB entries were reviewed and published through the governed Admin lifecycle. `KB-CARE-006`, **Approved hourly price - publication held**, remains a validated Draft. Publishing knowledge did not make retrieval available to any customer because every independent runtime, provider, visibility, role, capability, human-only, and exact-user gate remains fail-closed.

## Published inventory

| Package | Published stable IDs | Count |
| --- | --- | ---: |
| Shared support | `KB-SUP-001` through `KB-SUP-003` | 3 |
| Family orientation | `KB-FAM-001` through `KB-FAM-005` | 5 |
| Caregiver orientation | `KB-CGV-001` through `KB-CGV-004` | 4 |
| Interactive care support | `KB-CARE-001` through `KB-CARE-005`, and `KB-CARE-007` through `KB-CARE-012` | 11 |
| **Total published** |  | **23** |

Held inventory:

| Stable ID | State | Reason |
| --- | --- | --- |
| `KB-CARE-006` | Validated Version 1 Draft | Pricing publication remains held until the separate pricing/payment implementation reconciliation releases `DEC-049` |

## Review and lifecycle evidence

Before each publication, the authenticated Admin workflow verified:

- **Validation passed**;
- non-empty title and approved answer/procedure;
- at least one authoritative source;
- exactly five linked evaluation IDs;
- the stable ID was not `KB-CARE-006`; and
- the title did not contain the pricing publication hold.

Each entry then followed the same governed UI sequence:

1. Draft to **In Review** through **Submit for review**.
2. In Review to **Approved** through **Self-review and approve**, permitted for either of LoLo's two operators under `DEC-022`.
3. Approved to **Published** through the reasoned, typed-`PUBLISH` lifecycle action.

The content-free operational reason was:

> Approved non-pricing pilot knowledge under DEC-034 through DEC-066

The activity audit across all pages contains exactly 23 matching successful `published_now` events, zero matching failed events, and no other successful Knowledge Published event in the inspected production history.

## Final production audit

| Invariant | Verified state | Result |
| --- | --- | --- |
| Governed KB total | 24 working | Pass |
| Published KB | 23 | Pass |
| Draft KB | 1: `KB-CARE-006` | Pass |
| Paused / overdue | 0 / 0 | Pass |
| Per-entry validation, sources, evaluations | All 24 passed; exactly five evaluations each | Pass |
| Unexpected non-pricing lifecycle state | None | Pass |
| Runtime deployment guard | Off | Pass |
| Provider deployment guard | Off | Pass |
| Master / user-visible controls | Off / off | Pass |
| Role and capability controls | All off | Pass |
| Human-only | On; the only control showing On | Pass |
| Exact-user grants | 0 | Pass |
| Shadow operator control | Absent from state list and selector | Pass |
| No-shadow policy | Still shown on overview; server denial retained | Pass |
| Browser health | Zero current console errors or warnings | Pass |

## Mutations deliberately not performed

- No pilot grant was created or scheduled.
- No deployment, master, visibility, role, capability, commit, or tool control was enabled.
- Human-only was not disabled.
- `KB-CARE-006` was not submitted, approved, or published.
- No support message, model call, AI conversation, draft request, recap, Care Request, booking, hire, or payment record was created.

## Release position

This completes governed KB publication readiness, not limited-release authorization. The next work is provider privacy/retention evidence, production-like staff-account safety and rollback rehearsal, monitoring ownership, the five-person older-adult usability gate, and an explicit release decision for the first two exact Family users.
