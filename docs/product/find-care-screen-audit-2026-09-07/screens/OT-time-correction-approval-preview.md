# OT-time-correction-approval-preview — Time correction — approval and payment confirmation preview

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | One-time care |
| Lifecycle stage | Timesheet / review |
| Route | /family/requests/32?tab=shift |
| Selected tab | shift |
| Panel / disclosure | confirmation preview |
| Capture status | Captured |
| UX review | Audited — grouped evidence |
| UX findings | Delivery D03: Keep the existing explicit correction approval preview, with recorded/requested hours, breaks, care amount, fee and total. Make the final paid outcome and payment-only recovery clear; retain original versions and change-request/escalation actions. |
| Redesign decision | Proposed; not implemented |
| Owner | Unassigned |
| Priority | P1 |

These screenshots show the current application with synthetic audit data. This is capture and source-audit evidence; no UI redesign is implemented. Remote map/payment widgets were blocked in this local capture session, so their current fallback states are preserved. Google Fonts were allowed.

## Additional capture context

```json
{
  "captureVersion": 4,
  "prepared": true
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 3252 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8027/family/requests/32?tab=shift

[Focused view](<../screenshots/OT-time-correction-approval-preview-desktop-focus.png>) · [First screenful](<../screenshots/OT-time-correction-approval-preview-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/OT-time-correction-approval-preview-desktop-full.png>) · [Raw visible text](<../evidence/OT-time-correction-approval-preview-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/OT-time-correction-approval-preview-desktop.json>)

![Desktop screenshot of Time correction — approval and payment confirmation preview](<../screenshots/OT-time-correction-approval-preview-desktop-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
FAMILY WORKSPACE
Care
Caregivers
Messages
Request care
MB
Maria Bennett
Family
About Ellie
Mother
75-84
She/her
At a glance

A retired teacher who enjoys gardening, family stories, and a friendly conversation.

Interests and comforts

Crossword puzzles, jazz, and sitting in the garden when the weather is mild.

A good visit

A relaxed pace, a warm lunch, and a short walk make a good visit.

Communication
Use short, clear sentences
Give extra time to answer

Please introduce yourself and give Ellie time to answer.

Support and mobility
Companionship and conversation
Meal preparation or eating support
Light household help
Walking or mobility support

Mobility

Uses an aid

Uses a walking cane outdoors; stay nearby on steps.

Routine and comfort

Usual routine

Lunch around noon, followed by a short walk and a cup of tea.

Food and drink

Prefers soup and a sandwich. Encourage a glass of water at lunch.

Comfort and reassurance

Benefits from reminders or reassurance
Important for safety
Fall risk

Keep the hallway clear and remind Ellie to use the handrail.

What the family is looking for

Helpful caregiver qualities

Patient
Calm
Conversational

Please do

Explain what you are doing and offer choices.

Contacts and care coordination

Full name: Eleanor Bennett

Daniel Bennett · Son

+1 919 555 0153

daniel.find-care-audit@example.com

Contact Maria first; Daniel is the backup family contact.

Shared by Ellie's family · last reviewed September 7, 2026.
Eleanor Bennett · Mon, Sep 7
FILLED
VISIT Completed
Payment Authorized

One-time care - Sep 07, 2:51 AM - 4:51 AM - Raleigh, NC

Visit #20

Care story

REVIEW HOURS

Caregiver hours need your review.

Check the worked time and payment amount. Approving will capture payment and move payout forward.

Visit

Time, location, payment

Help

Change or get help

Caregiver

Profile, chat

Care details

Needs, address, tasks

VISIT RECORD

Final details

Keep the final schedule, time, payment, notes, and feedback in one record.

Completed

TIME CORRECTION · VISIT #20

Sofia asked you to review visit time

Monday, September 7, 2026 · America/New York

Waiting for family review

SCHEDULED

2:51 AM – 4:51 AM

APP RECORDED

2:51 AM – 4:51 AM

REQUESTED

2:51 AM – 4:36 AM

1 hr 45 min after 0 min unpaid break

WHAT HAPPENED

I forgot to end the visit

I finished helping Ellie at the time below but forgot to end the visit in the app until 15 minutes later.

FINAL AMOUNT IF APPROVED

$54.25

For 1 hr 45 min of care.

Care $52.50 + $1.75 processing fee.

CONFIRM APPROVAL

Approve 1 hr 45 min and pay $54.25?

This confirms the caregiver’s actual care hours. The visit record and payment will be updated together.

Go back
Confirm approval and payment

SCHEDULE

Sep 07, 2026

2:51 AM - 4:51 AM

CAREGIVER

Sofia Martinez

Checked in Sep 07, 2:51 AM

CARE LOCATION

142 Maple Grove Lane, Apartment 2B, Raleigh, NC 27601

Open map
Eleanor Bennett - Mother

VISIT RECEIPT

WORKED TIME

2h 00m

PAYMENT

$60.00

Authorized

TIMESHEET

Confirmed

Confirmed by family

Detailed visit record

© 2026 LoLo Care Inc

﻿
Terms of Service
Privacy Policy
Payments & Cancellation
Caregiver Terms
Family Terms
Safety Policy
All Legal Documents
Support
```

### Visible headings

| Level | Heading |
|---|---|
| H2 | About Ellie |
| H3 | At a glance |
| H3 | Communication |
| H3 | Support and mobility |
| H3 | Routine and comfort |
| H3 | Important for safety |
| H3 | What the family is looking for |
| H3 | Contacts and care coordination |
| H1 | Eleanor Bennett · Mon, Sep 7 |
| H2 | Final details |
| H3 | Sofia asked you to review visit time |
| H4 | Approve 1 hr 45 min and pay $54.25? |

### Every visible control and its current value

#### 1. MB<br>Maria Bennett<br>Family

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** MB<br>Maria Bennett<br>Family
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 2. Visit<br><br>Time, location, payment

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Visit<br><br>Time, location, payment
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 3. Help<br><br>Change or get help

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Help<br><br>Change or get help
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 4. Caregiver<br><br>Profile, chat

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Caregiver<br><br>Profile, chat
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 5. Care details<br><br>Needs, address, tasks

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Care details<br><br>Needs, address, tasks
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 6. Go back

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Go back
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 7. Confirm approval and payment

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Confirm approval and payment
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 8. Chat with LoLo Support

- **tag:** BUTTON
- **type:** button
- **label:** Chat with LoLo Support
- **text:** Support
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

### Every visible link

| Label | Destination |
|---|---|
| FAMILY WORKSPACE | http://127.0.0.1:8027/family/requests |
| Care | http://127.0.0.1:8027/family/requests |
| Caregivers | http://127.0.0.1:8027/caregivers/search |
| Messages | http://127.0.0.1:8027/messages |
| Request care | http://127.0.0.1:8027/family/requests/create |
| Notifications | http://127.0.0.1:8027/family/notifications |
| +1 919 555 0153 | tel:+1 919 555 0153 |
| daniel.find-care-audit@example.com | mailto:daniel.find-care-audit@example.com |
| Care story | http://127.0.0.1:8027/family/care/journey/request/32 |
| Open map | https://www.google.com/maps/search/?api=1&query=142%20Maple%20Grove%20Lane%2C%20Apartment%202B%2C%20Raleigh%2C%20NC%2027601 |
| Terms of Service | http://127.0.0.1:8027/legal/platform-terms-of-service |
| Privacy Policy | http://127.0.0.1:8027/legal/privacy-policy |
| Payments & Cancellation | http://127.0.0.1:8027/legal/payment-terms-and-cancellation-policy |
| Caregiver Terms | http://127.0.0.1:8027/legal/caregiver-terms |
| Family Terms | http://127.0.0.1:8027/legal/client-and-family-terms |
| Safety Policy | http://127.0.0.1:8027/legal/acceptable-use-and-safety-policy |
| All Legal Documents | http://127.0.0.1:8027/legal |

### Disclosure states

| Disclosure | State |
|---|---|
| Detailed visit record | Closed |

### Visible images and loading state

| Alternative text | Source | Loaded |
|---|---|---|
| LoLo | http://127.0.0.1:8027/images/marketing/lolo/lolo-app-icon.svg | true |
| LoLo | http://127.0.0.1:8027/images/marketing/lolo/lolo-wordmark-evergreen.svg | true |

### Capture metadata

```json
{
  "width": 1440,
  "height": 1000,
  "documentWidth": 1440,
  "documentHeight": 3252,
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8027/family/requests/32?tab=shift"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 5054 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8027/family/requests/32?tab=shift

[Focused view](<../screenshots/OT-time-correction-approval-preview-mobile-focus.png>) · [First screenful](<../screenshots/OT-time-correction-approval-preview-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/OT-time-correction-approval-preview-mobile-full.png>) · [Raw visible text](<../evidence/OT-time-correction-approval-preview-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/OT-time-correction-approval-preview-mobile.json>)

![Mobile screenshot of Time correction — approval and payment confirmation preview](<../screenshots/OT-time-correction-approval-preview-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
About Ellie
Mother
75-84
She/her
At a glance

A retired teacher who enjoys gardening, family stories, and a friendly conversation.

Interests and comforts

Crossword puzzles, jazz, and sitting in the garden when the weather is mild.

A good visit

A relaxed pace, a warm lunch, and a short walk make a good visit.

Communication
Use short, clear sentences
Give extra time to answer

Please introduce yourself and give Ellie time to answer.

Support and mobility
Companionship and conversation
Meal preparation or eating support
Light household help
Walking or mobility support

Mobility

Uses an aid

Uses a walking cane outdoors; stay nearby on steps.

Routine and comfort

Usual routine

Lunch around noon, followed by a short walk and a cup of tea.

Food and drink

Prefers soup and a sandwich. Encourage a glass of water at lunch.

Comfort and reassurance

Benefits from reminders or reassurance
Important for safety
Fall risk

Keep the hallway clear and remind Ellie to use the handrail.

What the family is looking for

Helpful caregiver qualities

Patient
Calm
Conversational

Please do

Explain what you are doing and offer choices.

Contacts and care coordination

Full name: Eleanor Bennett

Daniel Bennett · Son

+1 919 555 0153

daniel.find-care-audit@example.com

Contact Maria first; Daniel is the backup family contact.

Shared by Ellie's family · last reviewed September 7, 2026.
Eleanor Bennett · Mon, Sep 7
FILLED
VISIT Completed
Payment Authorized

One-time care - Sep 07, 2:51 AM - 4:51 AM - Raleigh, NC

Visit #20

Care story

REVIEW HOURS

Caregiver hours need your review.

Check the worked time and payment amount. Approving will capture payment and move payout forward.

Visit

Time, location, payment

Help

Change or get help

Caregiver

Profile, chat

Care details

Needs, address, tasks

VISIT RECORD

Final details

Keep the final schedule, time, payment, notes, and feedback in one record.

Completed

TIME CORRECTION · VISIT #20

Sofia asked you to review visit time

Monday, September 7, 2026 · America/New York

Waiting for family review

SCHEDULED

2:51 AM – 4:51 AM

APP RECORDED

2:51 AM – 4:51 AM

REQUESTED

2:51 AM – 4:36 AM

1 hr 45 min after 0 min unpaid break

WHAT HAPPENED

I forgot to end the visit

I finished helping Ellie at the time below but forgot to end the visit in the app until 15 minutes later.

FINAL AMOUNT IF APPROVED

$54.25

For 1 hr 45 min of care.

Care $52.50 + $1.75 processing fee.

CONFIRM APPROVAL

Approve 1 hr 45 min and pay $54.25?

This confirms the caregiver’s actual care hours. The visit record and payment will be updated together.

Go back
Confirm approval and payment

SCHEDULE

Sep 07, 2026

2:51 AM - 4:51 AM

CAREGIVER

Sofia Martinez

Checked in Sep 07, 2:51 AM

CARE LOCATION

142 Maple Grove Lane, Apartment 2B, Raleigh, NC 27601

Open map
Eleanor Bennett - Mother

VISIT RECEIPT

WORKED TIME

2h 00m

PAYMENT

$60.00

Authorized

TIMESHEET

Confirmed

Confirmed by family

Detailed visit record

© 2026 LoLo

Legal links
```

### Visible headings

| Level | Heading |
|---|---|
| H2 | About Ellie |
| H3 | At a glance |
| H3 | Communication |
| H3 | Support and mobility |
| H3 | Routine and comfort |
| H3 | Important for safety |
| H3 | What the family is looking for |
| H3 | Contacts and care coordination |
| H1 | Eleanor Bennett · Mon, Sep 7 |
| H2 | Final details |
| H3 | Sofia asked you to review visit time |
| H4 | Approve 1 hr 45 min and pay $54.25? |

### Every visible control and its current value

#### 1. Toggle navigation menu

- **tag:** BUTTON
- **type:** button
- **label:** Toggle navigation menu
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 2. Visit<br><br>Time, location, payment

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Visit<br><br>Time, location, payment
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 3. Help<br><br>Change or get help

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Help<br><br>Change or get help
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 4. Caregiver<br><br>Profile, chat

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Caregiver<br><br>Profile, chat
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 5. Care details<br><br>Needs, address, tasks

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Care details<br><br>Needs, address, tasks
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 6. Go back

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Go back
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 7. Confirm approval and payment

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Confirm approval and payment
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 8. Chat with LoLo Support

- **tag:** BUTTON
- **type:** button
- **label:** Chat with LoLo Support
- **text:** Support
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

### Every visible link

| Label | Destination |
|---|---|
| Family workspace | http://127.0.0.1:8027/family/requests |
| Notifications | http://127.0.0.1:8027/family/notifications |
| +1 919 555 0153 | tel:+1 919 555 0153 |
| daniel.find-care-audit@example.com | mailto:daniel.find-care-audit@example.com |
| Care story | http://127.0.0.1:8027/family/care/journey/request/32 |
| Open map | https://www.google.com/maps/search/?api=1&query=142%20Maple%20Grove%20Lane%2C%20Apartment%202B%2C%20Raleigh%2C%20NC%2027601 |

### Disclosure states

| Disclosure | State |
|---|---|
| Detailed visit record | Closed |
| Legal links | Closed |

### Visible images and loading state

| Alternative text | Source | Loaded |
|---|---|---|
| LoLo | http://127.0.0.1:8027/images/marketing/lolo/lolo-app-icon.svg | true |
| LoLo | http://127.0.0.1:8027/images/marketing/lolo/lolo-wordmark-evergreen.svg | true |

### Capture metadata

```json
{
  "width": 390,
  "height": 844,
  "documentWidth": 390,
  "documentHeight": 5054,
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8027/family/requests/32?tab=shift"
}
```
