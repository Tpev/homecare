# RC-time_correction-overview — time correction — Care details

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | Recurring care |
| Lifecycle stage | Timesheet / review |
| Route | /family/requests/38?tab=overview |
| Selected tab | overview |
| Panel / disclosure | default |
| Capture status | Captured |
| UX review | Audited — grouped evidence |
| UX findings | Discovery DISC-01 and delivery D01/D06: Keep this Care details destination stable and give every populated scope, task note, access/location/map, recipient and alternate-contact field a clear home; preserve profile snapshot, source/last-reviewed and selected caregiver links. D03: Show the pending correction as attention, never as final confirmation. Recurring D04/RC-UX-04: Keep the parent-plan link and dated-visit identity explicit; completing/cancelling this visit does not by itself close the plan. |
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

Viewport: **1440 × 1000 CSS px**. Document height: 3157 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8027/family/requests/38?tab=overview

[Focused view](<../screenshots/RC-time_correction-overview-desktop-focus.png>) · [First screenful](<../screenshots/RC-time_correction-overview-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/RC-time_correction-overview-desktop-full.png>) · [Raw visible text](<../evidence/RC-time_correction-overview-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/RC-time_correction-overview-desktop.json>)

![Desktop screenshot of time correction — Care details](<../screenshots/RC-time_correction-overview-desktop-focus.png>)

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

Visit #23

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

Request context

SCOPE OF WORK

Companionship, preparing lunch, and light housekeeping.

TIME EXPECTATIONS

Please arrive on time and leave a short update after the visit.

HOME ACCESS

Use the main entrance and ring apartment 2B. Maria will meet you for the first visit.

ADDRESS

142 Maple Grove Lane, Apartment 2B, Raleigh, NC 27601

Open full map

RECIPIENT

Eleanor Bennett

Mother

THIRD-PARTY CONTACT

Daniel Bennett

+1 919 555 0153

daniel.find-care-audit@example.com

Task list

3 task(s)

Companionship

Conversation, a crossword puzzle, and a short walk if Ellie feels up to it.

Meal preparation

Prepare soup and a sandwich for lunch; leave the kitchen tidy.

Light housekeeping

Wash dishes and keep the living room and hallway clear.

Selected caregiver

0 invite(s) sent

Sofia Martinez

Care rate: $30.00/hr* · processing fee $1.00/hr

7 years experience - 4.9 stars from 24 reviews

View profile
Go to visit

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
| H2 | Request context |
| H2 | Task list |
| H2 | Selected caregiver |

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

#### 6. View profile

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** View profile
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 7. Go to visit

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Go to visit
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
| Care story | http://127.0.0.1:8027/family/care/journey/regular/7 |
| Open full map | https://www.google.com/maps/search/?api=1&query=142%20Maple%20Grove%20Lane%2C%20Apartment%202B%2C%20Raleigh%2C%20NC%2027601 |
| View profile | http://127.0.0.1:8027/caregivers/sofia-find-care-audit?careRequest=38 |
| Terms of Service | http://127.0.0.1:8027/legal/platform-terms-of-service |
| Privacy Policy | http://127.0.0.1:8027/legal/privacy-policy |
| Payments & Cancellation | http://127.0.0.1:8027/legal/payment-terms-and-cancellation-policy |
| Caregiver Terms | http://127.0.0.1:8027/legal/caregiver-terms |
| Family Terms | http://127.0.0.1:8027/legal/client-and-family-terms |
| Safety Policy | http://127.0.0.1:8027/legal/acceptable-use-and-safety-policy |
| All Legal Documents | http://127.0.0.1:8027/legal |

### Disclosure states

No native disclosures recorded.

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
  "documentHeight": 3157,
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8027/family/requests/38?tab=overview"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 4516 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8027/family/requests/38?tab=overview

[Focused view](<../screenshots/RC-time_correction-overview-mobile-focus.png>) · [First screenful](<../screenshots/RC-time_correction-overview-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/RC-time_correction-overview-mobile-full.png>) · [Raw visible text](<../evidence/RC-time_correction-overview-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/RC-time_correction-overview-mobile.json>)

![Mobile screenshot of time correction — Care details](<../screenshots/RC-time_correction-overview-mobile-focus.png>)

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

Visit #23

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

Request context

SCOPE OF WORK

Companionship, preparing lunch, and light housekeeping.

TIME EXPECTATIONS

Please arrive on time and leave a short update after the visit.

HOME ACCESS

Use the main entrance and ring apartment 2B. Maria will meet you for the first visit.

ADDRESS

142 Maple Grove Lane, Apartment 2B, Raleigh, NC 27601

Open full map

RECIPIENT

Eleanor Bennett

Mother

THIRD-PARTY CONTACT

Daniel Bennett

+1 919 555 0153

daniel.find-care-audit@example.com

Task list

3 task(s)

Companionship

Conversation, a crossword puzzle, and a short walk if Ellie feels up to it.

Meal preparation

Prepare soup and a sandwich for lunch; leave the kitchen tidy.

Light housekeeping

Wash dishes and keep the living room and hallway clear.

Selected caregiver

0 invite(s) sent

Sofia Martinez

Care rate: $30.00/hr* · processing fee $1.00/hr

7 years experience - 4.9 stars from 24 reviews

View profile
Go to visit

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
| H2 | Request context |
| H2 | Task list |
| H2 | Selected caregiver |

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

#### 6. View profile

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** View profile
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 7. Go to visit

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Go to visit
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
| Care story | http://127.0.0.1:8027/family/care/journey/regular/7 |
| Open full map | https://www.google.com/maps/search/?api=1&query=142%20Maple%20Grove%20Lane%2C%20Apartment%202B%2C%20Raleigh%2C%20NC%2027601 |
| View profile | http://127.0.0.1:8027/caregivers/sofia-find-care-audit?careRequest=38 |

### Disclosure states

| Disclosure | State |
|---|---|
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
  "documentHeight": 4516,
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8027/family/requests/38?tab=overview"
}
```
