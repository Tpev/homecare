# RC-plan-countered-help — Recurring care countered — Help

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | Recurring care |
| Lifecycle stage | Waiting / inviting |
| Route | /family/care/8?tab=overview |
| Selected tab | Overview |
| Preserved route key | overview |
| Panel / disclosure | help expanded |
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
  "focusSelector": "#plan-help",
  "addedByRedesign": true,
  "captureVersion": 5,
  "capturedAt": "2026-09-08T15:47:41.660Z",
  "buildHash": "58c0b4adc3736b3717582dffe837c3b2284299b26b123064150359543361e636",
  "prepared": true,
  "displayTab": "Overview"
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 1474 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/care/8?tab=overview

[Focused view](<../screenshots/RC-plan-countered-help-desktop-focus.png>) · [First screenful](<../screenshots/RC-plan-countered-help-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/RC-plan-countered-help-desktop-full.png>) · [Raw visible text](<../evidence/RC-plan-countered-help-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/RC-plan-countered-help-desktop.json>)

![Desktop screenshot of Recurring care countered — Help](<../screenshots/RC-plan-countered-help-desktop-focus.png>)

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

Recurring care · Care #8

Recurring care for Eleanor Bennett

Sofia Martinez · Mon, Wed, Fri · 10:00 AM–12:00 PM

New schedule proposed
Caregiver & conversations
Get help
Overview
Caregivers
Visits
Care details
Help with your recurring care

For hours, payment, a change or a care concern, choose the affected visit. Its support history stays with that date.

Choose a visit
Chat with support
Contact LoLo Support
Close help

YOUR RESPONSE IS NEEDED

Sofia Martinez proposed a different schedule

Tue · 9:00 AM–11:00 AM; Thu · 1:00 PM–3:30 PM

I can help on Tuesday mornings and Thursday afternoons. Would those times work for Ellie?

Proposed dates · not booked yet

Tuesday, September 15 · 9:00 AM–11:00 AM
Thursday, September 17 · 1:00 PM–3:30 PM
Tuesday, September 22 · 9:00 AM–11:00 AM
Accept proposed schedule
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
| H2 | Help with your recurring care |
| H2 | Sofia Martinez proposed a different schedule |
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
- **expanded:** true
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

#### 7. Choose a visit

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Choose a visit
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 8. Chat with support

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Chat with support
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 9. Close help

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Close help
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 10. Accept proposed schedule

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Accept proposed schedule
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 11. Read full care details →

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
| Caregiver & conversations | http://127.0.0.1:8028/family/requests/39?tab=applicants |
| Contact LoLo Support | http://127.0.0.1:8028/support |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/8 |
| All past visits & payments | http://127.0.0.1:8028/family/care/history?plan=8 |
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
  "documentHeight": 1474,
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
    "headerText": "← Back to your care\n\nRecurring care · Care #8\n\nRecurring care for Eleanor Bennett\n\nSofia Martinez · Mon, Wed, Fri · 10:00 AM–12:00 PM\n\nNew schedule proposed\nCaregiver & conversations\nGet help",
    "primaryTitle": "",
    "selectedTabs": [
      "Overview"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/care/8?tab=overview"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 1835 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/care/8?tab=overview

[Focused view](<../screenshots/RC-plan-countered-help-mobile-focus.png>) · [First screenful](<../screenshots/RC-plan-countered-help-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/RC-plan-countered-help-mobile-full.png>) · [Raw visible text](<../evidence/RC-plan-countered-help-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/RC-plan-countered-help-mobile.json>)

![Mobile screenshot of Recurring care countered — Help](<../screenshots/RC-plan-countered-help-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
← Back to your care

Recurring care · Care #8

Recurring care for Eleanor Bennett

Sofia Martinez · Mon, Wed, Fri · 10:00 AM–12:00 PM

New schedule proposed
Caregiver & conversations
Get help
Overview
Caregivers
Visits
Care details
Help with your recurring care

For hours, payment, a change or a care concern, choose the affected visit. Its support history stays with that date.

Choose a visit
Chat with support
Contact LoLo Support
Close help

YOUR RESPONSE IS NEEDED

Sofia Martinez proposed a different schedule

Tue · 9:00 AM–11:00 AM; Thu · 1:00 PM–3:30 PM

I can help on Tuesday mornings and Thursday afternoons. Would those times work for Ellie?

Proposed dates · not booked yet

Tuesday, September 15 · 9:00 AM–11:00 AM
Thursday, September 17 · 1:00 PM–3:30 PM
Tuesday, September 22 · 9:00 AM–11:00 AM
Accept proposed schedule
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
| H2 | Help with your recurring care |
| H2 | Sofia Martinez proposed a different schedule |
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
- **expanded:** true
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

#### 7. Choose a visit

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Choose a visit
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 8. Chat with support

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Chat with support
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 9. Close help

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Close help
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 10. Accept proposed schedule

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Accept proposed schedule
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 11. Read full care details →

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
| Caregiver & conversations | http://127.0.0.1:8028/family/requests/39?tab=applicants |
| Contact LoLo Support | http://127.0.0.1:8028/support |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/8 |
| All past visits & payments | http://127.0.0.1:8028/family/care/history?plan=8 |

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
  "documentHeight": 1835,
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
    "headerText": "← Back to your care\n\nRecurring care · Care #8\n\nRecurring care for Eleanor Bennett\n\nSofia Martinez · Mon, Wed, Fri · 10:00 AM–12:00 PM\n\nNew schedule proposed\nCaregiver & conversations\nGet help",
    "primaryTitle": "",
    "selectedTabs": [
      "Overview"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/care/8?tab=overview"
}
```
