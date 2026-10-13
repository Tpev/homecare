# RC-plan-paused-overview — Recurring care paused — Overview

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | Recurring care |
| Lifecycle stage | Hired / before shift |
| Route | /family/care/3?tab=overview |
| Selected tab | Overview |
| Preserved route key | overview |
| Panel / disclosure | default |
| Capture status | Captured |
| UX review | Implementation rationale; validation recorded separately |
| UX findings | The recurring arrangement needs one continuing home across individual visits. |
| Redesign decision | A current visit and outstanding decisions are visible with their own status. Optional reviews follow operational decisions; plan status does not replace visit or payment status. |
| Owner | Local care workspace implementation |
| Priority |  |

These screenshots show the current application with synthetic audit data. This is capture and source-audit evidence; the local UI redesign is implemented. Remote map/payment widgets were blocked in this local capture session, so their current fallback states are preserved. Brand fonts are served locally.

## Additional capture context

```json
{
  "addedByRedesign": true,
  "captureVersion": 5,
  "capturedAt": "2026-09-08T15:45:57.218Z",
  "buildHash": "58c0b4adc3736b3717582dffe837c3b2284299b26b123064150359543361e636",
  "prepared": true,
  "displayTab": "Overview"
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 1030 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/care/3?tab=overview

[Focused view](<../screenshots/RC-plan-paused-overview-desktop-focus.png>) · [First screenful](<../screenshots/RC-plan-paused-overview-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/RC-plan-paused-overview-desktop-full.png>) · [Raw visible text](<../evidence/RC-plan-paused-overview-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/RC-plan-paused-overview-desktop.json>)

![Desktop screenshot of Recurring care paused — Overview](<../screenshots/RC-plan-paused-overview-desktop-focus.png>)

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

Recurring care · Care #3

Recurring care for Eleanor Bennett

Sofia Martinez · Mon, Wed, Fri · 10:00 AM–12:00 PM

Care is paused
Caregiver & conversations
Get help
Overview
Caregivers
Visits
Care details
Recurring care is paused

Pause starts Sep 7, 2026. Scheduled to resume Sep 14, 2026. Any visits still booked are listed below.

Manage your schedule
Important for Ellie’s safety
Fall risk

Keep the hallway clear and remind Ellie to use the handrail.

Read full care details →
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
| H2 | Recurring care is paused |
| H2 | Important for Ellie’s safety |

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
- **current:** page
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
- **current:** null
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

#### 7. Manage your schedule

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Manage your schedule
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 8. Read full care details →

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Read full care details →
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
| Caregiver & conversations | http://127.0.0.1:8028/family/requests/25?tab=applicants |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/3 |
| All past visits & payments | http://127.0.0.1:8028/family/care/history?plan=3 |
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
  "documentHeight": 1030,
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
    "headerText": "← Back to your care\n\nRecurring care · Care #3\n\nRecurring care for Eleanor Bennett\n\nSofia Martinez · Mon, Wed, Fri · 10:00 AM–12:00 PM\n\nCare is paused\nCaregiver & conversations\nGet help",
    "primaryTitle": "",
    "selectedTabs": [
      "Overview"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/care/3?tab=overview"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 1168 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/care/3?tab=overview

[Focused view](<../screenshots/RC-plan-paused-overview-mobile-focus.png>) · [First screenful](<../screenshots/RC-plan-paused-overview-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/RC-plan-paused-overview-mobile-full.png>) · [Raw visible text](<../evidence/RC-plan-paused-overview-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/RC-plan-paused-overview-mobile.json>)

![Mobile screenshot of Recurring care paused — Overview](<../screenshots/RC-plan-paused-overview-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
← Back to your care

Recurring care · Care #3

Recurring care for Eleanor Bennett

Sofia Martinez · Mon, Wed, Fri · 10:00 AM–12:00 PM

Care is paused
Caregiver & conversations
Get help
Overview
Caregivers
Visits
Care details
Recurring care is paused

Pause starts Sep 7, 2026. Scheduled to resume Sep 14, 2026. Any visits still booked are listed below.

Manage your schedule
Important for Ellie’s safety
Fall risk

Keep the hallway clear and remind Ellie to use the handrail.

Read full care details →
Care story
All past visits & payments

© 2026 LoLo

Legal links
```

### Visible headings

| Level | Heading |
|---|---|
| H1 | Recurring care for Eleanor Bennett |
| H2 | Recurring care is paused |
| H2 | Important for Ellie’s safety |

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
- **current:** page
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
- **current:** null
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

#### 7. Manage your schedule

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Manage your schedule
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 8. Read full care details →

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Read full care details →
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
| Caregiver & conversations | http://127.0.0.1:8028/family/requests/25?tab=applicants |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/3 |
| All past visits & payments | http://127.0.0.1:8028/family/care/history?plan=3 |

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
  "documentHeight": 1168,
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
    "headerText": "← Back to your care\n\nRecurring care · Care #3\n\nRecurring care for Eleanor Bennett\n\nSofia Martinez · Mon, Wed, Fri · 10:00 AM–12:00 PM\n\nCare is paused\nCaregiver & conversations\nGet help",
    "primaryTitle": "",
    "selectedTabs": [
      "Overview"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/care/3?tab=overview"
}
```
