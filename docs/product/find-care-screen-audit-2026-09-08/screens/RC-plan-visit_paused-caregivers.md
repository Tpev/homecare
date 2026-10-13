# RC-plan-visit_paused-caregivers — Recurring care visit_paused — Caregivers

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | Recurring care |
| Lifecycle stage | Hired / before shift |
| Route | /family/care/5?tab=caregivers |
| Selected tab | Caregivers |
| Preserved route key | caregivers |
| Panel / disclosure | default |
| Capture status | Captured |
| UX review | Implementation rationale; validation recorded separately |
| UX findings | The ongoing caregiver relationship needs a stable destination, separate from a dated visit. |
| Redesign decision | Caregiver identity, offer context and original recruitment/conversation access stay with the recurring care. |
| Owner | Local care workspace implementation |
| Priority |  |

These screenshots show the current application with synthetic audit data. This is capture and source-audit evidence; the local UI redesign is implemented. Remote map/payment widgets were blocked in this local capture session, so their current fallback states are preserved. Brand fonts are served locally.

## Additional capture context

```json
{
  "addedByRedesign": true,
  "captureVersion": 5,
  "capturedAt": "2026-09-08T15:49:16.109Z",
  "buildHash": "58c0b4adc3736b3717582dffe837c3b2284299b26b123064150359543361e636",
  "prepared": true,
  "displayTab": "Caregivers"
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 1526 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/care/5?tab=caregivers

[Focused view](<../screenshots/RC-plan-visit_paused-caregivers-desktop-focus.png>) · [First screenful](<../screenshots/RC-plan-visit_paused-caregivers-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/RC-plan-visit_paused-caregivers-desktop-full.png>) · [Raw visible text](<../evidence/RC-plan-visit_paused-caregivers-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/RC-plan-visit_paused-caregivers-desktop.json>)

![Desktop screenshot of Recurring care visit\_paused — Caregivers](<../screenshots/RC-plan-visit_paused-caregivers-desktop-focus.png>)

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
← Back to your care

Recurring care · Care #5

Recurring care for Eleanor Bennett

Sofia Martinez · Mon, Wed, Fri · 10:00 AM–12:00 PM

Recurring care is active
Caregiver & conversations
Get help
Overview
Caregivers
Visits
Care details

YOUR RECURRING CAREGIVER

S
Sofia Martinez

Raleigh, NC

7 years of experience
4.9 / 5 · 24 reviews
$30.00/hour agreed rate

I enjoy helping older adults keep a familiar routine, prepare meals, and stay connected.

Skills
Companionship
Meal preparation
Light housekeeping

Languages: English

View full profile
Caregiver’s response

Happy to continue supporting Ellie on this schedule.

Your offer message

We would love to keep the same friendly routine each week.

How this care started

Applications, invitations and conversations stay with your original care request.

Applications
1
Invitations
0
View applications & invitations
Care story
All past visits & payments

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
| H1 | Recurring care for Eleanor Bennett |
| H2 | Sofia Martinez |
| H3 | Skills |
| H3 | Caregiver’s response |
| H3 | Your offer message |
| H2 | How this care started |

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

#### 2. Get help

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Get help
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** false
- **pressed:** null
- **placeholder:** null

#### 3. Overview

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Overview
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 4. Caregivers

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Caregivers
- **value:** (empty)
- **disabled:** false
- **current:** page
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 5. Visits

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Visits
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

### Every visible link

| Label | Destination |
|---|---|
| FAMILY WORKSPACE | http://127.0.0.1:8028/family/requests |
| Care | http://127.0.0.1:8028/family/requests |
| Caregivers | http://127.0.0.1:8028/caregivers/search |
| Messages | http://127.0.0.1:8028/messages |
| Request care | http://127.0.0.1:8028/family/requests/create |
| Notifications | http://127.0.0.1:8028/family/notifications |
| ← Back to your care | http://127.0.0.1:8028/family/care |
| Caregiver & conversations | http://127.0.0.1:8028/family/requests/36?tab=applicants |
| View full profile | http://127.0.0.1:8028/caregivers/sofia-find-care-audit?careRequest=36 |
| View applications & invitations | http://127.0.0.1:8028/family/requests/36?tab=applicants |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/5 |
| All past visits & payments | http://127.0.0.1:8028/family/care/history?plan=5 |
| Terms of Service | http://127.0.0.1:8028/legal/platform-terms-of-service |
| Privacy Policy | http://127.0.0.1:8028/legal/privacy-policy |
| Payments & Cancellation | http://127.0.0.1:8028/legal/payment-terms-and-cancellation-policy |
| Caregiver Terms | http://127.0.0.1:8028/legal/caregiver-terms |
| Family Terms | http://127.0.0.1:8028/legal/client-and-family-terms |
| Safety Policy | http://127.0.0.1:8028/legal/acceptable-use-and-safety-policy |
| All Legal Documents | http://127.0.0.1:8028/legal |

### Disclosure states

No native disclosures recorded.

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
  "documentHeight": 1526,
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
    "heading": "Recurring care for Eleanor Bennett",
    "status": "",
    "headerText": "← Back to your care\n\nRecurring care · Care #5\n\nRecurring care for Eleanor Bennett\n\nSofia Martinez · Mon, Wed, Fri · 10:00 AM–12:00 PM\n\nRecurring care is active\nCaregiver & conversations\nGet help",
    "primaryTitle": "",
    "selectedTabs": [
      "Caregivers"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/care/5?tab=caregivers"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 1950 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/care/5?tab=caregivers

[Focused view](<../screenshots/RC-plan-visit_paused-caregivers-mobile-focus.png>) · [First screenful](<../screenshots/RC-plan-visit_paused-caregivers-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/RC-plan-visit_paused-caregivers-mobile-full.png>) · [Raw visible text](<../evidence/RC-plan-visit_paused-caregivers-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/RC-plan-visit_paused-caregivers-mobile.json>)

![Mobile screenshot of Recurring care visit\_paused — Caregivers](<../screenshots/RC-plan-visit_paused-caregivers-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
← Back to your care

Recurring care · Care #5

Recurring care for Eleanor Bennett

Sofia Martinez · Mon, Wed, Fri · 10:00 AM–12:00 PM

Recurring care is active
Caregiver & conversations
Get help
Overview
Caregivers
Visits
Care details

YOUR RECURRING CAREGIVER

S
Sofia Martinez

Raleigh, NC

7 years of experience
4.9 / 5 · 24 reviews
$30.00/hour agreed rate

I enjoy helping older adults keep a familiar routine, prepare meals, and stay connected.

Skills
Companionship
Meal preparation
Light housekeeping

Languages: English

View full profile
Caregiver’s response

Happy to continue supporting Ellie on this schedule.

Your offer message

We would love to keep the same friendly routine each week.

How this care started

Applications, invitations and conversations stay with your original care request.

Applications
1
Invitations
0
View applications & invitations
Care story
All past visits & payments

© 2026 LoLo

Legal links
```

### Visible headings

| Level | Heading |
|---|---|
| H1 | Recurring care for Eleanor Bennett |
| H2 | Sofia Martinez |
| H3 | Skills |
| H3 | Caregiver’s response |
| H3 | Your offer message |
| H2 | How this care started |

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

#### 2. Get help

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Get help
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** false
- **pressed:** null
- **placeholder:** null

#### 3. Overview

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Overview
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 4. Caregivers

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Caregivers
- **value:** (empty)
- **disabled:** false
- **current:** page
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 5. Visits

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Visits
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

### Every visible link

| Label | Destination |
|---|---|
| Family workspace | http://127.0.0.1:8028/family/requests |
| Notifications | http://127.0.0.1:8028/family/notifications |
| ← Back to your care | http://127.0.0.1:8028/family/care |
| Caregiver & conversations | http://127.0.0.1:8028/family/requests/36?tab=applicants |
| View full profile | http://127.0.0.1:8028/caregivers/sofia-find-care-audit?careRequest=36 |
| View applications & invitations | http://127.0.0.1:8028/family/requests/36?tab=applicants |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/5 |
| All past visits & payments | http://127.0.0.1:8028/family/care/history?plan=5 |

### Disclosure states

| Disclosure | State |
|---|---|
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
  "documentHeight": 1950,
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
    "heading": "Recurring care for Eleanor Bennett",
    "status": "",
    "headerText": "← Back to your care\n\nRecurring care · Care #5\n\nRecurring care for Eleanor Bennett\n\nSofia Martinez · Mon, Wed, Fri · 10:00 AM–12:00 PM\n\nRecurring care is active\nCaregiver & conversations\nGet help",
    "primaryTitle": "",
    "selectedTabs": [
      "Caregivers"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/care/5?tab=caregivers"
}
```
