# OT-after_shift-shift — after shift — Overview

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | One-time care |
| Lifecycle stage | After shift |
| Route | /family/requests/7?tab=shift |
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
  "capturedAt": "2026-09-08T15:28:03.635Z",
  "buildHash": "58c0b4adc3736b3717582dffe837c3b2284299b26b123064150359543361e636",
  "prepared": false,
  "displayTab": "Overview"
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 1235 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/7?tab=shift

[Focused view](<../screenshots/OT-after_shift-shift-desktop-focus.png>) · [First screenful](<../screenshots/OT-after_shift-shift-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/OT-after_shift-shift-desktop-full.png>) · [Raw visible text](<../evidence/OT-after_shift-shift-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/OT-after_shift-shift-desktop.json>)

![Desktop screenshot of after shift — Overview](<../screenshots/OT-after_shift-shift-desktop-focus.png>)

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

Eleanor Bennett · Mon, Sep 7

Sep 07, 5:51 AM - 7:51 AM · Raleigh, NC

Caregiver: Sofia Martinez

Completed
Visit #3
Message caregiver
Get help
Care story
Overview
Caregivers
Care details
Help

REVIEW HOURS

Caregiver hours need your review.

Check the worked time and payment amount. Approving will capture payment and move payout forward.

2h 00m · $62.00

Review hours & payment

VISIT DETAILS

Visit details before approval

Confirm schedule, caregiver, and location here. The hours and payment decision stay above.

Completed

SCHEDULE

Sep 07, 2026

5:51 AM - 7:51 AM

CAREGIVER

Sofia Martinez

Checked in Sep 07, 5:51 AM

CARE LOCATION

142 Maple Grove Lane, Apartment 2B, Raleigh, NC 27601

Open map
Eleanor Bennett - Mother
Location map
Question hours
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
| H2 | Caregiver hours need your review. |
| H2 | Visit details before approval |

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

#### 8. Review hours & payment

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Review hours & payment
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 9. Question hours

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Question hours
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
| Care story | http://127.0.0.1:8028/family/care/journey/request/7 |
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
  "documentHeight": 1235,
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
    "headerText": "My care\n/\nOne-time care\n\nONE-TIME CARE\n\nEleanor Bennett · Mon, Sep 7\n\nSep 07, 5:51 AM - 7:51 AM · Raleigh, NC\n\nCaregiver: Sofia Martinez\n\nCompleted\nVisit #3\nMessage caregiver\nGet help\nCare story",
    "primaryTitle": "Caregiver hours need your review.",
    "selectedTabs": [
      "Overview"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/7?tab=shift"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 1764 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/7?tab=shift

[Focused view](<../screenshots/OT-after_shift-shift-mobile-focus.png>) · [First screenful](<../screenshots/OT-after_shift-shift-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/OT-after_shift-shift-mobile-full.png>) · [Raw visible text](<../evidence/OT-after_shift-shift-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/OT-after_shift-shift-mobile.json>)

![Mobile screenshot of after shift — Overview](<../screenshots/OT-after_shift-shift-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
My care
/
One-time care

ONE-TIME CARE

Eleanor Bennett · Mon, Sep 7

Sep 07, 5:51 AM - 7:51 AM · Raleigh, NC

Caregiver: Sofia Martinez

Completed
Visit #3
Message caregiver
Get help
Care story
Overview
Caregivers
Care details
Help

REVIEW HOURS

Caregiver hours need your review.

Check the worked time and payment amount. Approving will capture payment and move payout forward.

2h 00m · $62.00

Review hours & payment

VISIT DETAILS

Visit details before approval

Confirm schedule, caregiver, and location here. The hours and payment decision stay above.

Completed

SCHEDULE

Sep 07, 2026

5:51 AM - 7:51 AM

CAREGIVER

Sofia Martinez

Checked in Sep 07, 5:51 AM

CARE LOCATION

142 Maple Grove Lane, Apartment 2B, Raleigh, NC 27601

Open map
Eleanor Bennett - Mother
Location map
Question hours
Detailed visit record
Review hours & payment

© 2026 LoLo

Legal links
```

### Visible headings

| Level | Heading |
|---|---|
| H1 | Eleanor Bennett · Mon, Sep 7 |
| H2 | Caregiver hours need your review. |
| H2 | Visit details before approval |

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

#### 8. Review hours & payment

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Review hours & payment
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 9. Question hours

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Question hours
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 10. Review hours & payment

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Review hours & payment
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
| Care story | http://127.0.0.1:8028/family/care/journey/request/7 |
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
  "documentHeight": 1764,
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
    "headerText": "My care\n/\nOne-time care\n\nONE-TIME CARE\n\nEleanor Bennett · Mon, Sep 7\n\nSep 07, 5:51 AM - 7:51 AM · Raleigh, NC\n\nCaregiver: Sofia Martinez\n\nCompleted\nVisit #3\nMessage caregiver\nGet help\nCare story",
    "primaryTitle": "Caregiver hours need your review.",
    "selectedTabs": [
      "Overview"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/7?tab=shift"
}
```
