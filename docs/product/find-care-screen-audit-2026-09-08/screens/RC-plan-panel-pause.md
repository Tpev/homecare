# RC-plan-panel-pause — Recurring plan — Pause recurring care

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | Recurring care |
| Lifecycle stage | Hired / before shift |
| Route | /family/care/1?tab=visits |
| Selected tab | Visits |
| Preserved route key | visits |
| Panel / disclosure | pause |
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
  "focusSelector": "h3:has-text(\"Pause recurring care\")",
  "captureVersion": 5,
  "capturedAt": "2026-09-08T15:34:03.398Z",
  "buildHash": "58c0b4adc3736b3717582dffe837c3b2284299b26b123064150359543361e636",
  "prepared": true,
  "displayTab": "Visits"
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 2047 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/care/1?tab=visits

[Focused view](<../screenshots/RC-plan-panel-pause-desktop-focus.png>) · [First screenful](<../screenshots/RC-plan-panel-pause-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/RC-plan-panel-pause-desktop-full.png>) · [Raw visible text](<../evidence/RC-plan-panel-pause-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/RC-plan-panel-pause-desktop.json>)

![Desktop screenshot of Recurring plan — Pause recurring care](<../screenshots/RC-plan-panel-pause-desktop-focus.png>)

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

Recurring care · Care #1

Recurring care for Eleanor Bennett

Sofia Martinez · Mon, Wed, Fri · 10:00 AM–12:00 PM

Recurring care is active
Caregiver & conversations
Get help
Overview
Caregivers
Visits
Care details

3 items to follow up across this care.

View care updates

RECURRING SCHEDULE

Mon, Wed, Fri · 10:00 AM–12:00 PM

Aug 24, 2026 through Dec 7, 2026 · America/New_York

Recurring care is active

Only booked dates appear in the list. Open a visit to manage that date’s hours, payment, care notes or support.

Manage recurring care

Choose one change. We will explain what happens before you confirm.

Add an extra visit
Change future schedule
Pause recurring care
End recurring care
Pause recurring care

Visits during the pause will be cancelled. You can choose a return date or resume later.

Pause starting
Return date (optional)
Pause care
Cancel
Your booked visits
Upcoming
Needs follow-up
Past
Cancelled
All visits

2 visits in this view

Recurring visit · Visit #9

Monday, September 7, 2026

7:56 AM–9:56 AM EDT · Sofia Martinez

Happening now

Card authorized

View visit details
Get help with this visit

Recurring visit · Visit #8

Wednesday, September 9, 2026

10:00 AM–12:00 PM EDT · Sofia Martinez

Scheduled

Card authorized

View visit details
Get help with this visit
Skip this visit
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
| H3 | Pause recurring care |
| H2 | Your booked visits |
| H3 | Monday, September 7, 2026 |
| H3 | Wednesday, September 9, 2026 |

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

#### 12. Pause starting

- **tag:** INPUT
- **type:** date
- **label:** Pause starting
- **text:** (empty)
- **value:** 2026-09-09
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 13. Return date (optional)

- **tag:** INPUT
- **type:** date
- **label:** Return date (optional)
- **text:** (empty)
- **value:** (empty)
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 14. Pause care

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Pause care
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 15. Cancel

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Cancel
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 16. Upcoming

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Upcoming
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** true
- **placeholder:** null

#### 17. Needs follow-up

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

#### 18. Past

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

#### 19. Cancelled

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Cancelled
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** false
- **placeholder:** null

#### 20. All visits

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

#### 21. Skip this visit

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Skip this visit
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
| Caregiver & conversations | http://127.0.0.1:8028/family/requests/16?tab=applicants |
| View visit details | http://127.0.0.1:8028/family/requests/17?tab=shift&return\_tab=visits&visit\_filter=upcoming&return\_page=1 |
| Get help with this visit | http://127.0.0.1:8028/family/requests/17?tab=support&return\_tab=visits&visit\_filter=upcoming&return\_page=1 |
| View visit details | http://127.0.0.1:8028/family/requests/16?tab=shift&return\_tab=visits&visit\_filter=upcoming&return\_page=1 |
| Get help with this visit | http://127.0.0.1:8028/family/requests/16?tab=support&return\_tab=visits&visit\_filter=upcoming&return\_page=1 |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/1 |
| All past visits & payments | http://127.0.0.1:8028/family/care/history?plan=1 |
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
  "documentHeight": 2047,
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
    "headerText": "← Back to your care\n\nRecurring care · Care #1\n\nRecurring care for Eleanor Bennett\n\nSofia Martinez · Mon, Wed, Fri · 10:00 AM–12:00 PM\n\nRecurring care is active\nCaregiver & conversations\nGet help",
    "primaryTitle": "",
    "selectedTabs": [
      "Visits"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/care/1?tab=visits"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 2953 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/care/1?tab=visits

[Focused view](<../screenshots/RC-plan-panel-pause-mobile-focus.png>) · [First screenful](<../screenshots/RC-plan-panel-pause-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/RC-plan-panel-pause-mobile-full.png>) · [Raw visible text](<../evidence/RC-plan-panel-pause-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/RC-plan-panel-pause-mobile.json>)

![Mobile screenshot of Recurring plan — Pause recurring care](<../screenshots/RC-plan-panel-pause-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
← Back to your care

Recurring care · Care #1

Recurring care for Eleanor Bennett

Sofia Martinez · Mon, Wed, Fri · 10:00 AM–12:00 PM

Recurring care is active
Caregiver & conversations
Get help
Overview
Caregivers
Visits
Care details

3 items to follow up across this care.

View care updates

RECURRING SCHEDULE

Mon, Wed, Fri · 10:00 AM–12:00 PM

Aug 24, 2026 through Dec 7, 2026 · America/New_York

Recurring care is active

Only booked dates appear in the list. Open a visit to manage that date’s hours, payment, care notes or support.

Manage recurring care

Choose one change. We will explain what happens before you confirm.

Add an extra visit
Change future schedule
Pause recurring care
End recurring care
Pause recurring care

Visits during the pause will be cancelled. You can choose a return date or resume later.

Pause starting
Return date (optional)
Pause care
Cancel
Your booked visits
Upcoming
Needs follow-up
Past
Cancelled
All visits

2 visits in this view

Recurring visit · Visit #9

Monday, September 7, 2026

7:56 AM–9:56 AM EDT · Sofia Martinez

Happening now

Card authorized

View visit details
Get help with this visit

Recurring visit · Visit #8

Wednesday, September 9, 2026

10:00 AM–12:00 PM EDT · Sofia Martinez

Scheduled

Card authorized

View visit details
Get help with this visit
Skip this visit
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
| H3 | Pause recurring care |
| H2 | Your booked visits |
| H3 | Monday, September 7, 2026 |
| H3 | Wednesday, September 9, 2026 |

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

#### 12. Pause starting

- **tag:** INPUT
- **type:** date
- **label:** Pause starting
- **text:** (empty)
- **value:** 2026-09-09
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 13. Return date (optional)

- **tag:** INPUT
- **type:** date
- **label:** Return date (optional)
- **text:** (empty)
- **value:** (empty)
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 14. Pause care

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Pause care
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 15. Cancel

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Cancel
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 16. Upcoming

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Upcoming
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** true
- **placeholder:** null

#### 17. Needs follow-up

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

#### 18. Past

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

#### 19. Cancelled

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Cancelled
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** false
- **placeholder:** null

#### 20. All visits

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

#### 21. Skip this visit

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Skip this visit
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
| Caregiver & conversations | http://127.0.0.1:8028/family/requests/16?tab=applicants |
| View visit details | http://127.0.0.1:8028/family/requests/17?tab=shift&return\_tab=visits&visit\_filter=upcoming&return\_page=1 |
| Get help with this visit | http://127.0.0.1:8028/family/requests/17?tab=support&return\_tab=visits&visit\_filter=upcoming&return\_page=1 |
| View visit details | http://127.0.0.1:8028/family/requests/16?tab=shift&return\_tab=visits&visit\_filter=upcoming&return\_page=1 |
| Get help with this visit | http://127.0.0.1:8028/family/requests/16?tab=support&return\_tab=visits&visit\_filter=upcoming&return\_page=1 |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/1 |
| All past visits & payments | http://127.0.0.1:8028/family/care/history?plan=1 |

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
  "documentHeight": 2953,
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
    "headerText": "← Back to your care\n\nRecurring care · Care #1\n\nRecurring care for Eleanor Bennett\n\nSofia Martinez · Mon, Wed, Fri · 10:00 AM–12:00 PM\n\nRecurring care is active\nCaregiver & conversations\nGet help",
    "primaryTitle": "",
    "selectedTabs": [
      "Visits"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/care/1?tab=visits"
}
```
