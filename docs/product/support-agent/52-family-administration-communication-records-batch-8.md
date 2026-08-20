# Family Administration, Communication, and Records — Batch 8

Status: Source implementation complete; automated verification complete; production deployment, KB publication, pilot activation, and authenticated pilot audit pending; **Live for everyone remains off**

Implemented: August 20, 2026

Scope: `FAM-ACCOUNT-001` through `FAM-ACCOUNT-020`, `FAM-ACCESS-001` through `FAM-ACCESS-020`, `FAM-COMMS-001` through `FAM-COMMS-017`, and `FAM-HISTORY-001` through `FAM-HISTORY-015`

## Outcome

Batch 8 adds the ordinary account-administration layer a Family user needs when they do not understand the app. In the same support conversation, the assistant can explain the relevant rule, read narrowly authorized current state, open the correct registered destination, and complete ten bounded account, membership, invitation, and notification operations.

The model receives no database, ORM, arbitrary URL, DOM selector, or generic mutation tool. The application resolves the declared intent, identifies the signed-in actor and active Family Account, performs account-scoped reads, produces a material recap for consequential actions, rechecks fresh state at confirmation, invokes one narrow existing service or model operation, and records an authoritative receipt.

## Implemented account coverage

| Family goal | Assistant behavior |
| --- | --- |
| Sign up, sign in, sign out, or recover a password | Give secure route-specific guidance. The authenticated assistant does not claim it can restore a signed-out session or handle credentials in chat |
| Change a password or account email | Open the existing secure Account Settings workflow; passwords, verification links, and secrets never enter chat |
| Change the account name | Show the exact old and new name, require confirmation, save, and verify |
| Understand or resend email verification | Read verification state; resend only for the signed-in unverified user after confirmation |
| Understand settings and account consequences | Explain current supported settings and the effect of deletion without pretending unsupported automation exists |
| Delete an account, report takeover, correct/export personal data | Transfer with the conversation context; no destructive or privacy determination is automated |
| Ask for phone-number or MFA settings | Truthfully identify the current product gap instead of inventing a setting |

## Implemented Family-access coverage

| Family goal | Assistant behavior |
| --- | --- |
| See members, roles, or pending invitations | Read only the active Family Account's current members and invitations |
| Invite a Family member | Owner-only; collect one email, recap it, confirm, create the invitation, and verify |
| Resend or replace an expired invitation | Owner-only; operate on one authorized pending or expired invitation after confirmation |
| Cancel an invitation | Owner-only; recap one pending invitation, confirm, cancel, and verify |
| Remove a member | Owner-only; identify one other active member, recap the loss of access, confirm, remove, and verify |
| Leave a Family Account | Member-only; recap the exact account and access consequence, confirm, leave, and verify. Owners transfer to a human |
| Accept or reject an invitation | Open the secure invitation workflow; the assistant never asks the user to paste an invitation token |
| Understand shared billing and attribution | Explain the shared Family payment method and identify the authorized actor attached to supported records |
| Transfer ownership, restore ended access, or impose granular member restrictions | Transfer governance decisions or state the unsupported product boundary without changing membership |

## Implemented communication and history coverage

| Family goal | Assistant behavior |
| --- | --- |
| Open conversations or message a caregiver | Reuse the exact account-authorized conversation and confirmed-message path from Batch 6 |
| See notifications | Read recent authorized notifications and unread count, with the Notifications destination |
| Mark one notification read | Perform the immediate low-risk action and verify the notification is read |
| Mark all notifications read | Show the unread count, require confirmation, update only the active Family Account, and verify |
| Change notification preferences or unsubscribe from nonessential email | Show the exact current and proposed channel setting, confirm, save, and verify; essential account/service messages are not silently disabled |
| Troubleshoot a missing notification | Show current preferences and safe recent delivery state without exposing provider errors or another account |
| Review care history and totals | Use the existing normalized Family history reader for authorized completed care, hours, and Family-visible totals |
| Inspect an historical visit or request | Re-authorize the exact record and offer its registered Family destination |
| Print or retain a record | Explain the current browser-print path truthfully; do not claim an official export file exists when it does not |
| Correct a historical record, request a formal receipt, or raise a dispute | Transfer with the relevant authorized record context; immutable history is not edited automatically |
| Reuse a previous request | Reuse the Batch 5 reversible draft/copy flow and require a new recap before publication |

## Narrow Batch 8 tools

All Batch 8 tools belong to default-off capability `family_administration_v1` and tool version `v1`.

- `account.name.update`
- `account.verification.resend`
- `family-access.invite`
- `family-access.invitation.resend`
- `family-access.invitation.cancel`
- `family-access.member.remove`
- `family-access.leave`
- `notification.mark-read`
- `notification.mark-all-read`
- `notification.preferences.update`

Except for marking one selected notification read, writes use a 30-minute recap, exact actor/account/resource authorization, fresh-state assertions, an idempotency key, and a post-action receipt. A stale recap is denied and rebuilt rather than applied to changed state. The immediate mark-read path is separately authorized and event-recorded because it is low risk and reversible in effect.

## Authorization and safety boundaries

- Every personalized fact is derived after signed-in user and active Family membership checks.
- Owner-only membership actions recheck ownership at confirmation time.
- Another Family Account's user, invitation, notification, conversation, request, or history record is never accepted because its ID was mentioned in chat.
- Passwords, payment credentials, verification links, invitation tokens, session tokens, and provider errors never appear in assistant context or receipts.
- Account deletion, ownership transfer, account-takeover reports, privacy rights, record disputes, and exceptional notification failures remain human-owned.
- Unsupported phone/MFA/member-restriction behavior is described as unavailable; the assistant does not invent a workaround.
- Emergency and medical classifiers still precede every ordinary account path.

## Governed knowledge and evaluations

Batch 8 shares manifest `family-administration-support-kb-v1` with Batch 9. The package contains 44 governed entries and 220 deterministic evaluations:

| Area | Entries | Registry intents |
| --- | ---: | ---: |
| Account and security | 8 | 20 |
| Family access and permissions | 7 | 20 |
| Messages and notifications | 7 | 17 |
| Care history and records | 6 | 15 |
| Continuous Coverage | 8 | 26 |
| Support, privacy, and exceptional handling | 8 | 20 plus 17 existing orientation/safety mappings |
| Total | 44 | 118 Batch 8/9 intents plus 17 existing support/orientation mappings |

Every entry has five cases: positive, boundary, wrong-account, stale/unavailable, and handoff. The generated Family catalog now validates all **324 / 324** rows with **324 / 324** explicit KB mappings and **1,296** phrase definitions. Batch 8 contributes **72 intents** and **288 registered phrases**.

The final source baseline passes **219 / 219 AI Support tests with 5,708 assertions**. The isolated Family Batch 1–9 harness passes **127 / 127 tests with 4,899 assertions**, including **472 / 472** Batch 8/9 phrases, **118 / 118** Batch 8/9 intents, and **10 / 10** protected collision cases. The final focused changes for active Continuous Coverage shift counting and expired-invitation replacement are additionally covered by the Batch 8/9 regression.

Publication command:

```bash
php artisan ai-support:import-family-administration-kb \
  --publish \
  --actor-email=test@test.com \
  --reason="Publish approved Batches 8 and 9 Family administration and exceptional-support knowledge for the two-user pilot." \
  --confirm=PUBLISH-FAMILY-ADMINISTRATION-SUPPORT-KB
```

The import is exact and idempotent. It refuses conflicting content and changes neither AI availability, pilot grants, Family membership, notifications, nor care records.

## Pilot activation boundary

After deployment and publication, activate the capability only for the two existing pilot grants:

```bash
php artisan ai-support:activate-batch9-pilot --actor-email=test@test.com
```

The command refuses activation unless exactly the configured two pilot users are effective and `general_release_enabled` is off. It extends only those two grants. It does not enable **Live for everyone** and it does not grant Continuous Coverage mutations.

## Required production audit

Source completion is not production evidence. After deployment, audit with one of the exact Family pilot users:

1. read members and pending invitations without mutation;
2. prepare, cancel, and then deliberately confirm one disposable invitation action;
3. read notifications and verify one safe read-state action;
4. read care history and open an authorized record;
5. verify one account-security guide never asks for a credential;
6. verify an owner-only or wrong-account denial;
7. verify a privacy/account-deletion request transfers in the same conversation; and
8. verify Admin sees the transcript, confirmation receipt, and transfer context.

Delete or reverse any disposable synthetic record created by the audit. Keep **Live for everyone** off.
