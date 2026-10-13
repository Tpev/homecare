# OT-leave_review-shift — leave review — Overview

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | One-time care |
| Lifecycle stage | Timesheet / review |
| Route | /family/requests/9?tab=shift |
| Selected tab | Overview |
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
  "capturedAt": "2026-09-08T15:28:26.387Z",
  "buildHash": "58c0b4adc3736b3717582dffe837c3b2284299b26b123064150359543361e636",
  "prepared": false,
  "displayTab": "Overview"
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 1604 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/9?tab=shift

[Focused view](<../screenshots/OT-leave_review-shift-desktop-focus.png>) · [First screenful](<../screenshots/OT-leave_review-shift-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/OT-leave_review-shift-desktop-full.png>) · [Raw visible text](<../evidence/OT-leave_review-shift-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/OT-leave_review-shift-desktop.json>)

![Desktop screenshot of leave review — Overview](<../screenshots/OT-leave_review-shift-desktop-focus.png>)

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
One-time care

ONE-TIME CARE

Eleanor Bennett · Sat, Sep 5

Sep 05, 10:00 AM - 12:00 PM · Raleigh, NC

Caregiver: Sofia Martinez

Reviewed
Visit #5
Message caregiver
Get help
Care story
Overview
Caregivers
Care details
Help

VISIT COMPLETE

This visit is complete.

Review the final record, caregiver feedback, and book again when you need the same help.

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

#### 4. Overview

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Overview
- **value:** (empty)
- **disabled:** false
- **current:** page
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 5. Caregivers

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Caregivers
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 6. Care details

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Care details
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 7. Help

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Help
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 8. Rate 1 out of 5

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

#### 9. Rate 2 out of 5

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

#### 10. Rate 3 out of 5

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

#### 11. Rate 4 out of 5

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

#### 12. Rate 5 out of 5

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

#### 13. Review comment

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

#### 14. Submit review

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
| Care story | http://127.0.0.1:8028/family/care/journey/request/9 |
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
  "documentHeight": 1604,
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
    "headerText": "My care\n/\nOne-time care\n\nONE-TIME CARE\n\nEleanor Bennett · Sat, Sep 5\n\nSep 05, 10:00 AM - 12:00 PM · Raleigh, NC\n\nCaregiver: Sofia Martinez\n\nReviewed\nVisit #5\nMessage caregiver\nGet help\nCare story",
    "primaryTitle": "This visit is complete.",
    "selectedTabs": [
      "Overview"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/9?tab=shift"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 2266 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/9?tab=shift

[Focused view](<../screenshots/OT-leave_review-shift-mobile-focus.png>) · [First screenful](<../screenshots/OT-leave_review-shift-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/OT-leave_review-shift-mobile-full.png>) · [Raw visible text](<../evidence/OT-leave_review-shift-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/OT-leave_review-shift-mobile.json>)

![Mobile screenshot of leave review — Overview](<../screenshots/OT-leave_review-shift-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
My care
/
One-time care

ONE-TIME CARE

Eleanor Bennett · Sat, Sep 5

Sep 05, 10:00 AM - 12:00 PM · Raleigh, NC

Caregiver: Sofia Martinez

Reviewed
Visit #5
Message caregiver
Get help
Care story
Overview
Caregivers
Care details
Help

VISIT COMPLETE

This visit is complete.

Review the final record, caregiver feedback, and book again when you need the same help.

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

#### 4. Overview

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Overview
- **value:** (empty)
- **disabled:** false
- **current:** page
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 5. Caregivers

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Caregivers
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 6. Care details

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Care details
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 7. Help

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Help
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 8. Rate 1 out of 5

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

#### 9. Rate 2 out of 5

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

#### 10. Rate 3 out of 5

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

#### 11. Rate 4 out of 5

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

#### 12. Rate 5 out of 5

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

#### 13. Review comment

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

#### 14. Submit review

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
| Care story | http://127.0.0.1:8028/family/care/journey/request/9 |
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
  "documentHeight": 2266,
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
    "headerText": "My care\n/\nOne-time care\n\nONE-TIME CARE\n\nEleanor Bennett · Sat, Sep 5\n\nSep 05, 10:00 AM - 12:00 PM · Raleigh, NC\n\nCaregiver: Sofia Martinez\n\nReviewed\nVisit #5\nMessage caregiver\nGet help\nCare story",
    "primaryTitle": "This visit is complete.",
    "selectedTabs": [
      "Overview"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/9?tab=shift"
}
```
