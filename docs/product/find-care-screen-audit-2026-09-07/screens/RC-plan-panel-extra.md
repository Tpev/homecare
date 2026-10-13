# RC-plan-panel-extra — Recurring plan — Add an extra visit

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | Recurring care |
| Lifecycle stage | Hired / before shift |
| Route | /family/care/1 |
| Selected tab | Inline panel |
| Panel / disclosure | extra |
| Capture status | Captured |
| UX review | Audited — grouped evidence |
| UX findings | Delivery D05 and recurring RC-UX-07/08: Label this as an extra dated visit outside the weekly schedule; retain date/start/duration/note and existing eligibility/acceptance/payment behavior. Preview resulting visit and keep form actions clear of generic sticky management. |
| Redesign decision | Proposed; not implemented |
| Owner | Unassigned |
| Priority | P2 |

These screenshots show the current application with synthetic audit data. This is capture and source-audit evidence; no UI redesign is implemented. Remote map/payment widgets were blocked in this local capture session, so their current fallback states are preserved. Google Fonts were allowed.

## Additional capture context

```json
{
  "focusSelector": "h3:has-text(\"Ask for one extra visit\")",
  "captureVersion": 4,
  "prepared": true
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 3297 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8027/family/care/1

[Focused view](<../screenshots/RC-plan-panel-extra-desktop-focus.png>) · [First screenful](<../screenshots/RC-plan-panel-extra-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/RC-plan-panel-extra-desktop-full.png>) · [Raw visible text](<../evidence/RC-plan-panel-extra-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/RC-plan-panel-extra-desktop.json>)

![Desktop screenshot of Recurring plan — Add an extra visit](<../screenshots/RC-plan-panel-extra-desktop-focus.png>)

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
Back to your care
Recurring care with Sofia Martinez

Mon, Wed, Fri · 10:00 AM–12:00 PM

Care story
View past visits
Message caregiver
Recurring care is active
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

NEXT VISIT

Wednesday, September 9

10:00 AM to 12:00 PM

Who is coming

Sofia Martinez

For Eleanor Bennett

Where

142 Maple Grove Lane

Raleigh, NC 27601

Payment

Payment confirmed

No action needed.

View visit details
Skip this visit
Later visits

After your next visit, these are the visits already on the calendar.

Monday, September 7

7:56 AM to 9:56 AM with Sofia Martinez

Payment confirmed

View details
Manage recurring care

Choose one change. We will explain what happens before you confirm.

Add an extra visit
Change future schedule
Pause recurring care
End recurring care
Ask for one extra visit

Sofia Martinez must accept before this becomes a booked visit.

Day
Start time
How long
1 hour
1 hr 30 min
2 hours
2 hr 30 min
3 hours
4 hours
5 hours
6 hours
7 hours
8 hours
Optional note
Send request
Cancel
Care details and instructions

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
| H1 | Recurring care with Sofia Martinez |
| H2 | About Ellie |
| H3 | At a glance |
| H3 | Communication |
| H3 | Support and mobility |
| H3 | Routine and comfort |
| H3 | Important for safety |
| H3 | What the family is looking for |
| H3 | Contacts and care coordination |
| H2 | Wednesday, September 9 |
| H2 | Later visits |
| H2 | Manage recurring care |
| H3 | Ask for one extra visit |

### Every visible control and its current value

#### 1. MB<br>Maria Bennett<br>Family

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** MB<br>Maria Bennett<br>Family
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 2. Care story

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Care story
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 3. View past visits

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** View past visits
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 4. Message caregiver

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Message caregiver
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 5. Skip this visit

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Skip this visit
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 6. Add an extra visit

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Add an extra visit
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 7. Change future schedule

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Change future schedule
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 8. Pause recurring care

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Pause recurring care
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 9. End recurring care

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** End recurring care
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 10. Day

- **tag:** INPUT
- **type:** date
- **label:** Day
- **text:** (empty)
- **value:** 2026-09-09
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 11. Start time

- **tag:** INPUT
- **type:** time
- **label:** Start time
- **text:** (empty)
- **value:** 10:00
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 12. How long<br>1 hour<br>1 hr 30 min<br>2 hours<br>2 hr 30 min<br>3 hours<br>4 hours<br>5 hours<br>6 hours<br>7 hours<br>8 hours

- **tag:** SELECT
- **type:** select-one
- **label:** How long<br>1 hour<br>1 hr 30 min<br>2 hours<br>2 hr 30 min<br>3 hours<br>4 hours<br>5 hours<br>6 hours<br>7 hours<br>8 hours
- **text:** (empty)
- **value:** 120
- **disabled:** false
- **required:** false
- **placeholder:** null
- **Dropdown options:**
  - 1 hour — value: 60; selected: false
  - 1 hr 30 min — value: 90; selected: false
  - 2 hours — value: 120; selected: true
  - 2 hr 30 min — value: 150; selected: false
  - 3 hours — value: 180; selected: false
  - 4 hours — value: 240; selected: false
  - 5 hours — value: 300; selected: false
  - 6 hours — value: 360; selected: false
  - 7 hours — value: 420; selected: false
  - 8 hours — value: 480; selected: false

#### 13. Optional note

- **tag:** TEXTAREA
- **type:** textarea
- **label:** Optional note
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 14. Send request

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Send request
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 15. Cancel

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Cancel
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 16. Chat with LoLo Support

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
| Back to your care | http://127.0.0.1:8027/family/care |
| Care story | http://127.0.0.1:8027/family/care/journey/regular/1 |
| View past visits | http://127.0.0.1:8027/family/care/history?plan=1 |
| Message caregiver | http://127.0.0.1:8027/family/requests/16 |
| +1 919 555 0153 | tel:+1 919 555 0153 |
| daniel.find-care-audit@example.com | mailto:daniel.find-care-audit@example.com |
| View visit details | http://127.0.0.1:8027/family/requests/16 |
| View details | http://127.0.0.1:8027/family/requests/17 |
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
| Care details and instructions | Closed |

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
  "documentHeight": 3297,
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8027/family/care/1"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 4813 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8027/family/care/1

[Focused view](<../screenshots/RC-plan-panel-extra-mobile-focus.png>) · [First screenful](<../screenshots/RC-plan-panel-extra-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/RC-plan-panel-extra-mobile-full.png>) · [Raw visible text](<../evidence/RC-plan-panel-extra-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/RC-plan-panel-extra-mobile.json>)

![Mobile screenshot of Recurring plan — Add an extra visit](<../screenshots/RC-plan-panel-extra-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
Back to your care
Recurring care with Sofia Martinez

Mon, Wed, Fri · 10:00 AM–12:00 PM

Care story
View past visits
Message caregiver
Recurring care is active
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

NEXT VISIT

Wednesday, September 9

10:00 AM to 12:00 PM

Who is coming

Sofia Martinez

For Eleanor Bennett

Where

142 Maple Grove Lane

Raleigh, NC 27601

Payment

Payment confirmed

No action needed.

View visit details
Skip this visit
Later visits

After your next visit, these are the visits already on the calendar.

Monday, September 7

7:56 AM to 9:56 AM with Sofia Martinez

Payment confirmed

View details
Manage recurring care

Choose one change. We will explain what happens before you confirm.

Add an extra visit
Change future schedule
Pause recurring care
End recurring care
Ask for one extra visit

Sofia Martinez must accept before this becomes a booked visit.

Day
Start time
How long
1 hour
1 hr 30 min
2 hours
2 hr 30 min
3 hours
4 hours
5 hours
6 hours
7 hours
8 hours
Optional note
Send request
Cancel
Care details and instructions
Manage recurring care

© 2026 LoLo

Legal links
```

### Visible headings

| Level | Heading |
|---|---|
| H1 | Recurring care with Sofia Martinez |
| H2 | About Ellie |
| H3 | At a glance |
| H3 | Communication |
| H3 | Support and mobility |
| H3 | Routine and comfort |
| H3 | Important for safety |
| H3 | What the family is looking for |
| H3 | Contacts and care coordination |
| H2 | Wednesday, September 9 |
| H2 | Later visits |
| H2 | Manage recurring care |
| H3 | Ask for one extra visit |

### Every visible control and its current value

#### 1. Toggle navigation menu

- **tag:** BUTTON
- **type:** button
- **label:** Toggle navigation menu
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 2. Care story

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Care story
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 3. View past visits

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** View past visits
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 4. Message caregiver

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Message caregiver
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 5. Skip this visit

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Skip this visit
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 6. Add an extra visit

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Add an extra visit
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 7. Change future schedule

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Change future schedule
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 8. Pause recurring care

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Pause recurring care
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 9. End recurring care

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** End recurring care
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 10. Day

- **tag:** INPUT
- **type:** date
- **label:** Day
- **text:** (empty)
- **value:** 2026-09-09
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 11. Start time

- **tag:** INPUT
- **type:** time
- **label:** Start time
- **text:** (empty)
- **value:** 10:00
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 12. How long<br>1 hour<br>1 hr 30 min<br>2 hours<br>2 hr 30 min<br>3 hours<br>4 hours<br>5 hours<br>6 hours<br>7 hours<br>8 hours

- **tag:** SELECT
- **type:** select-one
- **label:** How long<br>1 hour<br>1 hr 30 min<br>2 hours<br>2 hr 30 min<br>3 hours<br>4 hours<br>5 hours<br>6 hours<br>7 hours<br>8 hours
- **text:** (empty)
- **value:** 120
- **disabled:** false
- **required:** false
- **placeholder:** null
- **Dropdown options:**
  - 1 hour — value: 60; selected: false
  - 1 hr 30 min — value: 90; selected: false
  - 2 hours — value: 120; selected: true
  - 2 hr 30 min — value: 150; selected: false
  - 3 hours — value: 180; selected: false
  - 4 hours — value: 240; selected: false
  - 5 hours — value: 300; selected: false
  - 6 hours — value: 360; selected: false
  - 7 hours — value: 420; selected: false
  - 8 hours — value: 480; selected: false

#### 13. Optional note

- **tag:** TEXTAREA
- **type:** textarea
- **label:** Optional note
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 14. Send request

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Send request
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 15. Cancel

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Cancel
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 16. Chat with LoLo Support

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
| Back to your care | http://127.0.0.1:8027/family/care |
| Care story | http://127.0.0.1:8027/family/care/journey/regular/1 |
| View past visits | http://127.0.0.1:8027/family/care/history?plan=1 |
| Message caregiver | http://127.0.0.1:8027/family/requests/16 |
| +1 919 555 0153 | tel:+1 919 555 0153 |
| daniel.find-care-audit@example.com | mailto:daniel.find-care-audit@example.com |
| View visit details | http://127.0.0.1:8027/family/requests/16 |
| View details | http://127.0.0.1:8027/family/requests/17 |
| Manage recurring care | #manage-regular-care |

### Disclosure states

| Disclosure | State |
|---|---|
| Care details and instructions | Closed |
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
  "documentHeight": 4813,
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8027/family/care/1"
}
```
