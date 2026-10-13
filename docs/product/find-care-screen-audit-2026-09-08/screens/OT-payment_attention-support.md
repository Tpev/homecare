# OT-payment_attention-support — payment attention — Help

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | One-time care |
| Lifecycle stage | Hired / before shift |
| Route | /family/requests/31?tab=support |
| Selected tab | Help |
| Preserved route key | support |
| Panel / disclosure | default |
| Capture status | Captured |
| UX review | Implementation rationale; validation recorded separately |
| UX findings | Changes, hours questions and disputes need the context of the affected visit. |
| Redesign decision | Existing contextual forms and support actions remain on this record. They do not silently apply a change to the whole recurring arrangement. |
| Owner | Local care workspace implementation |
| Priority |  |

These screenshots show the current application with synthetic audit data. This is capture and source-audit evidence; the local UI redesign is implemented. Remote map/payment widgets were blocked in this local capture session, so their current fallback states are preserved. Brand fonts are served locally.

## Additional capture context

```json
{
  "captureVersion": 5,
  "capturedAt": "2026-09-08T15:37:52.106Z",
  "buildHash": "58c0b4adc3736b3717582dffe837c3b2284299b26b123064150359543361e636",
  "prepared": false,
  "displayTab": "Help"
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 1480 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/31?tab=support

[Focused view](<../screenshots/OT-payment_attention-support-desktop-focus.png>) · [First screenful](<../screenshots/OT-payment_attention-support-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/OT-payment_attention-support-desktop-full.png>) · [Raw visible text](<../evidence/OT-payment_attention-support-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/OT-payment_attention-support-desktop.json>)

![Desktop screenshot of payment attention — Help](<../screenshots/OT-payment_attention-support-desktop-focus.png>)

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

Eleanor Bennett · Wed, Sep 9

Sep 09, 10:00 AM - 12:00 PM · Raleigh, NC

Caregiver: Sofia Martinez

Scheduled
Visit #19
Message caregiver
Get help
Care story
Overview
Caregivers
Care details
Help

Card authorization needs attention.

Your card was declined. Update your payment method to keep this visit on track.

Confirm card authorization

Need a hand? Chat with support or use the visit options below.

Chat with support

BEFORE THE VISIT

Change or cancel this visit

Use this before caregiver check-in if the time no longer works or you need to cancel.

Request cancellation or reschedule

Use this for schedule changes before the caregiver checks in.

Before check-in
Change type
Cancel visit
Reschedule visit
Reason
Send request
Need to cancel now?
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
```

### Visible headings

| Level | Heading |
|---|---|
| H1 | Eleanor Bennett · Wed, Sep 9 |
| H2 | Change or cancel this visit |
| H3 | Request cancellation or reschedule |

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

#### 8. Confirm card authorization

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Confirm card authorization
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 9. Chat with support

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

#### 10. Change type

- **tag:** SELECT
- **type:** select-one
- **label:** Change type
- **text:** (empty)
- **value:** cancel
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null
- **Dropdown options:**
  - Cancel visit — value: cancel; selected: true
  - Reschedule visit — value: reschedule; selected: false

#### 11. Reason

- **tag:** TEXTAREA
- **type:** textarea
- **label:** Reason
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 12. Send request

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Send request
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
| Care story | http://127.0.0.1:8028/family/care/journey/request/31 |
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
| Need to cancel now? | Closed |
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
  "documentHeight": 1480,
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
    "heading": "Eleanor Bennett · Wed, Sep 9",
    "status": "Scheduled",
    "headerText": "My care\n/\nOne-time care\n\nONE-TIME CARE\n\nEleanor Bennett · Wed, Sep 9\n\nSep 09, 10:00 AM - 12:00 PM · Raleigh, NC\n\nCaregiver: Sofia Martinez\n\nScheduled\nVisit #19\nMessage caregiver\nGet help\nCare story",
    "primaryTitle": "",
    "selectedTabs": [
      "Help"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/31?tab=support"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 1772 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/31?tab=support

[Focused view](<../screenshots/OT-payment_attention-support-mobile-focus.png>) · [First screenful](<../screenshots/OT-payment_attention-support-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/OT-payment_attention-support-mobile-full.png>) · [Raw visible text](<../evidence/OT-payment_attention-support-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/OT-payment_attention-support-mobile.json>)

![Mobile screenshot of payment attention — Help](<../screenshots/OT-payment_attention-support-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
My care
/
One-time care

ONE-TIME CARE

Eleanor Bennett · Wed, Sep 9

Sep 09, 10:00 AM - 12:00 PM · Raleigh, NC

Caregiver: Sofia Martinez

Scheduled
Visit #19
Message caregiver
Get help
Care story
Overview
Caregivers
Care details
Help

Card authorization needs attention.

Your card was declined. Update your payment method to keep this visit on track.

Confirm card authorization

Need a hand? Chat with support or use the visit options below.

Chat with support

BEFORE THE VISIT

Change or cancel this visit

Use this before caregiver check-in if the time no longer works or you need to cancel.

Request cancellation or reschedule

Use this for schedule changes before the caregiver checks in.

Before check-in
Change type
Cancel visit
Reschedule visit
Reason
Send request
Need to cancel now?
Support ticket
Report incident

© 2026 LoLo

Legal links
```

### Visible headings

| Level | Heading |
|---|---|
| H1 | Eleanor Bennett · Wed, Sep 9 |
| H2 | Change or cancel this visit |
| H3 | Request cancellation or reschedule |

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

#### 8. Confirm card authorization

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Confirm card authorization
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 9. Chat with support

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

#### 10. Change type

- **tag:** SELECT
- **type:** select-one
- **label:** Change type
- **text:** (empty)
- **value:** cancel
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null
- **Dropdown options:**
  - Cancel visit — value: cancel; selected: true
  - Reschedule visit — value: reschedule; selected: false

#### 11. Reason

- **tag:** TEXTAREA
- **type:** textarea
- **label:** Reason
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 12. Send request

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Send request
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
| Care story | http://127.0.0.1:8028/family/care/journey/request/31 |

### Disclosure states

| Disclosure | State |
|---|---|
| Need to cancel now? | Closed |
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
  "documentHeight": 1772,
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
    "heading": "Eleanor Bennett · Wed, Sep 9",
    "status": "Scheduled",
    "headerText": "My care\n/\nOne-time care\n\nONE-TIME CARE\n\nEleanor Bennett · Wed, Sep 9\n\nSep 09, 10:00 AM - 12:00 PM · Raleigh, NC\n\nCaregiver: Sofia Martinez\n\nScheduled\nVisit #19\nMessage caregiver\nGet help\nCare story",
    "primaryTitle": "",
    "selectedTabs": [
      "Help"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/31?tab=support"
}
```
