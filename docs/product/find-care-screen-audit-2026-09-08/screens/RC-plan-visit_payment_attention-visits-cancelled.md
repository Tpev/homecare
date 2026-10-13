# RC-plan-visit_payment_attention-visits-cancelled — Recurring care visit_payment_attention — Visits / cancelled

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | Recurring care |
| Lifecycle stage | Hired / before shift |
| Route | /family/care/6?tab=visits&visit\_filter=cancelled |
| Selected tab | Visits |
| Preserved route key | visits |
| Panel / disclosure | cancelled filter |
| Capture status | Captured |
| UX review | Implementation rationale; validation recorded separately |
| UX findings | Families need to distinguish confirmed dates from a requested schedule and find older outstanding work. |
| Redesign decision | Filters use real bookings, retain exact-visit links and preserve the tab, filter and pagination context on return. Schedule management keeps existing business rules. |
| Owner | Local care workspace implementation |
| Priority |  |

These screenshots show the current application with synthetic audit data. This is capture and source-audit evidence; the local UI redesign is implemented. Remote map/payment widgets were blocked in this local capture session, so their current fallback states are preserved. Brand fonts are served locally.

## Additional capture context

```json
{
  "addedByRedesign": true,
  "captureVersion": 5,
  "capturedAt": "2026-09-08T15:50:35.907Z",
  "buildHash": "58c0b4adc3736b3717582dffe837c3b2284299b26b123064150359543361e636",
  "prepared": true,
  "displayTab": "Visits"
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 1392 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/care/6?tab=visits&visit\_filter=cancelled

[Focused view](<../screenshots/RC-plan-visit_payment_attention-visits-cancelled-desktop-focus.png>) · [First screenful](<../screenshots/RC-plan-visit_payment_attention-visits-cancelled-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/RC-plan-visit_payment_attention-visits-cancelled-desktop-full.png>) · [Raw visible text](<../evidence/RC-plan-visit_payment_attention-visits-cancelled-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/RC-plan-visit_payment_attention-visits-cancelled-desktop.json>)

![Desktop screenshot of Recurring care visit\_payment\_attention — Visits / cancelled](<../screenshots/RC-plan-visit_payment_attention-visits-cancelled-desktop-focus.png>)

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

Recurring care · Care #6

Recurring care for Eleanor Bennett

Sofia Martinez · Mon, Wed, Fri · 10:00 AM–12:00 PM

Payment needs attention
Caregiver & conversations
Get help
Overview
Caregivers
Visits
Care details

1 item to follow up across this care.

View care updates

RECURRING SCHEDULE

Mon, Wed, Fri · 10:00 AM–12:00 PM

Aug 24, 2026 through Dec 7, 2026 · America/New_York

Payment needs attention

Only booked dates appear in the list. Open a visit to manage that date’s hours, payment, care notes or support.

Manage recurring care

Choose one change. We will explain what happens before you confirm.

Add an extra visit
Change future schedule
Pause recurring care
End recurring care
Your booked visits
Upcoming
Needs follow-up
Past
Cancelled
All visits

0 visits in this view

No booked visits in this view.

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
| H2 | Mon, Wed, Fri · 10:00 AM–12:00 PM |
| H2 | Manage recurring care |
| H2 | Your booked visits |

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
- **current:** page
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

#### 7. View care updates

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** View care updates
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 8. Add an extra visit

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Add an extra visit
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 9. Change future schedule

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Change future schedule
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 10. Pause recurring care

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Pause recurring care
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 11. End recurring care

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** End recurring care
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 12. Upcoming

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Upcoming
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** false
- **placeholder:** null

#### 13. Needs follow-up

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Needs follow-up
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** false
- **placeholder:** null

#### 14. Past

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Past
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** false
- **placeholder:** null

#### 15. Cancelled

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Cancelled
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** true
- **placeholder:** null

#### 16. All visits

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** All visits
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** false
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
| Caregiver & conversations | http://127.0.0.1:8028/family/requests/37?tab=applicants |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/6 |
| All past visits & payments | http://127.0.0.1:8028/family/care/history?plan=6 |
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
  "documentHeight": 1392,
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
    "headerText": "← Back to your care\n\nRecurring care · Care #6\n\nRecurring care for Eleanor Bennett\n\nSofia Martinez · Mon, Wed, Fri · 10:00 AM–12:00 PM\n\nPayment needs attention\nCaregiver & conversations\nGet help",
    "primaryTitle": "",
    "selectedTabs": [
      "Visits"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/care/6?tab=visits&visit_filter=cancelled"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 1856 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/care/6?tab=visits&visit\_filter=cancelled

[Focused view](<../screenshots/RC-plan-visit_payment_attention-visits-cancelled-mobile-focus.png>) · [First screenful](<../screenshots/RC-plan-visit_payment_attention-visits-cancelled-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/RC-plan-visit_payment_attention-visits-cancelled-mobile-full.png>) · [Raw visible text](<../evidence/RC-plan-visit_payment_attention-visits-cancelled-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/RC-plan-visit_payment_attention-visits-cancelled-mobile.json>)

![Mobile screenshot of Recurring care visit\_payment\_attention — Visits / cancelled](<../screenshots/RC-plan-visit_payment_attention-visits-cancelled-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
← Back to your care

Recurring care · Care #6

Recurring care for Eleanor Bennett

Sofia Martinez · Mon, Wed, Fri · 10:00 AM–12:00 PM

Payment needs attention
Caregiver & conversations
Get help
Overview
Caregivers
Visits
Care details

1 item to follow up across this care.

View care updates

RECURRING SCHEDULE

Mon, Wed, Fri · 10:00 AM–12:00 PM

Aug 24, 2026 through Dec 7, 2026 · America/New_York

Payment needs attention

Only booked dates appear in the list. Open a visit to manage that date’s hours, payment, care notes or support.

Manage recurring care

Choose one change. We will explain what happens before you confirm.

Add an extra visit
Change future schedule
Pause recurring care
End recurring care
Your booked visits
Upcoming
Needs follow-up
Past
Cancelled
All visits

0 visits in this view

No booked visits in this view.

Care story
All past visits & payments

© 2026 LoLo

Legal links
```

### Visible headings

| Level | Heading |
|---|---|
| H1 | Recurring care for Eleanor Bennett |
| H2 | Mon, Wed, Fri · 10:00 AM–12:00 PM |
| H2 | Manage recurring care |
| H2 | Your booked visits |

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
- **current:** page
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

#### 7. View care updates

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** View care updates
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 8. Add an extra visit

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Add an extra visit
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 9. Change future schedule

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Change future schedule
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 10. Pause recurring care

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Pause recurring care
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 11. End recurring care

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** End recurring care
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 12. Upcoming

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Upcoming
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** false
- **placeholder:** null

#### 13. Needs follow-up

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Needs follow-up
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** false
- **placeholder:** null

#### 14. Past

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Past
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** false
- **placeholder:** null

#### 15. Cancelled

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Cancelled
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** true
- **placeholder:** null

#### 16. All visits

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** All visits
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** false
- **placeholder:** null

### Every visible link

| Label | Destination |
|---|---|
| Family workspace | http://127.0.0.1:8028/family/requests |
| Notifications | http://127.0.0.1:8028/family/notifications |
| ← Back to your care | http://127.0.0.1:8028/family/care |
| Caregiver & conversations | http://127.0.0.1:8028/family/requests/37?tab=applicants |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/6 |
| All past visits & payments | http://127.0.0.1:8028/family/care/history?plan=6 |

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
  "documentHeight": 1856,
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
    "headerText": "← Back to your care\n\nRecurring care · Care #6\n\nRecurring care for Eleanor Bennett\n\nSofia Martinez · Mon, Wed, Fri · 10:00 AM–12:00 PM\n\nPayment needs attention\nCaregiver & conversations\nGet help",
    "primaryTitle": "",
    "selectedTabs": [
      "Visits"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/care/6?tab=visits&visit_filter=cancelled"
}
```
