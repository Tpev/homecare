# RC-timesheet_review-shift-expanded — timesheet review — shift / all details open

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | Recurring care |
| Lifecycle stage | Timesheet / review |
| Route | /family/requests/19?tab=shift |
| Selected tab | shift |
| Panel / disclosure | expanded |
| Capture status | Captured |
| UX review | Audited — grouped evidence |
| UX findings | Delivery D02/D10: Lead with Review hours, then recorded check-in/out, breaks, payable duration and care/fee/total before explicit Approve hours and pay. Keep Question hours adjacent, all original/task/event evidence and payment recovery; do not invent a submission-wait stage. Recurring D04/RC-UX-04: Keep the parent-plan link and dated-visit identity explicit; completing/cancelling this visit does not by itself close the plan. Expanded-state requirement: preserve every revealed field, event, filter or form and its current value; collapsed replacements must show meaningful populated summaries and reopen reliably. |
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

Viewport: **1440 × 1000 CSS px**. Document height: 3493 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8027/family/requests/19?tab=shift

[Focused view](<../screenshots/RC-timesheet_review-shift-expanded-desktop-focus.png>) · [First screenful](<../screenshots/RC-timesheet_review-shift-expanded-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/RC-timesheet_review-shift-expanded-desktop-full.png>) · [Raw visible text](<../evidence/RC-timesheet_review-shift-expanded-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/RC-timesheet_review-shift-expanded-desktop.json>)

![Desktop screenshot of timesheet review — shift / all details open](<../screenshots/RC-timesheet_review-shift-expanded-desktop-focus.png>)

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

Visit #11

Care story

REVIEW HOURS

Caregiver hours need your review.

Check the worked time and payment amount. Approving will capture payment and move payout forward.

SUBMITTED HOURS

2h 00m

120 minutes

ESTIMATED PAYMENT

$62.00

Care $60.00 at $30.00/hr* + $2.00 processing fee

*A $1.00/hour processing fee is added.

Approve hours and pay $62.00
Question hours

Visit

Time, location, payment

Help

Change or get help

Caregiver

Profile, chat

Care details

Needs, address, tasks

VISIT DETAILS

Visit details before approval

Confirm schedule, caregiver, and location here. The hours and payment decision stay above.

Completed

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
Question hours
Detailed visit record

Caregiver check-in

Sep 07, 02:51

Caregiver check-out

Sep 07, 04:51

Family confirmation

Pending

Dispute

NONE

Expected 120 minutes - worked 120 minutes.

Care tasks

Companionship

Conversation, a crossword puzzle, and a short walk if Ellie feels up to it.

Meal preparation

Prepare soup and a sandwich for lunch; leave the kitchen tidy.

Light housekeeping

Wash dishes and keep the living room and hallway clear.

Visit timeline

Sep 07 04:56 - TIMESHEET SUBMITTED

Sep 07 04:51 - SHIFT COMPLETED BY CAREGIVER

Sep 07 02:51 - SHIFT STARTED BY CAREGIVER

Sep 06 02:51 - BOOKING HIRED

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
| H2 | Visit details before approval |

### Every visible control and its current value

#### 1. MB<br>Maria Bennett<br>Family

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** MB<br>Maria Bennett<br>Family
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 2. Approve hours and pay $62.00

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Approve hours and pay $62.00
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 3. Question hours

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Question hours
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 4. Visit<br><br>Time, location, payment

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Visit<br><br>Time, location, payment
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 5. Help<br><br>Change or get help

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Help<br><br>Change or get help
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 6. Caregiver<br><br>Profile, chat

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Caregiver<br><br>Profile, chat
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 7. Care details<br><br>Needs, address, tasks

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Care details<br><br>Needs, address, tasks
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 8. Question hours

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Question hours
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
| Care story | http://127.0.0.1:8027/family/care/journey/regular/1 |
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
| Detailed visit record | Open |

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
  "documentHeight": 3493,
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8027/family/requests/19?tab=shift"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 5001 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8027/family/requests/19?tab=shift

[Focused view](<../screenshots/RC-timesheet_review-shift-expanded-mobile-focus.png>) · [First screenful](<../screenshots/RC-timesheet_review-shift-expanded-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/RC-timesheet_review-shift-expanded-mobile-full.png>) · [Raw visible text](<../evidence/RC-timesheet_review-shift-expanded-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/RC-timesheet_review-shift-expanded-mobile.json>)

![Mobile screenshot of timesheet review — shift / all details open](<../screenshots/RC-timesheet_review-shift-expanded-mobile-focus.png>)

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

Visit #11

Care story

REVIEW HOURS

Caregiver hours need your review.

Check the worked time and payment amount. Approving will capture payment and move payout forward.

SUBMITTED HOURS

2h 00m

120 minutes

ESTIMATED PAYMENT

$62.00

Care $60.00 at $30.00/hr* + $2.00 processing fee

*A $1.00/hour processing fee is added.

Approve hours and pay $62.00
Question hours

Visit

Time, location, payment

Help

Change or get help

Caregiver

Profile, chat

Care details

Needs, address, tasks

VISIT DETAILS

Visit details before approval

Confirm schedule, caregiver, and location here. The hours and payment decision stay above.

Completed

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
Question hours
Detailed visit record

Caregiver check-in

Sep 07, 02:51

Caregiver check-out

Sep 07, 04:51

Family confirmation

Pending

Dispute

NONE

Expected 120 minutes - worked 120 minutes.

Care tasks

Companionship

Conversation, a crossword puzzle, and a short walk if Ellie feels up to it.

Meal preparation

Prepare soup and a sandwich for lunch; leave the kitchen tidy.

Light housekeeping

Wash dishes and keep the living room and hallway clear.

Visit timeline

Sep 07 04:56 - TIMESHEET SUBMITTED

Sep 07 04:51 - SHIFT COMPLETED BY CAREGIVER

Sep 07 02:51 - SHIFT STARTED BY CAREGIVER

Sep 06 02:51 - BOOKING HIRED

Approve hours and pay $62.00

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
| H2 | Visit details before approval |

### Every visible control and its current value

#### 1. Toggle navigation menu

- **tag:** BUTTON
- **type:** button
- **label:** Toggle navigation menu
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 2. Approve hours and pay $62.00

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Approve hours and pay $62.00
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 3. Question hours

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Question hours
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 4. Visit<br><br>Time, location, payment

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Visit<br><br>Time, location, payment
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 5. Help<br><br>Change or get help

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Help<br><br>Change or get help
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 6. Caregiver<br><br>Profile, chat

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Caregiver<br><br>Profile, chat
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 7. Care details<br><br>Needs, address, tasks

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Care details<br><br>Needs, address, tasks
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 8. Question hours

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Question hours
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 9. Approve hours and pay $62.00

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Approve hours and pay $62.00
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
| Care story | http://127.0.0.1:8027/family/care/journey/regular/1 |
| Open map | https://www.google.com/maps/search/?api=1&query=142%20Maple%20Grove%20Lane%2C%20Apartment%202B%2C%20Raleigh%2C%20NC%2027601 |

### Disclosure states

| Disclosure | State |
|---|---|
| Detailed visit record | Open |
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
  "documentHeight": 5001,
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8027/family/requests/19?tab=shift"
}
```
