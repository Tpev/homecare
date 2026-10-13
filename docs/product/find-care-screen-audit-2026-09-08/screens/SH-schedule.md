# SH-schedule — Schedule

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | Shared navigation |
| Lifecycle stage | Hired / before shift |
| Route | /family/care/schedule |
| Selected tab | Schedule |
| Preserved route key | Schedule |
| Panel / disclosure | default |
| Capture status | Captured |
| UX review | Implementation rationale; validation recorded separately |
| UX findings | Cross-care lists and history must retain their existing record identity and filtering. |
| Redesign decision | Shared styling connects these pages to the new workspace. Care cards open the continuing arrangement; booked-date actions open the exact visit. |
| Owner | Local care workspace implementation |
| Priority |  |

These screenshots show the current application with synthetic audit data. This is capture and source-audit evidence; the local UI redesign is implemented. Remote map/payment widgets were blocked in this local capture session, so their current fallback states are preserved. Brand fonts are served locally.

## Additional capture context

```json
{
  "captureVersion": 5,
  "capturedAt": "2026-09-08T15:34:35.395Z",
  "buildHash": "58c0b4adc3736b3717582dffe837c3b2284299b26b123064150359543361e636",
  "prepared": false,
  "displayTab": "Schedule"
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 1437 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/care/schedule

[Focused view](<../screenshots/SH-schedule-desktop-focus.png>) · [First screenful](<../screenshots/SH-schedule-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/SH-schedule-desktop-full.png>) · [Raw visible text](<../evidence/SH-schedule-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/SH-schedule-desktop.json>)

![Desktop screenshot of Schedule](<../screenshots/SH-schedule-desktop-focus.png>)

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

CARE SCHEDULE

Your visits, organized by when they happen.

One-time, regular, extra, and continuous care share one schedule. Start with today, then look ahead.

Request care
Overview
Arrangements
Schedule
History
4 upcoming visits

Filter by person or care type without changing the underlying schedule.

Care recipient
Everyone
Eleanor Bennett
Care type
All upcoming care
One-time visits
Recurring care visits
Extra visits
Continuous care

LOOKING AHEAD

This week

The rest of this week, grouped by day.

4

TOMORROW

Sep 9

ONE-TIME VISIT
Scheduled
Eleanor Bennett · Wed, Sep 9

10:00 AM–12:00 PM · Sofia Martinez

Raleigh, NC

Visit #1

View care
REGULAR VISIT
Scheduled
Eleanor Bennett · Wed, Sep 9

10:00 AM–12:00 PM · Sofia Martinez

Raleigh, NC

Visit #8

View care
ONE-TIME VISIT
Payment needs attention
Eleanor Bennett · Wed, Sep 9

10:00 AM–12:00 PM · Sofia Martinez

Raleigh, NC

Visit #19

Fix payment

Required to keep this visit financially protected.

REGULAR VISIT
Payment needs attention
Eleanor Bennett · Wed, Sep 9

10:00 AM–12:00 PM · Sofia Martinez

Raleigh, NC

Visit #22

Fix payment

Required to keep this visit financially protected.

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
| H1 | Your visits, organized by when they happen. |
| H2 | 4 upcoming visits |
| H2 | This week |
| H3 | Eleanor Bennett · Wed, Sep 9 |
| H3 | Eleanor Bennett · Wed, Sep 9 |
| H3 | Eleanor Bennett · Wed, Sep 9 |
| H3 | Eleanor Bennett · Wed, Sep 9 |

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

#### 2. Care recipient

- **tag:** SELECT
- **type:** select-one
- **label:** Care recipient
- **text:** (empty)
- **value:** all
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null
- **Dropdown options:**
  - Everyone — value: all; selected: true
  - Eleanor Bennett — value: Eleanor Bennett; selected: false

#### 3. Care type

- **tag:** SELECT
- **type:** select-one
- **label:** Care type
- **text:** (empty)
- **value:** all
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null
- **Dropdown options:**
  - All upcoming care — value: all; selected: true
  - One-time visits — value: one\_time; selected: false
  - Recurring care visits — value: regular; selected: false
  - Extra visits — value: extra; selected: false
  - Continuous care — value: coverage; selected: false

#### 4. Chat with LoLo Support

- **tag:** BUTTON
- **type:** button
- **label:** Chat with LoLo Support
- **text:** Support
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
| Request care | http://127.0.0.1:8028/family/requests/create |
| Overview | http://127.0.0.1:8028/family/requests |
| Arrangements | http://127.0.0.1:8028/family/care |
| Schedule | http://127.0.0.1:8028/family/care/schedule |
| History | http://127.0.0.1:8028/family/care/history |
| View care | http://127.0.0.1:8028/family/care/journey/request/5 |
| View care | http://127.0.0.1:8028/family/care/journey/regular/1 |
| Fix payment | http://127.0.0.1:8028/family/requests/31?tab=shift |
| Fix payment | http://127.0.0.1:8028/family/requests/37?tab=shift |
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
  "documentHeight": 1437,
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
    "heading": "Your visits, organized by when they happen.",
    "status": "",
    "headerText": "",
    "primaryTitle": "",
    "selectedTabs": []
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/care/schedule"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 2122 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/care/schedule

[Focused view](<../screenshots/SH-schedule-mobile-focus.png>) · [First screenful](<../screenshots/SH-schedule-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/SH-schedule-mobile-full.png>) · [Raw visible text](<../evidence/SH-schedule-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/SH-schedule-mobile.json>)

![Mobile screenshot of Schedule](<../screenshots/SH-schedule-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
CARE SCHEDULE

Your visits, organized by when they happen.
Request care
Overview
Arrangements
Schedule
History
4 upcoming visits

Filter by person or care type without changing the underlying schedule.

Care recipient
Everyone
Eleanor Bennett
Care type
All upcoming care
One-time visits
Recurring care visits
Extra visits
Continuous care

LOOKING AHEAD

This week

The rest of this week, grouped by day.

4

TOMORROW

Sep 9

ONE-TIME VISIT
Scheduled
Eleanor Bennett · Wed, Sep 9

10:00 AM–12:00 PM · Sofia Martinez

Raleigh, NC

Visit #1

View care
REGULAR VISIT
Scheduled
Eleanor Bennett · Wed, Sep 9

10:00 AM–12:00 PM · Sofia Martinez

Raleigh, NC

Visit #8

View care
ONE-TIME VISIT
Payment needs attention
Eleanor Bennett · Wed, Sep 9

10:00 AM–12:00 PM · Sofia Martinez

Raleigh, NC

Visit #19

Fix payment

Required to keep this visit financially protected.

REGULAR VISIT
Payment needs attention
Eleanor Bennett · Wed, Sep 9

10:00 AM–12:00 PM · Sofia Martinez

Raleigh, NC

Visit #22

Fix payment

Required to keep this visit financially protected.

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
| H1 | Your visits, organized by when they happen. |
| H2 | 4 upcoming visits |
| H2 | This week |
| H3 | Eleanor Bennett · Wed, Sep 9 |
| H3 | Eleanor Bennett · Wed, Sep 9 |
| H3 | Eleanor Bennett · Wed, Sep 9 |
| H3 | Eleanor Bennett · Wed, Sep 9 |

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

#### 2. Care recipient

- **tag:** SELECT
- **type:** select-one
- **label:** Care recipient
- **text:** (empty)
- **value:** all
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null
- **Dropdown options:**
  - Everyone — value: all; selected: true
  - Eleanor Bennett — value: Eleanor Bennett; selected: false

#### 3. Care type

- **tag:** SELECT
- **type:** select-one
- **label:** Care type
- **text:** (empty)
- **value:** all
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null
- **Dropdown options:**
  - All upcoming care — value: all; selected: true
  - One-time visits — value: one\_time; selected: false
  - Recurring care visits — value: regular; selected: false
  - Extra visits — value: extra; selected: false
  - Continuous care — value: coverage; selected: false

#### 4. Chat with LoLo Support

- **tag:** BUTTON
- **type:** button
- **label:** Chat with LoLo Support
- **text:** Support
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
| Request care | http://127.0.0.1:8028/family/requests/create |
| Overview | http://127.0.0.1:8028/family/requests |
| Arrangements | http://127.0.0.1:8028/family/care |
| Schedule | http://127.0.0.1:8028/family/care/schedule |
| History | http://127.0.0.1:8028/family/care/history |
| View care | http://127.0.0.1:8028/family/care/journey/request/5 |
| View care | http://127.0.0.1:8028/family/care/journey/regular/1 |
| Fix payment | http://127.0.0.1:8028/family/requests/31?tab=shift |
| Fix payment | http://127.0.0.1:8028/family/requests/37?tab=shift |
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
  "width": 390,
  "height": 844,
  "documentWidth": 390,
  "documentHeight": 2122,
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
    "heading": "Your visits, organized by when they happen.",
    "status": "",
    "headerText": "",
    "primaryTitle": "",
    "selectedTabs": []
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/care/schedule"
}
```
