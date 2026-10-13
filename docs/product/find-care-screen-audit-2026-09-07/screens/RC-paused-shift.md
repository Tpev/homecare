# RC-paused-shift — paused — Visit

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | Recurring care |
| Lifecycle stage | During shift |
| Route | /family/requests/36?tab=shift |
| Selected tab | shift |
| Panel / disclosure | default |
| Capture status | Captured |
| UX review | Audited — grouped evidence |
| UX findings | Delivery D02/D09: State visit paused, pause time/reason and caregiver resume responsibility; prioritize message/help and confirm any family End visit action. Preserve recorded check-in/location, schedule, payment and available task/event evidence without implying the recurring plan is paused. Recurring D04/RC-UX-04: Keep the parent-plan link and dated-visit identity explicit; completing/cancelling this visit does not by itself close the plan. |
| Redesign decision | Proposed; not implemented |
| Owner | Unassigned |
| Priority | P1 |

These screenshots show the current application with synthetic audit data. This is capture and source-audit evidence; no UI redesign is implemented. Remote map/payment widgets were blocked in this local capture session, so their current fallback states are preserved. Google Fonts were allowed.

## Additional capture context

```json
{
  "captureVersion": 4,
  "prepared": false
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 2820 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8027/family/requests/36?tab=shift

[Focused view](<../screenshots/RC-paused-shift-desktop-focus.png>) · [First screenful](<../screenshots/RC-paused-shift-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/RC-paused-shift-desktop-full.png>) · [Raw visible text](<../evidence/RC-paused-shift-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/RC-paused-shift-desktop.json>)

![Desktop screenshot of paused — Visit](<../screenshots/RC-paused-shift-desktop-focus.png>)

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
VISIT Paused
Payment Authorized

One-time care - Sep 07, 7:56 AM - 9:56 AM - Raleigh, NC

Visit #21

Care story

VISIT IN PROGRESS

The visit is paused.

Use this screen for check-in details, chat, safety support, and completion.

RIGHT NOW

Sofia paused the visit. They can resume or end it from their visit screen.

Message caregiver
The visit has ended
Get help

Visit

Time, location, payment

Help

Change or get help

Caregiver

Profile, chat

Care details

Needs, address, tasks

LIVE DETAILS

Paused visit details

Schedule, check-in, location, and payment stay here while pause details stay above.

Paused

Visit is paused

The caregiver paused the visit. They can resume or end from their visit screen.

LIVE CHECK-IN

Sofia is with Eleanor Bennett.

TIME

Sep 07

7:56 AM - 9:56 AM

CAREGIVER

Sofia Martinez

Checked in 7:56 AM

PAYMENT

Authorized

Authorized $62.00

CARE LOCATION

142 Maple Grove Lane, Apartment 2B, Raleigh, NC 27601

Open map
Eleanor Bennett - Mother

Checked in

Sofia checked in Sep 07, 7:56 AM.

GPS was captured at check-in.

Map and visit record

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
| H2 | Paused visit details |
| H3 | Sofia is with Eleanor Bennett. |

### Every visible control and its current value

#### 1. MB<br>Maria Bennett<br>Family

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** MB<br>Maria Bennett<br>Family
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 2. Message caregiver

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Message caregiver
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 3. The visit has ended

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** The visit has ended
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 4. Get help

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Get help
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 5. Visit<br><br>Time, location, payment

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Visit<br><br>Time, location, payment
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 6. Help<br><br>Change or get help

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Help<br><br>Change or get help
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 7. Caregiver<br><br>Profile, chat

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Caregiver<br><br>Profile, chat
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 8. Care details<br><br>Needs, address, tasks

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Care details<br><br>Needs, address, tasks
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 9. Chat with LoLo Support

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
| Care story | http://127.0.0.1:8027/family/care/journey/regular/5 |
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
| Map and visit record | Closed |

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
  "documentHeight": 2820,
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8027/family/requests/36?tab=shift"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 4097 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8027/family/requests/36?tab=shift

[Focused view](<../screenshots/RC-paused-shift-mobile-focus.png>) · [First screenful](<../screenshots/RC-paused-shift-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/RC-paused-shift-mobile-full.png>) · [Raw visible text](<../evidence/RC-paused-shift-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/RC-paused-shift-mobile.json>)

![Mobile screenshot of paused — Visit](<../screenshots/RC-paused-shift-mobile-focus.png>)

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
VISIT Paused
Payment Authorized

One-time care - Sep 07, 7:56 AM - 9:56 AM - Raleigh, NC

Visit #21

Care story

VISIT IN PROGRESS

The visit is paused.

Use this screen for check-in details, chat, safety support, and completion.

RIGHT NOW

Sofia paused the visit. They can resume or end it from their visit screen.

Message caregiver
The visit has ended
Get help

Visit

Time, location, payment

Help

Change or get help

Caregiver

Profile, chat

Care details

Needs, address, tasks

LIVE DETAILS

Paused visit details

Schedule, check-in, location, and payment stay here while pause details stay above.

Paused

Visit is paused

The caregiver paused the visit. They can resume or end from their visit screen.

LIVE CHECK-IN

Sofia is with Eleanor Bennett.

TIME

Sep 07

7:56 AM - 9:56 AM

CAREGIVER

Sofia Martinez

Checked in 7:56 AM

PAYMENT

Authorized

Authorized $62.00

CARE LOCATION

142 Maple Grove Lane, Apartment 2B, Raleigh, NC 27601

Open map
Eleanor Bennett - Mother

Checked in

Sofia checked in Sep 07, 7:56 AM.

GPS was captured at check-in.

Map and visit record
The visit has ended

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
| H2 | Paused visit details |
| H3 | Sofia is with Eleanor Bennett. |

### Every visible control and its current value

#### 1. Toggle navigation menu

- **tag:** BUTTON
- **type:** button
- **label:** Toggle navigation menu
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 2. Message caregiver

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Message caregiver
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 3. The visit has ended

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** The visit has ended
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 4. Get help

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Get help
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 5. Visit<br><br>Time, location, payment

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Visit<br><br>Time, location, payment
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 6. Help<br><br>Change or get help

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Help<br><br>Change or get help
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 7. Caregiver<br><br>Profile, chat

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Caregiver<br><br>Profile, chat
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 8. Care details<br><br>Needs, address, tasks

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Care details<br><br>Needs, address, tasks
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 9. The visit has ended

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** The visit has ended
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 10. Chat with LoLo Support

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
| Care story | http://127.0.0.1:8027/family/care/journey/regular/5 |
| Open map | https://www.google.com/maps/search/?api=1&query=142%20Maple%20Grove%20Lane%2C%20Apartment%202B%2C%20Raleigh%2C%20NC%2027601 |

### Disclosure states

| Disclosure | State |
|---|---|
| Map and visit record | Closed |
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
  "documentHeight": 4097,
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8027/family/requests/36?tab=shift"
}
```
