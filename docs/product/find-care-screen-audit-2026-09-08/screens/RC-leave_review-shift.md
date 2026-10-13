# RC-leave_review-shift — leave review — This visit

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | Recurring care |
| Lifecycle stage | Timesheet / review |
| Route | /family/requests/20?tab=shift |
| Selected tab | This visit |
| Preserved route key | shift |
| Panel / disclosure | default |
| Capture status | Captured |
| UX review | Implementation rationale; validation recorded separately |
| UX findings | The dated visit is the correct scope for care, hours, payment and feedback. |
| Redesign decision | The compact header retains exact identity and status. Relevant actions lead, with the existing detailed record, corrections, payment information and review fields still accessible. |
| Owner | Local care workspace implementation |
| Priority |  |

These screenshots show the current application with synthetic audit data. This is capture and source-audit evidence; the local UI redesign is implemented. Remote map/payment widgets were blocked in this local capture session, so their current fallback states are preserved. Brand fonts are served locally.

## Additional capture context

```json
{
  "captureVersion": 5,
  "capturedAt": "2026-09-08T15:30:11.936Z",
  "buildHash": "58c0b4adc3736b3717582dffe837c3b2284299b26b123064150359543361e636",
  "prepared": false,
  "displayTab": "This visit"
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 1635 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/20?tab=shift

[Focused view](<../screenshots/RC-leave_review-shift-desktop-focus.png>) · [First screenful](<../screenshots/RC-leave_review-shift-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/RC-leave_review-shift-desktop-full.png>) · [Raw visible text](<../evidence/RC-leave_review-shift-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/RC-leave_review-shift-desktop.json>)

![Desktop screenshot of leave review — This visit](<../screenshots/RC-leave_review-shift-desktop-focus.png>)

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
My care
/
Back to recurring care · Visits
/
Visit #12

ONE VISIT IN YOUR RECURRING CARE

Eleanor Bennett · Sat, Sep 5

Sep 05, 10:00 AM - 12:00 PM · Raleigh, NC

Caregiver: Sofia Martinez

Reviewed
Visit #12
Message caregiver
Get help
Care story
This visit
Care instructions
Help & changes

VISIT COMPLETE

This visit is complete.

Review the final record, caregiver feedback, and book again when you need the same help.

Changes and approvals here apply to this dated visit. Your recurring schedule has its own controls.

Leave a review

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
```

### Visible headings

| Level | Heading |
|---|---|
| H1 | Eleanor Bennett · Sat, Sep 5 |
| H2 | This visit is complete. |
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
- **current:** null
- **expanded:** false
- **pressed:** null
- **placeholder:** null

#### 2. Message caregiver

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Message caregiver
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 3. Get help

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Get help
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 4. This visit

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** This visit
- **value:** (empty)
- **disabled:** false
- **current:** page
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 5. Care instructions

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Care instructions
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 6. Help & changes

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Help & changes
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 7. Rate 1 out of 5

- **tag:** BUTTON
- **type:** button
- **label:** Rate 1 out of 5
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** false
- **placeholder:** null

#### 8. Rate 2 out of 5

- **tag:** BUTTON
- **type:** button
- **label:** Rate 2 out of 5
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** false
- **placeholder:** null

#### 9. Rate 3 out of 5

- **tag:** BUTTON
- **type:** button
- **label:** Rate 3 out of 5
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** false
- **placeholder:** null

#### 10. Rate 4 out of 5

- **tag:** BUTTON
- **type:** button
- **label:** Rate 4 out of 5
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** false
- **placeholder:** null

#### 11. Rate 5 out of 5

- **tag:** BUTTON
- **type:** button
- **label:** Rate 5 out of 5
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** false
- **placeholder:** null

#### 12. Review comment

- **tag:** TEXTAREA
- **type:** textarea
- **label:** Review comment
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 13. Submit review

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Submit review
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

### Every visible link

| Label | Destination |
|---|---|
| FAMILY WORKSPACE | http://127.0.0.1:8028/family/requests |
| Care | http://127.0.0.1:8028/family/requests |
| Caregivers | http://127.0.0.1:8028/caregivers/search |
| Messages | http://127.0.0.1:8028/messages |
| Request care | http://127.0.0.1:8028/family/requests/create |
| Notifications | http://127.0.0.1:8028/family/notifications |
| My care | http://127.0.0.1:8028/family/care |
| Back to recurring care · Visits | http://127.0.0.1:8028/family/care/1?tab=visits&visit\_filter=upcoming&visitsPage=1#care-visit-12 |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/1 |
| Leave a review | #family-review-form |
| Open map | https://www.google.com/maps/search/?api=1&query=142%20Maple%20Grove%20Lane%2C%20Apartment%202B%2C%20Raleigh%2C%20NC%2027601 |
| Terms of Service | http://127.0.0.1:8028/legal/platform-terms-of-service |
| Privacy Policy | http://127.0.0.1:8028/legal/privacy-policy |
| Payments & Cancellation | http://127.0.0.1:8028/legal/payment-terms-and-cancellation-policy |
| Caregiver Terms | http://127.0.0.1:8028/legal/caregiver-terms |
| Family Terms | http://127.0.0.1:8028/legal/client-and-family-terms |
| Safety Policy | http://127.0.0.1:8028/legal/acceptable-use-and-safety-policy |
| All Legal Documents | http://127.0.0.1:8028/legal |

### Disclosure states

| Disclosure | State |
|---|---|
| Detailed visit record | Closed |

### Visible images and loading state

| Alternative text | Source | Loaded |
|---|---|---|
| LoLo | http://127.0.0.1:8028/images/marketing/lolo/lolo-app-icon.svg | true |
| LoLo | http://127.0.0.1:8028/images/marketing/lolo/lolo-wordmark-evergreen.svg | true |

### Capture metadata

```json
{
  "width": 1440,
  "height": 1000,
  "documentWidth": 1440,
  "documentHeight": 1635,
  "fontFaces": [
    {
      "family": "Inter",
      "status": "loaded",
      "weight": "100 900",
      "style": "normal"
    },
    {
      "family": "Source Serif 4",
      "status": "loaded",
      "weight": "200 900",
      "style": "normal"
    }
  ],
  "bodyFont": "Inter, sans-serif",
  "workspace": {
    "heading": "Eleanor Bennett · Sat, Sep 5",
    "status": "Reviewed",
    "headerText": "My care\n/\nBack to recurring care · Visits\n/\nVisit #12\n\nONE VISIT IN YOUR RECURRING CARE\n\nEleanor Bennett · Sat, Sep 5\n\nSep 05, 10:00 AM - 12:00 PM · Raleigh, NC\n\nCaregiver: Sofia Martinez\n\nReviewed\nVisit #12\nMessage caregiver\nGet help\nCare story",
    "primaryTitle": "This visit is complete.",
    "selectedTabs": [
      "This visit"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/20?tab=shift"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 2343 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/20?tab=shift

[Focused view](<../screenshots/RC-leave_review-shift-mobile-focus.png>) · [First screenful](<../screenshots/RC-leave_review-shift-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/RC-leave_review-shift-mobile-full.png>) · [Raw visible text](<../evidence/RC-leave_review-shift-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/RC-leave_review-shift-mobile.json>)

![Mobile screenshot of leave review — This visit](<../screenshots/RC-leave_review-shift-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
My care
/
Back to recurring care · Visits
/
Visit #12

ONE VISIT IN YOUR RECURRING CARE

Eleanor Bennett · Sat, Sep 5

Sep 05, 10:00 AM - 12:00 PM · Raleigh, NC

Caregiver: Sofia Martinez

Reviewed
Visit #12
Message caregiver
Get help
Care story
This visit
Care instructions
Help & changes

VISIT COMPLETE

This visit is complete.

Review the final record, caregiver feedback, and book again when you need the same help.

Changes and approvals here apply to this dated visit. Your recurring schedule has its own controls.

Leave a review

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
| H1 | Eleanor Bennett · Sat, Sep 5 |
| H2 | This visit is complete. |
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
- **current:** null
- **expanded:** false
- **pressed:** null
- **placeholder:** null

#### 2. Message caregiver

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Message caregiver
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 3. Get help

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Get help
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 4. This visit

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** This visit
- **value:** (empty)
- **disabled:** false
- **current:** page
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 5. Care instructions

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Care instructions
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 6. Help & changes

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Help & changes
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 7. Rate 1 out of 5

- **tag:** BUTTON
- **type:** button
- **label:** Rate 1 out of 5
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** false
- **placeholder:** null

#### 8. Rate 2 out of 5

- **tag:** BUTTON
- **type:** button
- **label:** Rate 2 out of 5
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** false
- **placeholder:** null

#### 9. Rate 3 out of 5

- **tag:** BUTTON
- **type:** button
- **label:** Rate 3 out of 5
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** false
- **placeholder:** null

#### 10. Rate 4 out of 5

- **tag:** BUTTON
- **type:** button
- **label:** Rate 4 out of 5
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** false
- **placeholder:** null

#### 11. Rate 5 out of 5

- **tag:** BUTTON
- **type:** button
- **label:** Rate 5 out of 5
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** false
- **placeholder:** null

#### 12. Review comment

- **tag:** TEXTAREA
- **type:** textarea
- **label:** Review comment
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 13. Submit review

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Submit review
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

### Every visible link

| Label | Destination |
|---|---|
| Family workspace | http://127.0.0.1:8028/family/requests |
| Notifications | http://127.0.0.1:8028/family/notifications |
| My care | http://127.0.0.1:8028/family/care |
| Back to recurring care · Visits | http://127.0.0.1:8028/family/care/1?tab=visits&visit\_filter=upcoming&visitsPage=1#care-visit-12 |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/1 |
| Leave a review | #family-review-form |
| Open map | https://www.google.com/maps/search/?api=1&query=142%20Maple%20Grove%20Lane%2C%20Apartment%202B%2C%20Raleigh%2C%20NC%2027601 |

### Disclosure states

| Disclosure | State |
|---|---|
| Detailed visit record | Closed |
| Legal links | Closed |

### Visible images and loading state

| Alternative text | Source | Loaded |
|---|---|---|
| LoLo | http://127.0.0.1:8028/images/marketing/lolo/lolo-app-icon.svg | true |
| LoLo | http://127.0.0.1:8028/images/marketing/lolo/lolo-wordmark-evergreen.svg | true |

### Capture metadata

```json
{
  "width": 390,
  "height": 844,
  "documentWidth": 390,
  "documentHeight": 2343,
  "fontFaces": [
    {
      "family": "Inter",
      "status": "loaded",
      "weight": "100 900",
      "style": "normal"
    },
    {
      "family": "Source Serif 4",
      "status": "loaded",
      "weight": "200 900",
      "style": "normal"
    }
  ],
  "bodyFont": "Inter, sans-serif",
  "workspace": {
    "heading": "Eleanor Bennett · Sat, Sep 5",
    "status": "Reviewed",
    "headerText": "My care\n/\nBack to recurring care · Visits\n/\nVisit #12\n\nONE VISIT IN YOUR RECURRING CARE\n\nEleanor Bennett · Sat, Sep 5\n\nSep 05, 10:00 AM - 12:00 PM · Raleigh, NC\n\nCaregiver: Sofia Martinez\n\nReviewed\nVisit #12\nMessage caregiver\nGet help\nCare story",
    "primaryTitle": "This visit is complete.",
    "selectedTabs": [
      "This visit"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/20?tab=shift"
}
```
