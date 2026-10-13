# OT-applicants-home — applicants — Overview

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | One-time care |
| Lifecycle stage | Waiting / inviting |
| Route | /family/requests/4?tab=home |
| Selected tab | Overview |
| Preserved route key | home |
| Panel / disclosure | default |
| Capture status | Captured |
| UX review | Implementation rationale; validation recorded separately |
| UX findings | The next step should remain clear for zero, one or many replies, including overdue requests. |
| Redesign decision | Overview gives the request status and current action; Caregivers, Visits where relevant, Care details and Help remain predictable destinations. |
| Owner | Local care workspace implementation |
| Priority |  |

These screenshots show the current application with synthetic audit data. This is capture and source-audit evidence; the local UI redesign is implemented. Remote map/payment widgets were blocked in this local capture session, so their current fallback states are preserved. Brand fonts are served locally.

## Additional capture context

```json
{
  "addedByRedesign": true,
  "captureVersion": 5,
  "capturedAt": "2026-09-08T15:42:20.098Z",
  "buildHash": "58c0b4adc3736b3717582dffe837c3b2284299b26b123064150359543361e636",
  "prepared": false,
  "displayTab": "Overview"
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 1050 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/4?tab=home

[Focused view](<../screenshots/OT-applicants-home-desktop-focus.png>) · [First screenful](<../screenshots/OT-applicants-home-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/OT-applicants-home-desktop-full.png>) · [Raw visible text](<../evidence/OT-applicants-home-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/OT-applicants-home-desktop.json>)

![Desktop screenshot of applicants — Overview](<../screenshots/OT-applicants-home-desktop-focus.png>)

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

Eleanor Bennett · Thu, Sep 10

Sep 10, 10:00 AM · Raleigh, NC

Finding a caregiver
Request #4
Get help
Care story
Overview
Caregivers
Care details
Help

CHOOSE CAREGIVER

Caregivers are ready for review.

Compare profiles, chat if needed, then hire the person you trust.

Review 2 caregivers
Invite someone else
Your care request

To review

2

Interested caregivers

Invited

0

Direct invites

Schedule

Sep 10, 10:00 AM

Requested time

A helping hand for Ellie — Applicants

Companionship, preparing lunch, and light housekeeping.

Companionship
Meal preparation
Light housekeeping
View full care details and contacts

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
| H1 | Eleanor Bennett · Thu, Sep 10 |
| H2 | Caregivers are ready for review. |
| H2 | Your care request |

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
- **expanded:** null
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

#### 5. Care details

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

#### 6. Help

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

#### 7. Review 2 caregivers

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Review 2 caregivers
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 8. Invite someone else

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Invite someone else
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 9. View full care details and contacts

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** View full care details and contacts
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
| Care story | http://127.0.0.1:8028/family/care/journey/request/4 |
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
  "documentHeight": 1050,
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
    "heading": "Eleanor Bennett · Thu, Sep 10",
    "status": "Finding a caregiver",
    "headerText": "My care\n/\nOne-time care\n\nONE-TIME CARE\n\nEleanor Bennett · Thu, Sep 10\n\nSep 10, 10:00 AM · Raleigh, NC\n\nFinding a caregiver\nRequest #4\nGet help\nCare story",
    "primaryTitle": "Caregivers are ready for review.",
    "selectedTabs": [
      "Overview"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/4?tab=home"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 1485 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/4?tab=home

[Focused view](<../screenshots/OT-applicants-home-mobile-focus.png>) · [First screenful](<../screenshots/OT-applicants-home-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/OT-applicants-home-mobile-full.png>) · [Raw visible text](<../evidence/OT-applicants-home-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/OT-applicants-home-mobile.json>)

![Mobile screenshot of applicants — Overview](<../screenshots/OT-applicants-home-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
My care
/
One-time care

ONE-TIME CARE

Eleanor Bennett · Thu, Sep 10

Sep 10, 10:00 AM · Raleigh, NC

Finding a caregiver
Request #4
Get help
Care story
Overview
Caregivers
Care details
Help

CHOOSE CAREGIVER

Caregivers are ready for review.

Compare profiles, chat if needed, then hire the person you trust.

Review 2 caregivers
Invite someone else
Your care request

To review

2

Interested caregivers

Invited

0

Direct invites

Schedule

Sep 10, 10:00 AM

Requested time

A helping hand for Ellie — Applicants

Companionship, preparing lunch, and light housekeeping.

Companionship
Meal preparation
Light housekeeping
View full care details and contacts
Review caregivers

© 2026 LoLo

Legal links
```

### Visible headings

| Level | Heading |
|---|---|
| H1 | Eleanor Bennett · Thu, Sep 10 |
| H2 | Caregivers are ready for review. |
| H2 | Your care request |

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
- **expanded:** null
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

#### 5. Care details

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

#### 6. Help

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

#### 7. Review 2 caregivers

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Review 2 caregivers
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 8. Invite someone else

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Invite someone else
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 9. View full care details and contacts

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** View full care details and contacts
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 10. Review caregivers

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Review caregivers
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
| Care story | http://127.0.0.1:8028/family/care/journey/request/4 |

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
  "documentHeight": 1485,
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
    "heading": "Eleanor Bennett · Thu, Sep 10",
    "status": "Finding a caregiver",
    "headerText": "My care\n/\nOne-time care\n\nONE-TIME CARE\n\nEleanor Bennett · Thu, Sep 10\n\nSep 10, 10:00 AM · Raleigh, NC\n\nFinding a caregiver\nRequest #4\nGet help\nCare story",
    "primaryTitle": "Caregivers are ready for review.",
    "selectedTabs": [
      "Overview"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/4?tab=home"
}
```
