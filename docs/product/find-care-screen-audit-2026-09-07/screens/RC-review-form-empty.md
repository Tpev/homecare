# RC-review-form-empty — Leave a caregiver review — empty form

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | Recurring care |
| Lifecycle stage | Timesheet / review |
| Route | /family/requests/20?tab=shift |
| Selected tab | shift |
| Panel / disclosure | review form |
| Capture status | Captured |
| UX review | Audited — grouped evidence |
| UX findings | Delivery D11: Name caregiver and visit/date above the five-star review and full comment; preserve current validation/limits and separate rating from hours/payment. Show the unselected rating state and plain guidance without implying a saved review. Return to the next visit/plan after submission without ending the arrangement. |
| Redesign decision | Proposed; not implemented |
| Owner | Unassigned |
| Priority | P2 |

These screenshots show the current application with synthetic audit data. This is capture and source-audit evidence; no UI redesign is implemented. Remote map/payment widgets were blocked in this local capture session, so their current fallback states are preserved. Google Fonts were allowed.

## Additional capture context

```json
{
  "focusSelector": "h2:has-text(\"Leave a caregiver review\")",
  "captureVersion": 4,
  "prepared": true
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 3028 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8027/family/requests/20?tab=shift

[Focused view](<../screenshots/RC-review-form-empty-desktop-focus.png>) · [First screenful](<../screenshots/RC-review-form-empty-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/RC-review-form-empty-desktop-full.png>) · [Raw visible text](<../evidence/RC-review-form-empty-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/RC-review-form-empty-desktop.json>)

![Desktop screenshot of Leave a caregiver review — empty form](<../screenshots/RC-review-form-empty-desktop-focus.png>)

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
Eleanor Bennett · Sat, Sep 5
FILLED
VISIT Reviewed
Payment Captured

One-time care - Sep 05, 10:00 AM - 12:00 PM - Raleigh, NC

Visit #12

Care story

VISIT COMPLETE

This visit is complete.

Review the final record, caregiver feedback, and book again when you need the same help.

Visit

Time, location, payment

Caregiver

Profile, chat

Help

Change or get help

Care details

Needs, address, tasks

VISIT RECORD

Final details

Keep the final schedule, time, payment, notes, and feedback in one record.

Reviewed

SCHEDULE

Sep 05, 2026

10:00 AM - 12:00 PM

CAREGIVER

Sofia Martinez

Checked in Sep 05, 10:00 AM

CARE LOCATION

142 Maple Grove Lane, Apartment 2B, Raleigh, NC 27601

Open map
Eleanor Bennett - Mother

VISIT RECEIPT

WORKED TIME

2h 00m

PAYMENT

$62.00

Captured

TIMESHEET

Confirmed

Sep 05, 12:35

Detailed visit record
Leave a caregiver review

Tap stars to rate this visit.

Rating

No rating selected yet.

Review comment
Submit review

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
| H1 | Eleanor Bennett · Sat, Sep 5 |
| H2 | Final details |
| H2 | Leave a caregiver review |

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

#### 3. Caregiver<br><br>Profile, chat

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Caregiver<br><br>Profile, chat
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 4. Help<br><br>Change or get help

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Help<br><br>Change or get help
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

#### 6. Rate 1 out of 5

- **tag:** BUTTON
- **type:** button
- **label:** Rate 1 out of 5
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 7. Rate 2 out of 5

- **tag:** BUTTON
- **type:** button
- **label:** Rate 2 out of 5
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 8. Rate 3 out of 5

- **tag:** BUTTON
- **type:** button
- **label:** Rate 3 out of 5
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 9. Rate 4 out of 5

- **tag:** BUTTON
- **type:** button
- **label:** Rate 4 out of 5
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 10. Rate 5 out of 5

- **tag:** BUTTON
- **type:** button
- **label:** Rate 5 out of 5
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 11. Review comment

- **tag:** TEXTAREA
- **type:** textarea
- **label:** Review comment
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 12. Submit review

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Submit review
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 13. Chat with LoLo Support

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
  "documentHeight": 3028,
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8027/family/requests/20?tab=shift"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 4228 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8027/family/requests/20?tab=shift

[Focused view](<../screenshots/RC-review-form-empty-mobile-focus.png>) · [First screenful](<../screenshots/RC-review-form-empty-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/RC-review-form-empty-mobile-full.png>) · [Raw visible text](<../evidence/RC-review-form-empty-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/RC-review-form-empty-mobile.json>)

![Mobile screenshot of Leave a caregiver review — empty form](<../screenshots/RC-review-form-empty-mobile-focus.png>)

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
Eleanor Bennett · Sat, Sep 5
FILLED
VISIT Reviewed
Payment Captured

One-time care - Sep 05, 10:00 AM - 12:00 PM - Raleigh, NC

Visit #12

Care story

VISIT COMPLETE

This visit is complete.

Review the final record, caregiver feedback, and book again when you need the same help.

Visit

Time, location, payment

Caregiver

Profile, chat

Help

Change or get help

Care details

Needs, address, tasks

VISIT RECORD

Final details

Keep the final schedule, time, payment, notes, and feedback in one record.

Reviewed

SCHEDULE

Sep 05, 2026

10:00 AM - 12:00 PM

CAREGIVER

Sofia Martinez

Checked in Sep 05, 10:00 AM

CARE LOCATION

142 Maple Grove Lane, Apartment 2B, Raleigh, NC 27601

Open map
Eleanor Bennett - Mother

VISIT RECEIPT

WORKED TIME

2h 00m

PAYMENT

$62.00

Captured

TIMESHEET

Confirmed

Sep 05, 12:35

Detailed visit record
Leave a caregiver review

Tap stars to rate this visit.

Rating

No rating selected yet.

Review comment
Submit review

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
| H1 | Eleanor Bennett · Sat, Sep 5 |
| H2 | Final details |
| H2 | Leave a caregiver review |

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

#### 3. Caregiver<br><br>Profile, chat

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Caregiver<br><br>Profile, chat
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 4. Help<br><br>Change or get help

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Help<br><br>Change or get help
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

#### 6. Rate 1 out of 5

- **tag:** BUTTON
- **type:** button
- **label:** Rate 1 out of 5
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 7. Rate 2 out of 5

- **tag:** BUTTON
- **type:** button
- **label:** Rate 2 out of 5
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 8. Rate 3 out of 5

- **tag:** BUTTON
- **type:** button
- **label:** Rate 3 out of 5
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 9. Rate 4 out of 5

- **tag:** BUTTON
- **type:** button
- **label:** Rate 4 out of 5
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 10. Rate 5 out of 5

- **tag:** BUTTON
- **type:** button
- **label:** Rate 5 out of 5
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 11. Review comment

- **tag:** TEXTAREA
- **type:** textarea
- **label:** Review comment
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 12. Submit review

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Submit review
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 13. Chat with LoLo Support

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
  "documentHeight": 4228,
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8027/family/requests/20?tab=shift"
}
```
