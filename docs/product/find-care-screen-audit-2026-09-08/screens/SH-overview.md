# SH-overview — Care overview

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | Shared navigation |
| Lifecycle stage | Waiting / inviting |
| Route | /family/requests |
| Selected tab | Care overview |
| Preserved route key | Care overview |
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
  "capturedAt": "2026-09-08T15:34:25.274Z",
  "buildHash": "58c0b4adc3736b3717582dffe837c3b2284299b26b123064150359543361e636",
  "prepared": false,
  "displayTab": "Care overview"
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 1284 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests

[Focused view](<../screenshots/SH-overview-desktop-focus.png>) · [First screenful](<../screenshots/SH-overview-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/SH-overview-desktop-full.png>) · [Raw visible text](<../evidence/SH-overview-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/SH-overview-desktop.json>)

![Desktop screenshot of Care overview](<../screenshots/SH-overview-desktop-focus.png>)

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

CARE OVERVIEW

Hi, Maria.

Here’s an overview of your care.

Request care
Overview
Arrangements
Schedule
History

DO NOW

Needs your attention

The most urgent decisions across all care.

View all 18

PAYMENT ACTION REQUIRED

Payment needs attention

A helping hand for Ellie — Payment Attention · Wed, Sep 9

Sep 9, 10:00 AM to 12:00 PM

Fix payment

PAYMENT ACTION REQUIRED

Payment needs attention

Regular visit for Eleanor Bennett · Wed, Sep 9

Sep 9, 10:00 AM to 12:00 PM

Fix payment

VISIT HOURS NEED YOUR REVIEW

Review Sofia Martinez's reported visit hours

Monday, September 7, 2026

Estimated $54.25 · Review the exact hours before any payment is made.

Review hours

NEXT

Next visit
Schedule (4)
ONE-TIME VISIT
Scheduled
Eleanor Bennett · Wed, Sep 9

10:00 AM–12:00 PM

Sofia Martinez · Raleigh, NC

Open visit

YOUR CARE

Arrangements
View all (19)

Being arranged

Open one-time and recurring care requests

14

Ongoing recurring care

Active or paused schedules

5

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
| H1 | Hi, Maria. |
| H2 | Needs your attention |
| H2 | Next visit |
| H3 | Eleanor Bennett · Wed, Sep 9 |
| H2 | Arrangements |

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

#### 2. Chat with LoLo Support

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
| View all 18 | http://127.0.0.1:8028/family/care/actions |
| PAYMENT ACTION REQUIRED<br><br>Payment needs attention<br><br>A helping hand for Ellie — Payment Attention · Wed, Sep 9<br><br>Sep 9, 10:00 AM to 12:00 PM<br><br>Fix payment | http://127.0.0.1:8028/family/requests/31 |
| PAYMENT ACTION REQUIRED<br><br>Payment needs attention<br><br>Regular visit for Eleanor Bennett · Wed, Sep 9<br><br>Sep 9, 10:00 AM to 12:00 PM<br><br>Fix payment | http://127.0.0.1:8028/family/care/6 |
| VISIT HOURS NEED YOUR REVIEW<br><br>Review Sofia Martinez's reported visit hours<br><br>Monday, September 7, 2026<br><br>Estimated $54.25 · Review the exact hours before any payment is made.<br><br>Review hours | http://127.0.0.1:8028/family/requests/32?tab=shift#time-correction-review-1 |
| Schedule (4) | http://127.0.0.1:8028/family/care/schedule |
| Open visit | http://127.0.0.1:8028/family/care/journey/request/5 |
| View all (19) | http://127.0.0.1:8028/family/care |
| Being arranged<br><br>Open one-time and recurring care requests<br><br>14 | http://127.0.0.1:8028/family/care?view=arranging |
| Ongoing recurring care<br><br>Active or paused schedules<br><br>5 | http://127.0.0.1:8028/family/care?view=ongoing&type=regular |
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
  "documentHeight": 1284,
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
    "heading": "Hi, Maria.",
    "status": "",
    "headerText": "",
    "primaryTitle": "",
    "selectedTabs": []
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 1707 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests

[Focused view](<../screenshots/SH-overview-mobile-focus.png>) · [First screenful](<../screenshots/SH-overview-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/SH-overview-mobile-full.png>) · [Raw visible text](<../evidence/SH-overview-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/SH-overview-mobile.json>)

![Mobile screenshot of Care overview](<../screenshots/SH-overview-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
CARE OVERVIEW

Hi, Maria.

Here’s an overview of your care.

Request care
Overview
Arrangements
Schedule
History

DO NOW

Needs your attention

The most urgent decisions across all care.

View all 18

PAYMENT ACTION REQUIRED

Payment needs attention

A helping hand for Ellie — Payment Attention · Wed, Sep 9

Sep 9, 10:00 AM to 12:00 PM

Fix payment

NEXT

Next visit
Schedule (4)
ONE-TIME VISIT
Scheduled
Eleanor Bennett · Wed, Sep 9

10:00 AM–12:00 PM

Sofia Martinez · Raleigh, NC

Open visit

YOUR CARE

Arrangements
View all (19)

Being arranged

Open one-time and recurring care requests

14

Ongoing recurring care

Active or paused schedules

5

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
| H1 | Hi, Maria. |
| H2 | Needs your attention |
| H2 | Next visit |
| H3 | Eleanor Bennett · Wed, Sep 9 |
| H2 | Arrangements |

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

#### 2. Chat with LoLo Support

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
| View all 18 | http://127.0.0.1:8028/family/care/actions |
| PAYMENT ACTION REQUIRED<br><br>Payment needs attention<br><br>A helping hand for Ellie — Payment Attention · Wed, Sep 9<br><br>Sep 9, 10:00 AM to 12:00 PM<br><br>Fix payment | http://127.0.0.1:8028/family/requests/31 |
| Schedule (4) | http://127.0.0.1:8028/family/care/schedule |
| Open visit | http://127.0.0.1:8028/family/care/journey/request/5 |
| View all (19) | http://127.0.0.1:8028/family/care |
| Being arranged<br><br>Open one-time and recurring care requests<br><br>14 | http://127.0.0.1:8028/family/care?view=arranging |
| Ongoing recurring care<br><br>Active or paused schedules<br><br>5 | http://127.0.0.1:8028/family/care?view=ongoing&type=regular |
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
  "documentHeight": 1707,
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
    "heading": "Hi, Maria.",
    "status": "",
    "headerText": "",
    "primaryTitle": "",
    "selectedTabs": []
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests"
}
```
