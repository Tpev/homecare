# SH-arrangements — Arrangements

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | Shared navigation |
| Lifecycle stage | Hired / before shift |
| Route | /family/care |
| Selected tab | Arrangements |
| Preserved route key | Arrangements |
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
  "capturedAt": "2026-09-08T15:34:30.144Z",
  "buildHash": "58c0b4adc3736b3717582dffe837c3b2284299b26b123064150359543361e636",
  "prepared": false,
  "displayTab": "Arrangements"
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 1928 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/care

[Focused view](<../screenshots/SH-arrangements-desktop-focus.png>) · [First screenful](<../screenshots/SH-arrangements-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/SH-arrangements-desktop-full.png>) · [Raw visible text](<../evidence/SH-arrangements-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/SH-arrangements-desktop.json>)

![Desktop screenshot of Arrangements](<../screenshots/SH-arrangements-desktop-focus.png>)

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
Care arrangements

ARRANGEMENTS

Every care arrangement, in one place.

See care still being arranged and ongoing caregiver relationships. Confirmed dates belong in Schedule.

Request care
Overview
Arrangements
Schedule
History
Status
Current care
Being arranged
Ongoing care
Past care
All care
Care type
All care types
One-time care
Recurring care

REQUESTS AND RELATIONSHIPS

19 arrangements
Open visit schedule
RECURRING CARE
New schedule proposed
Recurring care for Eleanor Bennett

Caregiver: Sofia Martinez

Mon, Wed, Fri · 10:00 AM–12:00 PM

Payment checked before each visit

Recurring care #8
Manage recurring care
RECURRING CARE
Payment needs attention
Recurring care for Eleanor Bennett

Caregiver: Sofia Martinez

Mon, Wed, Fri · 10:00 AM–12:00 PM

Next visit: Wednesday, September 9 · 10:00 AM–12:00 PM

Payment action needed

Recurring care #6
Fix payment
RECURRING CARE
Active
Recurring care for Eleanor Bennett

Caregiver: Sofia Martinez

Mon, Wed, Fri · 10:00 AM–12:00 PM

Payment authorized

Recurring care #5
Manage recurring care
RECURRING CARE
Active
Recurring care for Eleanor Bennett

Caregiver: Sofia Martinez

Mon, Wed, Fri · 10:00 AM–12:00 PM

Payment authorized

Recurring care #7
Manage recurring care
RECURRING CARE
Active
Recurring care for Eleanor Bennett

Caregiver: Sofia Martinez

Mon, Wed, Fri · 10:00 AM–12:00 PM

Next visit: Wednesday, September 9 · 10:00 AM–12:00 PM

Payment authorized

Recurring care #1
Manage recurring care
RECURRING CARE
Waiting for Sofia Martinez
Recurring care for Eleanor Bennett

Caregiver: Sofia Martinez

Mon, Wed, Fri · 10:00 AM–12:00 PM

Payment checked before each visit

Recurring care #2
Manage recurring care
RECURRING CARE
Paused
Recurring care for Eleanor Bennett

Caregiver: Sofia Martinez

Mon, Wed, Fri · 10:00 AM–12:00 PM

Payment authorized

Recurring care #3
Manage recurring care
ONE-TIME CARE
1 caregiver replied
Eleanor Bennett · Thu, Sep 10

Thursday, September 10 · 10:00 AM–12:00 PM

Raleigh, NC

Request #27
Review caregivers

Showing 8 of 19 arrangements.

Show more arrangements

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
| H1 | Every care arrangement, in one place. |
| H2 | 19 arrangements |
| H3 | Recurring care for Eleanor Bennett |
| H3 | Recurring care for Eleanor Bennett |
| H3 | Recurring care for Eleanor Bennett |
| H3 | Recurring care for Eleanor Bennett |
| H3 | Recurring care for Eleanor Bennett |
| H3 | Recurring care for Eleanor Bennett |
| H3 | Recurring care for Eleanor Bennett |
| H3 | Eleanor Bennett · Thu, Sep 10 |

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

#### 2. Status

- **tag:** SELECT
- **type:** select-one
- **label:** Status
- **text:** (empty)
- **value:** current
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null
- **Dropdown options:**
  - Current care — value: current; selected: true
  - Being arranged — value: arranging; selected: false
  - Ongoing care — value: ongoing; selected: false
  - Past care — value: past; selected: false
  - All care — value: all; selected: false

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
  - All care types — value: all; selected: true
  - One-time care — value: one\_time; selected: false
  - Recurring care — value: regular; selected: false

#### 4. Show more arrangements

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Show more arrangements
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 5. Chat with LoLo Support

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
| Open visit schedule | http://127.0.0.1:8028/family/care/schedule?person=all |
| Manage recurring care | http://127.0.0.1:8028/family/care/8 |
| Fix payment | http://127.0.0.1:8028/family/care/6 |
| Manage recurring care | http://127.0.0.1:8028/family/care/5 |
| Manage recurring care | http://127.0.0.1:8028/family/care/7 |
| Manage recurring care | http://127.0.0.1:8028/family/care/1 |
| Manage recurring care | http://127.0.0.1:8028/family/care/2 |
| Manage recurring care | http://127.0.0.1:8028/family/care/3 |
| Review caregivers | http://127.0.0.1:8028/family/requests/27 |
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
  "documentHeight": 1928,
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
    "heading": "Every care arrangement, in one place.",
    "status": "",
    "headerText": "",
    "primaryTitle": "",
    "selectedTabs": []
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/care"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 3683 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/care

[Focused view](<../screenshots/SH-arrangements-mobile-focus.png>) · [First screenful](<../screenshots/SH-arrangements-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/SH-arrangements-mobile-full.png>) · [Raw visible text](<../evidence/SH-arrangements-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/SH-arrangements-mobile.json>)

![Mobile screenshot of Arrangements](<../screenshots/SH-arrangements-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
Care arrangements

ARRANGEMENTS

Every care arrangement, in one place.
Request care
Overview
Arrangements
Schedule
History
Status
Current care
Being arranged
Ongoing care
Past care
All care
Care type
All care types
One-time care
Recurring care

REQUESTS AND RELATIONSHIPS

19 arrangements
Open visit schedule
RECURRING CARE
New schedule proposed
Recurring care for Eleanor Bennett

Caregiver: Sofia Martinez

Mon, Wed, Fri · 10:00 AM–12:00 PM

Payment checked before each visit

Recurring care #8
Manage recurring care
RECURRING CARE
Payment needs attention
Recurring care for Eleanor Bennett

Caregiver: Sofia Martinez

Mon, Wed, Fri · 10:00 AM–12:00 PM

Next visit: Wednesday, September 9 · 10:00 AM–12:00 PM

Payment action needed

Recurring care #6
Fix payment
RECURRING CARE
Active
Recurring care for Eleanor Bennett

Caregiver: Sofia Martinez

Mon, Wed, Fri · 10:00 AM–12:00 PM

Payment authorized

Recurring care #5
Manage recurring care
RECURRING CARE
Active
Recurring care for Eleanor Bennett

Caregiver: Sofia Martinez

Mon, Wed, Fri · 10:00 AM–12:00 PM

Payment authorized

Recurring care #7
Manage recurring care
RECURRING CARE
Active
Recurring care for Eleanor Bennett

Caregiver: Sofia Martinez

Mon, Wed, Fri · 10:00 AM–12:00 PM

Next visit: Wednesday, September 9 · 10:00 AM–12:00 PM

Payment authorized

Recurring care #1
Manage recurring care
RECURRING CARE
Waiting for Sofia Martinez
Recurring care for Eleanor Bennett

Caregiver: Sofia Martinez

Mon, Wed, Fri · 10:00 AM–12:00 PM

Payment checked before each visit

Recurring care #2
Manage recurring care
RECURRING CARE
Paused
Recurring care for Eleanor Bennett

Caregiver: Sofia Martinez

Mon, Wed, Fri · 10:00 AM–12:00 PM

Payment authorized

Recurring care #3
Manage recurring care
ONE-TIME CARE
1 caregiver replied
Eleanor Bennett · Thu, Sep 10

Thursday, September 10 · 10:00 AM–12:00 PM

Raleigh, NC

Request #27
Review caregivers

Showing 8 of 19 arrangements.

Show more arrangements

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
| H1 | Every care arrangement, in one place. |
| H2 | 19 arrangements |
| H3 | Recurring care for Eleanor Bennett |
| H3 | Recurring care for Eleanor Bennett |
| H3 | Recurring care for Eleanor Bennett |
| H3 | Recurring care for Eleanor Bennett |
| H3 | Recurring care for Eleanor Bennett |
| H3 | Recurring care for Eleanor Bennett |
| H3 | Recurring care for Eleanor Bennett |
| H3 | Eleanor Bennett · Thu, Sep 10 |

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

#### 2. Status

- **tag:** SELECT
- **type:** select-one
- **label:** Status
- **text:** (empty)
- **value:** current
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null
- **Dropdown options:**
  - Current care — value: current; selected: true
  - Being arranged — value: arranging; selected: false
  - Ongoing care — value: ongoing; selected: false
  - Past care — value: past; selected: false
  - All care — value: all; selected: false

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
  - All care types — value: all; selected: true
  - One-time care — value: one\_time; selected: false
  - Recurring care — value: regular; selected: false

#### 4. Show more arrangements

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Show more arrangements
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 5. Chat with LoLo Support

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
| Open visit schedule | http://127.0.0.1:8028/family/care/schedule?person=all |
| Manage recurring care | http://127.0.0.1:8028/family/care/8 |
| Fix payment | http://127.0.0.1:8028/family/care/6 |
| Manage recurring care | http://127.0.0.1:8028/family/care/5 |
| Manage recurring care | http://127.0.0.1:8028/family/care/7 |
| Manage recurring care | http://127.0.0.1:8028/family/care/1 |
| Manage recurring care | http://127.0.0.1:8028/family/care/2 |
| Manage recurring care | http://127.0.0.1:8028/family/care/3 |
| Review caregivers | http://127.0.0.1:8028/family/requests/27 |
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
  "documentHeight": 3683,
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
    "heading": "Every care arrangement, in one place.",
    "status": "",
    "headerText": "",
    "primaryTitle": "",
    "selectedTabs": []
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/care"
}
```
