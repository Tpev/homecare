# OT-inline-support-visit — Inline Help — support chat open

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | One-time care |
| Lifecycle stage | Timesheet / review |
| Route | /family/requests/8?tab=support |
| Selected tab | Help |
| Preserved route key | support |
| Panel / disclosure | support chat |
| Capture status | Captured |
| UX review | Implementation rationale; validation recorded separately |
| UX findings | Help must remain reachable without covering the mobile decision button. |
| Redesign decision | The inline entry opens the existing support dialog. Context and support behavior remain unchanged. |
| Owner | Local care workspace implementation |
| Priority |  |

These screenshots show the current application with synthetic audit data. This is capture and source-audit evidence; the local UI redesign is implemented. Remote map/payment widgets were blocked in this local capture session, so their current fallback states are preserved. Brand fonts are served locally.

## Additional capture context

```json
{
  "addedByRedesign": true,
  "captureVersion": 5,
  "capturedAt": "2026-09-08T15:59:27.468Z",
  "buildHash": "58c0b4adc3736b3717582dffe837c3b2284299b26b123064150359543361e636",
  "prepared": true,
  "displayTab": "Help"
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 1206 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/8?tab=support

[Focused view](<../screenshots/OT-inline-support-visit-desktop-focus.png>) · [First screenful](<../screenshots/OT-inline-support-visit-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/OT-inline-support-visit-desktop-full.png>) · [Raw visible text](<../evidence/OT-inline-support-visit-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/OT-inline-support-visit-desktop.json>)

![Desktop screenshot of Inline Help — support chat open](<../screenshots/OT-inline-support-visit-desktop-focus.png>)

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

Eleanor Bennett · Mon, Sep 7

Sep 07, 2:51 AM - 4:51 AM · Raleigh, NC

Caregiver: Sofia Martinez

Completed
Visit #4
Message caregiver
Get help
Care story
Overview
Caregivers
Care details
Help

Need a hand? Chat with support or use the visit options below.

Chat with support

TIMESHEET HELP

Question hours or payment

If the submitted hours do not look right, ask for help before approving payment.

Question the submitted hours

Use this before approving if the worked time, location, or payment amount looks wrong.

What looks wrong?
Open dispute
Support ticket
Report incident

© 2026 LoLo Care Inc

﻿
Terms of Service
Privacy Policy
Payments & Cancellation
Caregiver Terms
Family Terms
Safety Policy
All Legal Documents
LoLo Support

Leave us a message

Hi Maria. How can we help?

Send your question and a LoLo support team member will reply here.

Message LoLo Support
Send

Enter sends. Shift+Enter starts a new line.

Open Support Center

Emergency? Call 911.

Support chat opened.
```

### Visible headings

| Level | Heading |
|---|---|
| H1 | Eleanor Bennett · Mon, Sep 7 |
| H2 | Question hours or payment |
| H3 | Question the submitted hours |
| H2 | LoLo Support |
| H3 | Hi Maria. How can we help? |

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

#### 4. Overview

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

#### 5. Caregivers

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

#### 7. Help

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Help
- **value:** (empty)
- **disabled:** false
- **current:** page
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

#### 9. What looks wrong?

- **tag:** TEXTAREA
- **type:** textarea
- **label:** What looks wrong?
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 10. Open dispute

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Open dispute
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 11. Minimize support chat

- **tag:** BUTTON
- **type:** button
- **label:** Minimize support chat
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 12. Message LoLo Support

- **tag:** TEXTAREA
- **type:** textarea
- **label:** Message LoLo Support
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** Ask us a question…

#### 13. Send message to LoLo Support

- **tag:** BUTTON
- **type:** submit
- **label:** Send message to LoLo Support
- **text:** Send
- **value:** (empty)
- **disabled:** true
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
| Care story | http://127.0.0.1:8028/family/care/journey/request/8 |
| Terms of Service | http://127.0.0.1:8028/legal/platform-terms-of-service |
| Privacy Policy | http://127.0.0.1:8028/legal/privacy-policy |
| Payments & Cancellation | http://127.0.0.1:8028/legal/payment-terms-and-cancellation-policy |
| Caregiver Terms | http://127.0.0.1:8028/legal/caregiver-terms |
| Family Terms | http://127.0.0.1:8028/legal/client-and-family-terms |
| Safety Policy | http://127.0.0.1:8028/legal/acceptable-use-and-safety-policy |
| All Legal Documents | http://127.0.0.1:8028/legal |
| Open Support Center | http://127.0.0.1:8028/support |

### Disclosure states

| Disclosure | State |
|---|---|
| Support ticket | Closed |
| Report incident | Closed |

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
  "documentHeight": 1206,
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
    "headerText": "My care\n/\nOne-time care\n\nONE-TIME CARE\n\nEleanor Bennett · Mon, Sep 7\n\nSep 07, 2:51 AM - 4:51 AM · Raleigh, NC\n\nCaregiver: Sofia Martinez\n\nCompleted\nVisit #4\nMessage caregiver\nGet help\nCare story",
    "primaryTitle": "",
    "selectedTabs": [
      "Help"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/8?tab=support"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 1416 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/8?tab=support

[Focused view](<../screenshots/OT-inline-support-visit-mobile-focus.png>) · [First screenful](<../screenshots/OT-inline-support-visit-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/OT-inline-support-visit-mobile-full.png>) · [Raw visible text](<../evidence/OT-inline-support-visit-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/OT-inline-support-visit-mobile.json>)

![Mobile screenshot of Inline Help — support chat open](<../screenshots/OT-inline-support-visit-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
My care
/
One-time care

ONE-TIME CARE

Eleanor Bennett · Mon, Sep 7

Sep 07, 2:51 AM - 4:51 AM · Raleigh, NC

Caregiver: Sofia Martinez

Completed
Visit #4
Message caregiver
Get help
Care story
Overview
Caregivers
Care details
Help

Need a hand? Chat with support or use the visit options below.

Chat with support

TIMESHEET HELP

Question hours or payment

If the submitted hours do not look right, ask for help before approving payment.

Question the submitted hours

Use this before approving if the worked time, location, or payment amount looks wrong.

What looks wrong?
Open dispute
Support ticket
Report incident

© 2026 LoLo

Legal links
LoLo Support

Leave us a message

Hi Maria. How can we help?

Send your question and a LoLo support team member will reply here.

Message LoLo Support
Send

Enter sends. Shift+Enter starts a new line.

Open Support Center

Emergency? Call 911.

Support chat opened.
```

### Visible headings

| Level | Heading |
|---|---|
| H1 | Eleanor Bennett · Mon, Sep 7 |
| H2 | Question hours or payment |
| H3 | Question the submitted hours |
| H2 | LoLo Support |
| H3 | Hi Maria. How can we help? |

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

#### 4. Overview

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

#### 5. Caregivers

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

#### 7. Help

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Help
- **value:** (empty)
- **disabled:** false
- **current:** page
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

#### 9. What looks wrong?

- **tag:** TEXTAREA
- **type:** textarea
- **label:** What looks wrong?
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 10. Open dispute

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Open dispute
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 11. Minimize support chat

- **tag:** BUTTON
- **type:** button
- **label:** Minimize support chat
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 12. Message LoLo Support

- **tag:** TEXTAREA
- **type:** textarea
- **label:** Message LoLo Support
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** Ask us a question…

#### 13. Send message to LoLo Support

- **tag:** BUTTON
- **type:** submit
- **label:** Send message to LoLo Support
- **text:** Send
- **value:** (empty)
- **disabled:** true
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
| Care story | http://127.0.0.1:8028/family/care/journey/request/8 |
| Open Support Center | http://127.0.0.1:8028/support |

### Disclosure states

| Disclosure | State |
|---|---|
| Support ticket | Closed |
| Report incident | Closed |
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
  "documentHeight": 1416,
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
    "headerText": "My care\n/\nOne-time care\n\nONE-TIME CARE\n\nEleanor Bennett · Mon, Sep 7\n\nSep 07, 2:51 AM - 4:51 AM · Raleigh, NC\n\nCaregiver: Sofia Martinez\n\nCompleted\nVisit #4\nMessage caregiver\nGet help\nCare story",
    "primaryTitle": "",
    "selectedTabs": [
      "Help"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/8?tab=support"
}
```
