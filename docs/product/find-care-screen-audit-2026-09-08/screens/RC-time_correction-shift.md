# RC-time_correction-shift — time correction — This visit

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | Recurring care |
| Lifecycle stage | Timesheet / review |
| Route | /family/requests/38?tab=shift |
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
  "capturedAt": "2026-09-08T15:41:13.871Z",
  "buildHash": "58c0b4adc3736b3717582dffe837c3b2284299b26b123064150359543361e636",
  "prepared": false,
  "displayTab": "This visit"
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 2153 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/38?tab=shift

[Focused view](<../screenshots/RC-time_correction-shift-desktop-focus.png>) · [First screenful](<../screenshots/RC-time_correction-shift-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/RC-time_correction-shift-desktop-full.png>) · [Raw visible text](<../evidence/RC-time_correction-shift-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/RC-time_correction-shift-desktop.json>)

![Desktop screenshot of time correction — This visit](<../screenshots/RC-time_correction-shift-desktop-focus.png>)

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
Visit #23

ONE VISIT IN YOUR RECURRING CARE

Eleanor Bennett · Mon, Sep 7

Sep 07, 2:51 AM - 4:51 AM · Raleigh, NC

Caregiver: Sofia Martinez

Completed
Visit #23
Message caregiver
Get help
Care story
This visit
Care instructions
Help & changes

REVIEW HOURS

Review the updated visit hours

A time correction is in progress. Its review and payment status are shown below.

Changes and approvals here apply to this dated visit. Your recurring schedule has its own controls.

VISIT RECORD

Final details

Keep the final schedule, time, payment, notes, and feedback in one record.

Completed

TIME CORRECTION · VISIT #23

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

Approve 1 hr 45 min and pay $54.25
Ask Sofia to change this  Send change request
Get help from LoLo

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
Location map
Payment status: Authorized - Authorized $62.00
Get support

WORKED TIME

2h 00m

ESTIMATED VISIT TOTAL

$62.00

$30.00/hr care + processing fee

TIMESHEET

Awaiting your confirmation

Submitted Sep 07, 04:56

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
```

### Visible headings

| Level | Heading |
|---|---|
| H1 | Eleanor Bennett · Mon, Sep 7 |
| H2 | Review the updated visit hours |
| H2 | Final details |
| H3 | Sofia asked you to review visit time |

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

#### 7. Approve 1 hr 45 min and pay $54.25

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Approve 1 hr 45 min and pay $54.25
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 8. Ask Sofia to change this

- **tag:** TEXTAREA
- **type:** textarea
- **label:** Ask Sofia to change this
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** Explain what time or detail needs to change.

#### 9. Send change request

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Send change request
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 10. Get help from LoLo

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Get help from LoLo
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 11. Get support

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Get support
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
| Back to recurring care · Visits | http://127.0.0.1:8028/family/care/7?tab=visits&visit\_filter=upcoming&visitsPage=1#care-visit-23 |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/7 |
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
| Location map | Closed |
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
  "documentHeight": 2153,
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
    "heading": "Eleanor Bennett · Mon, Sep 7",
    "status": "Completed",
    "headerText": "My care\n/\nBack to recurring care · Visits\n/\nVisit #23\n\nONE VISIT IN YOUR RECURRING CARE\n\nEleanor Bennett · Mon, Sep 7\n\nSep 07, 2:51 AM - 4:51 AM · Raleigh, NC\n\nCaregiver: Sofia Martinez\n\nCompleted\nVisit #23\nMessage caregiver\nGet help\nCare story",
    "primaryTitle": "Review the updated visit hours",
    "selectedTabs": [
      "This visit"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/38?tab=shift"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 3197 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/38?tab=shift

[Focused view](<../screenshots/RC-time_correction-shift-mobile-focus.png>) · [First screenful](<../screenshots/RC-time_correction-shift-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/RC-time_correction-shift-mobile-full.png>) · [Raw visible text](<../evidence/RC-time_correction-shift-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/RC-time_correction-shift-mobile.json>)

![Mobile screenshot of time correction — This visit](<../screenshots/RC-time_correction-shift-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
My care
/
Back to recurring care · Visits
/
Visit #23

ONE VISIT IN YOUR RECURRING CARE

Eleanor Bennett · Mon, Sep 7

Sep 07, 2:51 AM - 4:51 AM · Raleigh, NC

Caregiver: Sofia Martinez

Completed
Visit #23
Message caregiver
Get help
Care story
This visit
Care instructions
Help & changes

REVIEW HOURS

Review the updated visit hours

A time correction is in progress. Its review and payment status are shown below.

Changes and approvals here apply to this dated visit. Your recurring schedule has its own controls.

VISIT RECORD

Final details

Keep the final schedule, time, payment, notes, and feedback in one record.

Completed

TIME CORRECTION · VISIT #23

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

Approve 1 hr 45 min and pay $54.25
Ask Sofia to change this  Send change request
Get help from LoLo

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
Location map
Payment status: Authorized - Authorized $62.00
Get support

WORKED TIME

2h 00m

ESTIMATED VISIT TOTAL

$62.00

$30.00/hr care + processing fee

TIMESHEET

Awaiting your confirmation

Submitted Sep 07, 04:56

Detailed visit record

© 2026 LoLo

Legal links
```

### Visible headings

| Level | Heading |
|---|---|
| H1 | Eleanor Bennett · Mon, Sep 7 |
| H2 | Review the updated visit hours |
| H2 | Final details |
| H3 | Sofia asked you to review visit time |

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

#### 7. Approve 1 hr 45 min and pay $54.25

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Approve 1 hr 45 min and pay $54.25
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 8. Ask Sofia to change this

- **tag:** TEXTAREA
- **type:** textarea
- **label:** Ask Sofia to change this
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** Explain what time or detail needs to change.

#### 9. Send change request

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Send change request
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 10. Get help from LoLo

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Get help from LoLo
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 11. Get support

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Get support
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
| Back to recurring care · Visits | http://127.0.0.1:8028/family/care/7?tab=visits&visit\_filter=upcoming&visitsPage=1#care-visit-23 |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/7 |
| Open map | https://www.google.com/maps/search/?api=1&query=142%20Maple%20Grove%20Lane%2C%20Apartment%202B%2C%20Raleigh%2C%20NC%2027601 |

### Disclosure states

| Disclosure | State |
|---|---|
| Location map | Closed |
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
  "documentHeight": 3197,
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
    "heading": "Eleanor Bennett · Mon, Sep 7",
    "status": "Completed",
    "headerText": "My care\n/\nBack to recurring care · Visits\n/\nVisit #23\n\nONE VISIT IN YOUR RECURRING CARE\n\nEleanor Bennett · Mon, Sep 7\n\nSep 07, 2:51 AM - 4:51 AM · Raleigh, NC\n\nCaregiver: Sofia Martinez\n\nCompleted\nVisit #23\nMessage caregiver\nGet help\nCare story",
    "primaryTitle": "Review the updated visit hours",
    "selectedTabs": [
      "This visit"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/38?tab=shift"
}
```
