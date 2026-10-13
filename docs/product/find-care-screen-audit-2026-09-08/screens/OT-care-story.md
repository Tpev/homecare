# OT-care-story — Care story — completed request and connected visit

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | One-time care |
| Lifecycle stage | Closed |
| Route | /family/care/journey/request/10 |
| Selected tab | Care sections |
| Preserved route key | Care sections |
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
  "capturedAt": "2026-09-08T15:36:41.903Z",
  "buildHash": "58c0b4adc3736b3717582dffe837c3b2284299b26b123064150359543361e636",
  "prepared": false,
  "displayTab": "Care sections"
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 1394 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/care/journey/request/10

[Focused view](<../screenshots/OT-care-story-desktop-focus.png>) · [First screenful](<../screenshots/OT-care-story-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/OT-care-story-desktop-full.png>) · [Raw visible text](<../evidence/OT-care-story-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/OT-care-story-desktop.json>)

![Desktop screenshot of Care story — completed request and connected visit](<../screenshots/OT-care-story-desktop-focus.png>)

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
← Back to Care
ONE-TIME CARE
Filled
Eleanor Bennett with Sofia Martinez

Monday, August 31 · 10:00 AM–12:00 PM

Request #10

NEXT STEP

See the visit, messages, submitted hours, and payment in one place.

Open visit details
Overview
Arrangements
Schedule
History

THE COMPLETE STORY

How this care is progressing

Requests, caregiver selection, visits, hours, and payment stay connected here without changing how each workflow operates.

✓

Care requested

One-time care created for Eleanor Bennett.

Sep 7, 2026 · 8:51 AM

✓

Caregiver selected

Sofia Martinez was selected.

Aug 30, 2026 · 10:00 AM

✓

Visit scheduled

Monday, August 31 · 10:00 AM–12:00 PM

Sep 7, 2026 · 8:51 AM

✓

Care delivered

The family approved the submitted visit hours.

Aug 31, 2026 · 12:35 PM

✓

Payment

Payment completed · $62.00.

Aug 31, 2026 · 12:35 PM

Visits connected to this care

Scheduled and completed visits remain traceable to the care that created them.

Mon, Aug 31, 2026

10:00 AM–12:00 PM · Sofia Martinez

Reviewed
·
Payment: Captured
Open visit

CARE DETAILS

Care recipient
Eleanor Bennett
Caregiver
Sofia Martinez
Schedule
Monday, August 31 · 10:00 AM–12:00 PM
Open full management
Care tasks
Companionship
Meal preparation
Light housekeeping

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
| H1 | Eleanor Bennett with Sofia Martinez |
| H2 | How this care is progressing |
| H2 | Visits connected to this care |
| H2 | Care tasks |

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
| ← Back to Care | http://127.0.0.1:8028/family/requests |
| Open visit details | http://127.0.0.1:8028/family/requests/10 |
| Overview | http://127.0.0.1:8028/family/requests |
| Arrangements | http://127.0.0.1:8028/family/care |
| Schedule | http://127.0.0.1:8028/family/care/schedule |
| History | http://127.0.0.1:8028/family/care/history |
| Open visit | http://127.0.0.1:8028/family/requests/10 |
| Open full management | http://127.0.0.1:8028/family/requests/10 |
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
  "documentHeight": 1394,
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
    "heading": "",
    "status": "",
    "headerText": "",
    "primaryTitle": "",
    "selectedTabs": []
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/care/journey/request/10"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 2492 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/care/journey/request/10

[Focused view](<../screenshots/OT-care-story-mobile-focus.png>) · [First screenful](<../screenshots/OT-care-story-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/OT-care-story-mobile-full.png>) · [Raw visible text](<../evidence/OT-care-story-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/OT-care-story-mobile.json>)

![Mobile screenshot of Care story — completed request and connected visit](<../screenshots/OT-care-story-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
← Back to Care
ONE-TIME CARE
Filled
Eleanor Bennett with Sofia Martinez

Monday, August 31 · 10:00 AM–12:00 PM

Request #10

NEXT STEP

See the visit, messages, submitted hours, and payment in one place.

Open visit details
Overview
Arrangements
Schedule
History

THE COMPLETE STORY

How this care is progressing

Requests, caregiver selection, visits, hours, and payment stay connected here without changing how each workflow operates.

✓

Care requested

One-time care created for Eleanor Bennett.

Sep 7, 2026 · 8:51 AM

✓

Caregiver selected

Sofia Martinez was selected.

Aug 30, 2026 · 10:00 AM

✓

Visit scheduled

Monday, August 31 · 10:00 AM–12:00 PM

Sep 7, 2026 · 8:51 AM

✓

Care delivered

The family approved the submitted visit hours.

Aug 31, 2026 · 12:35 PM

✓

Payment

Payment completed · $62.00.

Aug 31, 2026 · 12:35 PM

Visits connected to this care

Scheduled and completed visits remain traceable to the care that created them.

Mon, Aug 31, 2026

10:00 AM–12:00 PM · Sofia Martinez

Reviewed
·
Payment: Captured
Open visit

CARE DETAILS

Care recipient
Eleanor Bennett
Caregiver
Sofia Martinez
Schedule
Monday, August 31 · 10:00 AM–12:00 PM
Open full management
Care tasks
Companionship
Meal preparation
Light housekeeping
Open visit details

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
| H1 | Eleanor Bennett with Sofia Martinez |
| H2 | How this care is progressing |
| H2 | Visits connected to this care |
| H2 | Care tasks |

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
| ← Back to Care | http://127.0.0.1:8028/family/requests |
| Open visit details | http://127.0.0.1:8028/family/requests/10 |
| Overview | http://127.0.0.1:8028/family/requests |
| Arrangements | http://127.0.0.1:8028/family/care |
| Schedule | http://127.0.0.1:8028/family/care/schedule |
| History | http://127.0.0.1:8028/family/care/history |
| Open visit | http://127.0.0.1:8028/family/requests/10 |
| Open full management | http://127.0.0.1:8028/family/requests/10 |
| Open visit details | http://127.0.0.1:8028/family/requests/10 |
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
  "documentHeight": 2492,
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
    "heading": "",
    "status": "",
    "headerText": "",
    "primaryTitle": "",
    "selectedTabs": []
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/care/journey/request/10"
}
```
