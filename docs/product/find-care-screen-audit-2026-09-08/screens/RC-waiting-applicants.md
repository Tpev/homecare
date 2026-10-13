# RC-waiting-applicants — waiting — Caregivers

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | Recurring care |
| Lifecycle stage | Waiting / inviting |
| Route | /family/requests/13?tab=applicants |
| Selected tab | Caregivers |
| Preserved route key | applicants |
| Panel / disclosure | default |
| Capture status | Captured |
| UX review | Implementation rationale; validation recorded separately |
| UX findings | A family comparing replies should reach candidates without navigating through a large invitation area. |
| Redesign decision | Replies lead when present; invitations lead when there are no replies. Filters, profiles, messages, invitation history and original eligibility rules remain available. |
| Owner | Local care workspace implementation |
| Priority |  |

These screenshots show the current application with synthetic audit data. This is capture and source-audit evidence; the local UI redesign is implemented. Remote map/payment widgets were blocked in this local capture session, so their current fallback states are preserved. Brand fonts are served locally.

## Additional capture context

```json
{
  "captureVersion": 5,
  "capturedAt": "2026-09-08T15:55:28.491Z",
  "buildHash": "58c0b4adc3736b3717582dffe837c3b2284299b26b123064150359543361e636",
  "prepared": false,
  "displayTab": "Caregivers"
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 1313 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/13?tab=applicants

[Focused view](<../screenshots/RC-waiting-applicants-desktop-focus.png>) · [First screenful](<../screenshots/RC-waiting-applicants-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/RC-waiting-applicants-desktop-full.png>) · [Raw visible text](<../evidence/RC-waiting-applicants-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/RC-waiting-applicants-desktop.json>)

![Desktop screenshot of waiting — Caregivers](<../screenshots/RC-waiting-applicants-desktop-focus.png>)

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
Recurring care

RECURRING CARE

Recurring care for Eleanor Bennett

Mon, Wed, Fri · 10:00 AM–12:00 PM · Raleigh, NC

Finding a caregiver
Request #13
Get help
Care story
Overview
Caregivers
Visits
Care details

SUGGESTED CAREGIVERS

Invite one or two caregivers

Your request is live. Inviting a strong match can help you get a reply faster.

LOOKING FOR A SPECIFIC PERSON?

Invite someone you know

Search by name and invite them without leaving this request or choosing the request again.

Search and invite
People you invited

Invitations are separate from caregivers who replied.

0 total
No active invitations yet.
Recommended caregivers

Suggestions based on this request’s schedule and location.

Certifications & training
Any
⌄

3 suggested caregivers found

AR

Aisha Robinson

Same ZIP service area - Match score 128

5.0
31 reviews
Identity verified
Background check
Top caregiver

Same ZIP service area - Recurring match 100%

Invite Aisha
SM

Sofia Martinez

Same ZIP service area - Match score 128

4.9
24 reviews
Identity verified
Background check
Top caregiver

Same ZIP service area - Recurring match 100%

Invite Sofia
JW

James Wilson

Same ZIP service area - Match score 128

4.8
18 reviews
Identity verified
Background check
Top caregiver

Same ZIP service area - Recurring match 100%

Invite James
After a caregiver replies, this screen changes to compare, chat, and hire.

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
| H2 | Invite one or two caregivers |
| H3 | Invite someone you know |
| H3 | People you invited |
| H3 | Recommended caregivers |

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

#### 7. Search and invite

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Search and invite
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 8. Invite Aisha

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Invite Aisha
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 9. Invite Sofia

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Invite Sofia
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 10. Invite James

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Invite James
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
| Care story | http://127.0.0.1:8028/family/care/journey/request/13 |
| Terms of Service | http://127.0.0.1:8028/legal/platform-terms-of-service |
| Privacy Policy | http://127.0.0.1:8028/legal/privacy-policy |
| Payments & Cancellation | http://127.0.0.1:8028/legal/payment-terms-and-cancellation-policy |
| Caregiver Terms | http://127.0.0.1:8028/legal/caregiver-terms |
| Family Terms | http://127.0.0.1:8028/legal/client-and-family-terms |
| Safety Policy | http://127.0.0.1:8028/legal/acceptable-use-and-safety-policy |
| All Legal Documents | http://127.0.0.1:8028/legal |

### Disclosure states

| Disclosure | State |
|---|---|
| Certifications & training<br>Any<br>⌄ | Closed |

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
  "documentHeight": 1313,
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
    "status": "Finding a caregiver",
    "headerText": "My care\n/\nRecurring care\n\nRECURRING CARE\n\nRecurring care for Eleanor Bennett\n\nMon, Wed, Fri · 10:00 AM–12:00 PM · Raleigh, NC\n\nFinding a caregiver\nRequest #13\nGet help\nCare story",
    "primaryTitle": "",
    "selectedTabs": [
      "Caregivers"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/13?tab=applicants"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 2181 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/13?tab=applicants

[Focused view](<../screenshots/RC-waiting-applicants-mobile-focus.png>) · [First screenful](<../screenshots/RC-waiting-applicants-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/RC-waiting-applicants-mobile-full.png>) · [Raw visible text](<../evidence/RC-waiting-applicants-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/RC-waiting-applicants-mobile.json>)

![Mobile screenshot of waiting — Caregivers](<../screenshots/RC-waiting-applicants-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
My care
/
Recurring care

RECURRING CARE

Recurring care for Eleanor Bennett

Mon, Wed, Fri · 10:00 AM–12:00 PM · Raleigh, NC

Finding a caregiver
Request #13
Get help
Care story
Overview
Caregivers
Visits
Care details

SUGGESTED CAREGIVERS

Invite one or two caregivers

Your request is live. Inviting a strong match can help you get a reply faster.

LOOKING FOR A SPECIFIC PERSON?

Invite someone you know

Search by name and invite them without leaving this request or choosing the request again.

Search and invite
People you invited

Invitations are separate from caregivers who replied.

0 total
No active invitations yet.
Recommended caregivers

Suggestions based on this request’s schedule and location.

Certifications & training
Any
⌄

3 suggested caregivers found

AR

Aisha Robinson

Same ZIP service area - Match score 128

5.0
31 reviews
Identity verified
Background check
Top caregiver

Same ZIP service area - Recurring match 100%

Invite Aisha
SM

Sofia Martinez

Same ZIP service area - Match score 128

4.9
24 reviews
Identity verified
Background check
Top caregiver

Same ZIP service area - Recurring match 100%

Invite Sofia
JW

James Wilson

Same ZIP service area - Match score 128

4.8
18 reviews
Identity verified
Background check
Top caregiver

Same ZIP service area - Recurring match 100%

Invite James
After a caregiver replies, this screen changes to compare, chat, and hire.

© 2026 LoLo

Legal links
```

### Visible headings

| Level | Heading |
|---|---|
| H1 | Recurring care for Eleanor Bennett |
| H2 | Invite one or two caregivers |
| H3 | Invite someone you know |
| H3 | People you invited |
| H3 | Recommended caregivers |

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

#### 7. Search and invite

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Search and invite
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 8. Invite Aisha

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Invite Aisha
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 9. Invite Sofia

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Invite Sofia
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 10. Invite James

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Invite James
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
| Care story | http://127.0.0.1:8028/family/care/journey/request/13 |

### Disclosure states

| Disclosure | State |
|---|---|
| Certifications & training<br>Any<br>⌄ | Closed |
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
  "documentHeight": 2181,
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
    "status": "Finding a caregiver",
    "headerText": "My care\n/\nRecurring care\n\nRECURRING CARE\n\nRecurring care for Eleanor Bennett\n\nMon, Wed, Fri · 10:00 AM–12:00 PM · Raleigh, NC\n\nFinding a caregiver\nRequest #13\nGet help\nCare story",
    "primaryTitle": "",
    "selectedTabs": [
      "Caregivers"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/13?tab=applicants"
}
```
