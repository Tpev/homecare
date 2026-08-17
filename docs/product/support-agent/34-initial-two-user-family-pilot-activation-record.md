# Initial Two-User Family Pilot Activation Record

Status: Limited release active; activation and initial smoke evidence complete

> Historical activation record: `DEC-072` superseded the exact-commit and expansion gates. The two named users remain the Pilot-only cohort, but this record no longer authorizes or blocks current operation.

Activated: August 17, 2026

Decision owner: Product

Operating owners: Full Administrators `1` and `18` under the approved either-Administrator model

## Purpose

This record captures the ordered production activation and first content-free user-facing verification of the exact two-user Family pilot authorized by `DEC-070` and `DEC-071`. It does not mark any deferred-before-expansion evidence as Passed and does not authorize a third user or another capability.

## Immutable release anchors

| Anchor | Recorded value |
| --- | --- |
| Deployed and approved commit | `0dcb433d1ecb574e14804a37f5d5bc2da1d1b901` |
| Explicit release-decision reference | `334025a1-78f2-4290-9d72-df7f3ef5268f` |
| Release snapshot SHA-256 | `12a9f397edf6cdb320cf2d5417538c9f1a430f6eb263fed4d90f1ff6effd14c0` |
| Initial-pilot preflight at approval | `READY`; 17 Passed, 5 Deferred before expansion, 0 Blocking |
| Release-decision state after guard deployment and activation | `APPROVED · EFFECTIVE` |
| Decision expiry | August 29, 2026 11:59 PM |

The readiness page reported zero open incidents and zero open warnings after activation. Expansion readiness remained Blocked, as required.

## Exact cohort and grants

| User | Production ID | Role | Bundle | Grant reference | Window |
| --- | ---: | --- | --- | --- | --- |
| Thibaud Peverelli | `19` | Family | `family_support_v1` | `a839bec2-0a79-446f-87fe-ae4626ff8faa` | Aug 17, 2026 8:52 AM through Aug 29, 2026 8:52 AM |
| Charles Petrini | `282` | Family | `family_support_v1` | `8fd585e6-6b5f-491d-981f-c37e4f6ea7c5` | Aug 17, 2026 8:52 AM through Aug 29, 2026 8:52 AM |

The production current-grants view contained exactly these two records and no scheduled grant.

## Ordered activation evidence

The activation preserved a fail-closed boundary at every intermediate step:

1. A fresh initial-pilot preflight Passed on the exact deployed commit while both deployment guards were off, Human Only was on, and no grant existed.
2. Product recorded the explicit release decision shown above. That action created no grant and changed no control.
3. Exact expiring grants were created for IDs `19` and `282` only. Both remained ineligible because the runtime deployment guard was off.
4. The following stored controls were turned on while Human Only and both deployment guards still blocked exposure:
   - `master_enabled`;
   - `user_visible_enabled`;
   - `role.family`;
   - `capability.support_answers_v1`;
   - `capability.semantic_navigation_v1`;
   - `capability.family_context_v1`;
   - `capability.care_intake_v1`;
   - `capability.care_request_draft_v1`;
   - `capability.care_request_recap_v1`;
   - `capability.care_request_publish_v1`;
   - `capability.care_24h_handoff_v1`;
   - `commit.one_time`;
   - `tool.care-request.publish.one-time`.
5. `role.caregiver`, `commit.recurring`, and `tool.care-request.publish.recurring` remained off.
6. Production set `AI_SUPPORT_RUNTIME_AVAILABLE=true` and `AI_SUPPORT_PROVIDER_ENABLED=true` through the normal `deploy.sh` path without changing the approved commit.
7. Before exposure, `ai-support:monitor-health` reported zero conversation and tool samples, zero failed operations notifications, `$0.000000` daily model cost, and no daily cost stop.
8. With both guards on, both exact grants were still blocked only by Human Only. The release decision remained effective and the cohort remained exactly two.
9. Human Only was turned off last using the content-free reason `Start approved DEC-070 exact two-user Family pilot after green deployment and health audit.`

### Corrective exact-commit reactivation on August 17

The receipt-preservation safety finding discovered after the first activation was contained by turning Human Only on, revoking both grants, and closing every exposure-opening stored control before deploying a correction. Exact commit `0dcb433d1ecb574e14804a37f5d5bc2da1d1b901` then passed the complete `14 / 14` staffed synthetic safety record `SR-20260817-0DCB433D`, including preservation of completed receipts during takeover and rollback.

Production deployed that exact commit with both deployment guards off. Admin recorded the corrected rehearsal as `Passed`, and the fresh read-only preflight returned `17` Passed, `5` Deferred before expansion, and `0` Blocking. The separate release decision has reference `334025a1-78f2-4290-9d72-df7f3ef5268f` and snapshot SHA-256 `12a9f397edf6cdb320cf2d5417538c9f1a430f6eb263fed4d90f1ff6effd14c0`.

Reactivation repeated the complete ordered sequence: exact expiring grants for users `19` and `282`; only the thirteen approved Family and one-time stored controls; both deployment guards through `deploy.sh`; a green health result with zero failed operations notifications and no daily cost stop; exact-decision, incident, warning, and cohort checks; and Human Only off last. Post-reactivation Admin evidence showed `Pilot controls open`, exactly two active grants, users `19` and `282` Eligible, non-pilot Family user `392` denied for `No Active Exact User Grant`, Caregiver user `399` denied for `Role Not Released`, and zero open incidents or warnings.

## Post-activation boundary verification

The production Admin UI then showed:

| Subject | Observed result |
| --- | --- |
| Customer AI state | `Pilot controls open`; master on, user-visible on, Human Only off |
| Active exact-user grants | `2` |
| Family user `19` | `Eligible` |
| Family user `282` | `Eligible` |
| Non-pilot Family user `392` | `NOT ENABLED`; `No Active Exact User Grant`; grant history `0` |
| Caregiver user `399` | `NOT ENABLED`; `Role Not Released`; grant history `0` |
| Release decision | `APPROVED · EFFECTIVE` on commit `0dcb433d...` |
| Incidents and warnings | `0` and `0` |

This verifies the exact-user and role boundaries after the final control change. It does not replace daily monitoring or review of every pilot interaction.

## Content-free live smoke test

An Administrator used the production **Login as** control for exact pilot Family user `19`. This action replaces the browser session; it is not a reversible impersonation banner. No credentials were exposed.

The Family dashboard displayed the existing **Chat with LoLo Support** launcher. Before the first message, the UI preserved the canonical human channel, Support Center link, and emergency warning. The test sent only:

> Pilot smoke test: What can you help me with?

Observed result:

- canonical support ticket: `#28`;
- ticket state: `OPEN`, category `GENERAL`, priority `NORMAL`;
- assistant disclosure: `AI assistant - You can ask for a person anytime`;
- human control: visible **Talk to a person** button;
- assistant label: `LoLo Support assistant · Support`;
- response: the assistant stated that it can explain LoLo, navigate to the Family dashboard or Care page, explain Family access, and help start a non-medical care request;
- emergency boundary remained visible: LoLo Support is not an emergency service and immediate danger routes to 911;
- response timestamp shown by the application: August 17, 2026 9:07 AM;
- browser transport: the relevant Livewire requests returned HTTP 200;
- browser console: zero errors and zero warnings.

The smoke test supplied no care details, requested no side effect, displayed no recap or confirmation, and produced no care-request publication receipt. The existing human-support conversation and Support Center remained available throughout.

## Post-conversation Administrator audit

A fresh `test@test.com` Administrator session inspected `/admin/support/tickets/28` after the user-facing response.

| Evidence | Observed value |
| --- | --- |
| Responder mode | `automated` |
| Context contract | `support-context-v1` |
| Event contract | `support-event-v1` |
| Retention policy | `ai-support-retention-v1` |
| Model configuration | `luna-low-v2` |
| Model event | `model turn completed` |
| Result and capability | `answer` · `support_answers_v1` |
| KB versions | `2`, `7`, `5`, `4` |
| Latency | `5098 ms` |
| Tokens | `2423` input · `294` output |
| Recorded cost | `$0.0008` |
| Delivery event | `answer delivered` · `delivered` · `support_answers_v1` |
| Active / expired recaps | `0` / `0` |
| Receipts | `0` |
| Private request draft | Absent |
| Confirmed-action receipt | Absent |
| Related care request or booking | Absent; zero Admin request links |

After this real model turn, the readiness page still showed the explicit release decision as `APPROVED · EFFECTIVE`, zero open incidents, and zero open warnings. The overview still showed `Pilot controls open`, exactly two active exact-user grants, Human Only off, 23 published KB entries, zero paused entries, and zero overdue entries. Human support remained identified as primary.

The single `$0.0008` response is below the `$0.03` conversation warning and `$0.05` conversation stop. `ai-support:monitor-health` computes conversation/tool P95 over the trailing hour, opens a latency warning only after at least 20 observations, counts failed AI/handoff notification deliveries from the trailing hour, and sums cost from the current UTC day. Therefore the one `5098 ms` response remains observable but cannot by itself open the P95 warning.

A fresh post-smoke monitor run recorded:

| Monitor metric | Value |
| --- | --- |
| Conversation sample / P95 | `1 / 5098 ms` |
| Tool sample / P95 | `0 / 0 ms` |
| Failed operations notifications | `0` |
| Daily model cost | `$0.000838` |
| Daily cost stop | `Not reached` |

Production scheduler wiring was then verified directly on August 17, 2026. Laravel listed `ai-support:monitor-health` on a five-minute schedule, `systemctl is-active cron` returned `active`, and the root crontab contained `* * * * * cd /var/www/homecare && php artisan schedule:run >> /dev/null 2>&1`. This proves that the production scheduler invokes Laravel every minute and can dispatch the five-minute health monitor. A content-free `Cost and performance monitoring` evidence version containing this result and the post-smoke metrics was recorded as `Passed` in Admin Release readiness. The evidence write explicitly changed no runtime control or pilot grant.

This closes the initial activation/smoke evidence batch. Both operating owners must continue monitoring the pilot and reviewing interactions under the approved either-Administrator ownership model.

## Live reversible takeover-path drill

The existing content-free ticket `#28` was then used to exercise the production Admin takeover and deliberate-return path without creating a second conversation.

1. The Administrator sent the clearly labeled public rehearsal message: `Pilot safety rehearsal: a LoLo team member has taken over this conversation. No care action was requested or taken.`
2. The ticket changed from responder mode `automated` to `human only`, status `In progress`, and conversation owner `Thibaud Peverelli - Admin`.
3. AI evidence recorded `transferred to human` with result `human_only` at August 17, 2026 9:18 AM.
4. Active recaps, expired recaps, and receipts remained zero; no draft, request, booking, or care action existed.
5. The Administrator used **Return deliberately** with the content-free reason `Complete the content-free pilot takeover rehearsal; no care data or user action is pending.`
6. The ticket returned to responder mode `automated`; the user-facing message stated that the LoLo Support assistant was available again and that the user could ask for a person at any time.
7. AI evidence recorded `returned to automation` with result `automated` at August 17, 2026 9:18 AM.
8. The release decision remained effective and readiness still showed zero open incidents and zero open warnings.
9. The completed content-free ticket was marked `RESOLVED` at August 17, 2026 9:20 AM while responder mode remained `automated`; a later user reply may reopen it through the ordinary support flow.

This proves the live Admin reply takeover, automated-reply suppression state, human ownership, and deliberate return path. It does **not** complete the full 14-observation synthetic safety record because this ticket had no active recap or confirmation to invalidate and did not exercise emergency, 24/7, automatic-stop, both-Administrator alert, or preserved-record cases. The `human_takeover_and_rollback` readiness item therefore remains `Deferred before expansion` until the approved synthetic runbook and validator pass in full.

The production readiness register received a new content-free `Deferred before expansion` version on August 17 with source `Production Admin ticket 28 AI evidence; DEC-070; observed 2026-08-17`. The UI confirmed that recording this evidence changed no runtime control or pilot grant.

## Expansion remains prohibited

The following six `DEC-070` obligations remain Deferred before expansion:

1. provider no-training and effective-retention account evidence;
2. provider destination/contract reference;
3. downstream extinction inventory and restore/re-deletion rehearsal;
4. witnessed staffed human-takeover and rollback drill;
5. five-person non-team older-adult usability study, correction, and any required retest;
6. a real screen-reader session.

Until those obligations Pass, do not add a third user, release Caregiver AI, enable recurring publication, alter pricing/payment behavior, or broaden any capability. Any defined critical failure triggers immediate Human Only rollback and grant revocation.

## Repository handling note

This activation record is intentionally prepared locally without an immediate deployment. A newly deployed repository commit would no longer match the exact commit bound to the current release decision. Archive the documentation in Git only with an explicit no-deploy instruction, or record a fresh exact-commit release decision before any later deployment that changes production HEAD.
