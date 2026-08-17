# Original Objective Completion Audit and Expansion Work Queue

> Historical record: `DEC-072` superseded this document's expansion gates on August 17, 2026. Its completed safety findings remain useful evidence, but its preflight, exact-commit approval, and deferred-work queue no longer block Pilot or Everyone availability.

Status: Exact two-user Family pilot active; original preparation objective audited; expansion evidence open

Last updated: August 17, 2026

Owner: Product and either full Administrator

## Purpose

This record audits the original limited-release objective against current repository and production evidence. `DEC-070` originally accepted six items as `Deferred before expansion`; the staffed safety item is now Passed, leaving five. A deferral is never a Pass.

Later Product decisions are authoritative where they changed the original preparation sequence: `DEC-068` allowed the current server-side project key and made the external `$25` provider alert optional for the initial pilot; `DEC-070` permitted exactly six visible deferrals for two users only; and `DEC-071` subsequently authorized and activated the exact two-user pilot. Therefore the original instruction to keep both guards off and create no grants describes the preparation state, not the current active-pilot state.

## Requirement-by-requirement audit

| Original requirement | Current result | Authoritative evidence | Remaining work |
| --- | --- | --- | --- |
| Dedicated `AI_SUPPORT_SAFETY_IDENTIFIER_SECRET` | Passed | Production current-key verifier and initial-pilot preflight verified a separate server-only safety secret | Rotate through the normal secret procedure if exposure is suspected |
| Verify key belongs to intended project | Deferred before expansion | The configured project-scoped key authenticated at the standard API destination; exact Admin API project identity was deliberately not inferred under `DEC-068` | Obtain account-owned project/key evidence with an ephemeral Admin API credential before expansion |
| Data sharing disabled | Deferred before expansion | Application `store:false`, no hosted conversations/files/vector stores/tools/background mode, and default no-training baseline are proved; the account's actual sharing selection is not | Record account-owned sharing state and effective project/organization retention |
| `$25` billing alert | Optional and unverified | `DEC-068` removed this from the initial-pilot gate; application cost/turn/latency stops remain active | Create and verify the provider alert if Product restores it as a requirement |
| Keep both runtime guards off | Completed preparation state; superseded for activation | Both guards stayed off through provider checks, rehearsal, preflight, decision, grant creation, and stored-control ordering | Both guards are now intentionally on for the exact approved pilot; turning either off is an emergency rollback action |
| Project/credential evidence | Passed for initial pilot | `Configured provider credential` is Passed from the content-free current-key verification | Exact project identity remains required before expansion |
| No-training and retention evidence | Deferred before expansion | `Provider no-training and retention controls` remains Deferred under `DEC-070` | Obtain account-owned sharing and effective-retention proof |
| Provider destination/contract | Deferred before expansion | Standard API destination authentication is proved and Product accepted standard processing with no residency claim; retained contractual reference is missing | Attach the governing provider/contract reference before expansion |
| Provider deletion behavior | Passed | Runtime and synthetic Responses requests enforce `store:false`; no provider-hosted state, files, tools, or background jobs are created | Re-evaluate if the endpoint or provider-state design changes |
| Optional ZDR request status | Optional; no approval claimed | The evidence register contains the optional ZDR item and no false approval claim | Record actual request/approval status only if LoLo pursues ZDR |
| Content-free alert test to both Administrators | Passed | Both full Administrators confirmed both email and in-app receipt after the SES dependency correction | Repeat after notification-provider or routing changes |
| Monitoring ownership | Passed | Administrators `1` and `18` are co-owners; either may act independently | Preserve at least one staffed owner throughout the pilot |
| Cost/performance monitoring | Passed | Production scheduler runs Laravel every minute and `ai-support:monitor-health` every five minutes; post-smoke evidence recorded one `5098 ms` conversation, `$0.000838` daily cost, zero failed notifications, and no cost stop | Continue daily review and investigate warnings/stops |
| Exact-commit rehearsal | Passed | The isolated browser/live-provider rehearsal passed its frozen cases, extraction gate, cost ceiling, database destruction, and exact-candidate evidence | Repeat after runtime, prompt, provider, safety, or tool-contract changes |
| Human takeover | Passed | Production ticket `#28` proved the live basic path; exact-commit record `SR-20260817-0DCB433D` passed takeover-before-reply and all staffed safety observations | Repeat after relevant runtime or takeover changes |
| Emergency and 24/7 transfer | Passed | The exact-commit staffed record and deterministic evidence prove 911-before-transfer, provider skip, and 24/7 transfer without queue/time promise | Repeat after safety-routing changes |
| Automatic stop and rollback | Passed | The exact-commit staffed record proves one incident, both-Administrator alerts, resolution without re-enable, Human Only rollback, and preservation of drafts, valid requests, confirmed evidence, and receipts | Repeat after stop, incident, invalidation, or rollback changes |
| Confirmation invalidation | Passed | The exact-commit staffed record directly witnessed active-recap invalidation and stale-confirm denial; feature/browser evidence covers expiry, logout, renewal, and duplicates | Repeat after confirmation-contract changes |
| Human chat remains available | Passed | Production ticket `#28` and the complete staffed record retained human access throughout takeover, stop, rollback, and return | Continue monitoring every pilot interaction |
| Automated accessibility checks | Passed | Focused desktop/mobile/WebKit evidence passed zoom/reflow, keyboard/focus, contrast, touch target, accessible names, and draft preservation | Repeat after relevant UI changes |
| Real screen-reader session | Deferred before expansion | No NVDA, VoiceOver, Narrator, or equivalent witnessed session is claimed | Complete and record one real assistive-technology session |
| Five-person older-adult study | Deferred before expansion | Protocol, strict template, validator, synthetic scenario, and recruitment matrix are ready; no participant evidence is claimed | Run five qualifying non-team sessions, correct comprehension failures, and retest as required |
| Name exactly two Family users | Passed | Production Family IDs `19` and `282` are the exact enforced cohort | Do not add a third user before expansion readiness Passes |
| Do not create grants yet | Completed preparation state; superseded for activation | Preflight and release approval were recorded before any grant | Two grants now exist only because Product later instructed `START TWO-USER PILOT` |
| Read-only preflight | Passed before activation | Current approval snapshot: 17 Passed, 5 Deferred, 0 Blocking | The preparation preflight is intentionally false while controls/guards/grants are active; this is not an incident |
| Separate explicit release decision | Passed and effective | Decision `334025a1-78f2-4290-9d72-df7f3ef5268f` is bound to deployed commit `0dcb433d1ecb574e14804a37f5d5bc2da1d1b901` and approved snapshot SHA-256 `12a9f397edf6cdb320cf2d5417538c9f1a430f6eb263fed4d90f1ff6effd14c0` | Record a new decision before exposing a different deployed commit |

## Remaining expansion work queue

The next evidence must be real and may not be manufactured from automated tests or documentation:

1. obtain provider-account sharing, effective-retention, intended-project/key, and contractual-reference evidence;
2. complete both downstream-destination inventories and an isolated restore/re-deletion rehearsal;
3. complete one real screen-reader session;
4. complete five qualifying non-team older-adult sessions, fix any comprehension problem, and validate the final record with `ai-support:validate-older-adult-study`;
5. record each resulting content-free evidence version as Passed and run `ai-support:release-preflight --scope=expansion`.

Until all five remaining `DEC-070` items Pass, the current boundary remains exact users `19` and `282`, Family role only, non-pricing support, and one-time request publication only. Caregiver AI, recurring publication, a third user, and any broader capability remain prohibited.

## Exact safety-runner state

On August 17, 2026, `php artisan ai-support:rehearse-release` was run without execution flags. It returned the expected plan-only matrix and explicitly made no process, provider, database, or report mutation. The active checkout correctly reports tracked documentation changes, so the full runner would refuse execution there. Do not bypass this safeguard or disturb the deployed exact-commit decision merely to create a clean working tree. Run the later staffed drill from a clean, dedicated synthetic checkout at its deliberately selected candidate commit.

The current commit's focused safety baseline was also rerun on August 17: `InteractiveSupportRuntimeTest`, `LimitedReleaseReadinessTest`, and `HumanEvidenceValidationCommandTest` passed 33 tests and 227 assertions. This covers exact-user isolation, emergency and 24/7 routing, recap and confirmation behavior, takeover precedence, automatic stop/incident behavior, monitor thresholds, release boundaries, and strict rejection of incomplete human records. It is regression evidence only and does not convert any witnessed-human item to Passed.

## August 17 staffed synthetic rollback finding

A dedicated detached checkout at deployed commit `5c20f4167f1199ca5b4248a7b3516dfa3998b91b` was connected only to a fresh synthetic SQLite database. The UI directly showed an active recap, persistent **Talk to a person**, takeover into human-only, removal of the pending recap, continued public chat, and a stale-confirm attempt rejected with no Care Request or confirmed-action receipt for that ticket. Separate synthetic tickets showed the 911 instruction before emergency transfer, zero emergency model-turn events, and 24/7 transfer without a queue, minute, or hour promise.

The first automatic-stop/rollback observation then found a real failure: the valid Care Request and immutable confirmed-action evidence survived, but the user-facing completed receipt action lost its payload. The failed result is retained here and is not rewritten as a pass.

Root cause: both the global control-stop invalidation query and the per-ticket human-handoff invalidation query selected every unconsumed message action. A completed receipt is intentionally not a pending confirmation and has no `consumed_at`, so it was incorrectly selected with recaps and other pending actions.

The corrective release candidate excludes `AiSupportMessageAction::TYPE_RECEIPT` from both invalidation queries while leaving pending recap and preview invalidation unchanged. Regression coverage now publishes a synthetic one-time request, captures its receipt, performs human handoff and automatic stop, and proves the receipt payload, Care Request, and confirmed-action evidence all survive. The complete AI Support feature suite passes 101 tests and 821 assertions; the focused safety subset passes 33 tests and 233 assertions.

A fresh synthetic rerun with the correction observed two Administrators, four handoff delivery rows, four incident delivery rows, both in-app deliveries Sent per event, both email deliveries accepted into the isolated array transport per event, one automatic-stop incident, resolution without re-enable, Human Only on, the active recap invalidated and content removed, safe draft preserved, valid Care Request preserved, confirmed-action evidence preserved, completed receipt preserved, and zero remaining open automated tickets. This corrected run is implementation verification only until the fix is committed as an exact candidate, the complete 14-field record is rerun against that clean commit, and production follows the deliberate exact-commit reapproval sequence.

The correction was committed and pushed to `master` as exact candidate `0dcb433d1ecb574e14804a37f5d5bc2da1d1b901`. A new clean detached checkout of that commit repeated the UI takeover, stale-confirm, emergency, 24/7, automatic-stop, two-Administrator notification, resolution, Human Only, invalidation, preservation, and continuing-human-chat observations. The content-free record `SR-20260817-0DCB433D` passed `14 / 14` structural and gate validation with SHA-256 `a6a055cb7c9d7152a8811dff826333937307f6c2f3388ce43dc7e4853ca9c552` and zero validator mutation.

Production was then contained before deployment: Human Only was turned on first; both exact grants were revoked; Support Answers, the one-time tool/commit, all released Family capabilities, Family role, user visibility, and master controls were turned off. The Admin overview verified `Master off · user-visible off · human-only on`, zero active and scheduled grants, and human support preserved. The deployed `5c20f416` rollback evidence was versioned as `Failed`; its evidence summary explicitly states that the corrected candidate is not yet deployed or approved. Do not replace that Failed evidence with Passed until production deploys exact `0dcb433d`, both deployment guards are off, and the candidate identity is verified.

Production deployed exact commit `0dcb433d1ecb574e14804a37f5d5bc2da1d1b901` on August 17. The post-deploy health monitor reported zero failed operations notifications, daily model cost `$0.000838`, and no reached daily stop. A fresh Admin reload verified both deployment guards Off, Human Only containment unchanged, and the exact safety record above was entered as a new content-free `Passed` evidence version. The evidence write changed no runtime control and created no grant. Initial-pilot readiness then changed to `READY FOR EXPLICIT INITIAL PILOT APPROVAL`; activation remains prohibited until the new exact-commit release decision is recorded and the ordered two-user reactivation is completed.

The fresh read-only preflight then returned `17` Passed, `5` Deferred before expansion, and `0` Blocking. Release decision `334025a1-78f2-4290-9d72-df7f3ef5268f`, snapshot SHA-256 `12a9f397edf6cdb320cf2d5417538c9f1a430f6eb263fed4d90f1ff6effd14c0`, approved exact commit `0dcb433d` for only IDs `19` and `282`. The two expiring grants and only the approved Family/one-time controls were restored under Human Only with both deployment guards off. Production then enabled both guards through `deploy.sh` without changing the commit; health remained green with zero failed operations notifications and no daily stop. Admin verified the exact decision effective, two grants, zero incidents, zero warnings, and correct non-pilot boundaries before Human Only was removed last. Final evidence showed users `19` and `282` Eligible, Family user `392` denied for no active exact grant, and Caregiver user `399` denied because the role is not released.
