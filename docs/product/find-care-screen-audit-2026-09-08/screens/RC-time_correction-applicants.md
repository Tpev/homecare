# RC-time_correction-applicants — time correction — Caregivers

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | Recurring care |
| Lifecycle stage | Timesheet / review |
| Route | /family/requests/38?tab=applicants |
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
  "capturedAt": "2026-09-08T15:59:06.504Z",
  "buildHash": "58c0b4adc3736b3717582dffe837c3b2284299b26b123064150359543361e636",
  "prepared": false,
  "displayTab": "Caregivers"
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 1254 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/38?tab=applicants

[Focused view](<../screenshots/RC-time_correction-applicants-desktop-focus.png>) · [First screenful](<../screenshots/RC-time_correction-applicants-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/RC-time_correction-applicants-desktop-full.png>) · [Raw visible text](<../evidence/RC-time_correction-applicants-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/RC-time_correction-applicants-desktop.json>)

![Desktop screenshot of time correction — Caregivers](<../screenshots/RC-time_correction-applicants-desktop-focus.png>)

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
Back to recurring care · Visits
/
Visit #23

ONE VISIT IN YOUR RECURRING CARE

Eleanor Bennett · Mon, Sep 7

Sep 07, 2:51 AM - 4:51 AM · Raleigh, NC

Caregiver: Sofia Martinez

Completed
Visit #23
Message caregiver
Get help
Care story
This visit
Care instructions
Help & changes

CAREGIVER SELECTION

Selected caregiver

Sofia Martinez is selected. Keep profile and chat history here.

READY

0 to review

Filter or sort caregivers

1 caregivers match the applicant filters

Caregivers who replied

Review their reply, profile, or start a conversation.

SM

Sofia Martinez

Hired

Raleigh, NC

7 years experience
4.9
24 reviews
Reliability 98%
Profile details

I would be happy to help Ellie with lunch, a little tidying, and conversation. I am available for your requested schedule.

View profile
People you invited

Invitations are separate from caregivers who replied.

0 total
No active invitations yet.

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
| H1 | Eleanor Bennett · Mon, Sep 7 |
| H2 | Selected caregiver |
| H3 | Caregivers who replied |
| H3 | People you invited |

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

#### 2. Message caregiver

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Message caregiver
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 3. Get help

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

#### 4. This visit

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** This visit
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 5. Care instructions

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Care instructions
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 6. Help & changes

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Help & changes
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 7. View profile

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** View profile
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
| Back to recurring care · Visits | http://127.0.0.1:8028/family/care/7?tab=visits&visit\_filter=upcoming&visitsPage=1#care-visit-23 |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/7 |
| View profile | http://127.0.0.1:8028/caregivers/sofia-find-care-audit?careRequest=38 |
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
| Filter or sort caregivers | Closed |
| Profile details | Closed |

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
  "documentHeight": 1254,
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
    "heading": "Eleanor Bennett · Mon, Sep 7",
    "status": "Completed",
    "headerText": "My care\n/\nBack to recurring care · Visits\n/\nVisit #23\n\nONE VISIT IN YOUR RECURRING CARE\n\nEleanor Bennett · Mon, Sep 7\n\nSep 07, 2:51 AM - 4:51 AM · Raleigh, NC\n\nCaregiver: Sofia Martinez\n\nCompleted\nVisit #23\nMessage caregiver\nGet help\nCare story",
    "primaryTitle": "",
    "selectedTabs": []
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/38?tab=applicants"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 1582 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/38?tab=applicants

[Focused view](<../screenshots/RC-time_correction-applicants-mobile-focus.png>) · [First screenful](<../screenshots/RC-time_correction-applicants-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/RC-time_correction-applicants-mobile-full.png>) · [Raw visible text](<../evidence/RC-time_correction-applicants-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/RC-time_correction-applicants-mobile.json>)

![Mobile screenshot of time correction — Caregivers](<../screenshots/RC-time_correction-applicants-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
My care
/
Back to recurring care · Visits
/
Visit #23

ONE VISIT IN YOUR RECURRING CARE

Eleanor Bennett · Mon, Sep 7

Sep 07, 2:51 AM - 4:51 AM · Raleigh, NC

Caregiver: Sofia Martinez

Completed
Visit #23
Message caregiver
Get help
Care story
This visit
Care instructions
Help & changes

CAREGIVER SELECTION

Selected caregiver

Sofia Martinez is selected. Keep profile and chat history here.

READY

0 to review

Filter or sort caregivers

1 caregivers match the applicant filters

Caregivers who replied

Review their reply, profile, or start a conversation.

SM

Sofia Martinez

Hired

Raleigh, NC

7 years experience
4.9
24 reviews
Reliability 98%
Profile details

I would be happy to help Ellie with lunch, a little tidying, and conversation. I am available for your requested schedule.

View profile
People you invited

Invitations are separate from caregivers who replied.

0 total
No active invitations yet.

© 2026 LoLo

Legal links
```

### Visible headings

| Level | Heading |
|---|---|
| H1 | Eleanor Bennett · Mon, Sep 7 |
| H2 | Selected caregiver |
| H3 | Caregivers who replied |
| H3 | People you invited |

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

#### 2. Message caregiver

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Message caregiver
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 3. Get help

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

#### 4. This visit

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** This visit
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 5. Care instructions

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Care instructions
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 6. Help & changes

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Help & changes
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 7. View profile

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** View profile
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
| Back to recurring care · Visits | http://127.0.0.1:8028/family/care/7?tab=visits&visit\_filter=upcoming&visitsPage=1#care-visit-23 |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/7 |
| View profile | http://127.0.0.1:8028/caregivers/sofia-find-care-audit?careRequest=38 |

### Disclosure states

| Disclosure | State |
|---|---|
| Filter or sort caregivers | Closed |
| Profile details | Closed |
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
  "documentHeight": 1582,
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
    "heading": "Eleanor Bennett · Mon, Sep 7",
    "status": "Completed",
    "headerText": "My care\n/\nBack to recurring care · Visits\n/\nVisit #23\n\nONE VISIT IN YOUR RECURRING CARE\n\nEleanor Bennett · Mon, Sep 7\n\nSep 07, 2:51 AM - 4:51 AM · Raleigh, NC\n\nCaregiver: Sofia Martinez\n\nCompleted\nVisit #23\nMessage caregiver\nGet help\nCare story",
    "primaryTitle": "",
    "selectedTabs": []
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/38?tab=applicants"
}
```
