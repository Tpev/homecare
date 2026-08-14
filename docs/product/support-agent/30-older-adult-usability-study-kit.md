# Older-Adult Usability Study Kit

Status: Approved protocol; participant evidence open

Last updated: August 14, 2026

Owner: Product, design/accessibility, or either full administrator

## Release gate

The first named-user pilot remains blocked until five representative non-team older adults complete this protocol with synthetic accounts and data.

Passing requires:

- at least 27 of 30 task attempts completed without assistance;
- every participant can explain the final recap;
- every participant understands that a live request does not mean a Caregiver is hired;
- every participant understands that publication does not authorize payment;
- every participant can reach a person;
- no participant loses a safe draft;
- 200% zoom, screen-reader labels, keyboard/focus order, contrast, and touch targets pass.

## Recruitment matrix

Use participant IDs `OA-01` through `OA-05`; do not put participant names in the AI Support evidence register.

| Requirement | Minimum |
| --- | ---: |
| Age 65 or older | 5 |
| Age 75 or older | 2 |
| Self-reported low digital confidence | 2 |
| Primarily mobile | 3 |
| Uses enlarged text or another accessibility setting | 1 |
| LoLo team member | 0 |

Record age band rather than exact birth date. Do not collect medical conditions, financial information, real care-recipient details, or real addresses.

## Moderator introduction

Read this without coaching the interface:

> We are testing LoLo, not you. Please use the made-up information on the task card. Say what you are thinking if you are comfortable. I will not help unless you are stuck or want to stop. You can stop at any time.

Do not promise that the assistant is always correct. Do not reveal the expected navigation, answer, or button before the participant attempts the task.

## Synthetic task card

Use one consistent synthetic scenario, for example:

- Recipient: Arthur Example, father
- Location: 110 Pilot Lane, Raleigh, NC 27601
- Need: companionship and a light lunch
- Schedule: one visit tomorrow at 1:00 PM Eastern Time for three hours
- No medical procedure, emergency, 24/7 coverage, payment, or real person data

## Six tasks per participant

### Task 1 - Basic support answer

Prompt: **Ask how LoLo care requests work.**

Pass: participant opens support, recognizes the AI label, receives a grounded non-pricing answer, and can identify the next step without help.

### Task 2 - Safe navigation

Prompt: **Ask support to take you to your care requests.**

Pass: participant reaches the registered care-request page without using an arbitrary or unsafe route.

### Task 3 - Choose care type

Prompt: **Tell support what Arthur needs and decide which kind of request fits.**

Pass: participant reaches one-time care, or answers one short clarification, without the assistant guessing a material value.

### Task 4 - Complete the request

Prompt: **Use the task card to complete the one-time request.**

Pass: all required values are captured one short question at a time and the participant reaches the deterministic recap without moderator help.

### Task 5 - Review and modify

Prompt: **Explain what will happen, then change the visit length before confirming.**

Pass: participant can explain recipient, tasks, schedule, location, live-versus-hired, and payment timing; modifies the field and receives a fresh recap.

### Task 6 - Reach a person

Prompt: **Ask to talk with a person.**

Pass: participant finds or requests human transfer, sees the approved no-queue/no-time-promise message, and does not have to repeat the synthetic context.

## Per-participant record

| Field | Allowed value |
| --- | --- |
| Participant ID | `OA-01` through `OA-05` |
| Age band | 65-74, 75-84, or 85+ |
| Digital confidence | Low, medium, high |
| Primary device | Mobile or desktop |
| Accessibility setting | None, enlarged text, screen reader, keyboard, or safe short label |
| Task results | Pass unassisted, completed with assistance, not completed |
| Recap understood | Yes/No |
| Live is not hired understood | Yes/No |
| No payment authorization understood | Yes/No |
| Human transfer understood | Yes/No |
| Draft preserved | Yes/No |
| Content-free observation | At most 500 characters; no task-card or personal data copy |

## Aggregate scoring record

| Participant | T1 | T2 | T3 | T4 | T5 | T6 | Recap | Not hired | No payment | Human | Draft |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| OA-01 |  |  |  |  |  |  |  |  |  |  |  |
| OA-02 |  |  |  |  |  |  |  |  |  |  |  |
| OA-03 |  |  |  |  |  |  |  |  |  |  |  |
| OA-04 |  |  |  |  |  |  |  |  |  |  |  |
| OA-05 |  |  |  |  |  |  |  |  |  |  |  |

The denominator is always 30 task attempts. Assistance is a failed unassisted attempt even when the participant ultimately finishes.

## Accessibility record

Separately record:

- no horizontal overflow and usable controls at 200% zoom;
- visible focus and logical keyboard order;
- meaningful names and states with NVDA, VoiceOver, or an equivalent screen reader;
- contrast meeting the approved interface standard;
- at least 44-by-44-pixel primary touch controls;
- focus returning to the relevant draft/recap section after a validation error;
- short, singular questions;
- refresh, navigation, timeout, and confirmation expiry preserving the safe draft.

## Failure and retest rule

Any failed universal comprehension item blocks release. Fix the cause, rerun deterministic and accessibility regressions, then retest the affected task with enough new participants to retain five valid post-change observations. Do not erase or rewrite the failed historical record.

After all gates pass, record `older_adult_usability` and `accessibility` as separate Passed items in Admin Release readiness with only the aggregate counts, report reference, commit, and dates.
