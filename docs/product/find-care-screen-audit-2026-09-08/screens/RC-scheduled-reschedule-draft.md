# RC-scheduled-reschedule-draft — Help — reschedule fields filled before sending

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | Recurring care |
| Lifecycle stage | Hired / before shift |
| Route | /family/requests/16?tab=support |
| Selected tab | Help & changes |
| Preserved route key | support |
| Panel / disclosure | reschedule draft |
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
  "capturedAt": "2026-09-08T15:40:03.126Z",
  "buildHash": "58c0b4adc3736b3717582dffe837c3b2284299b26b123064150359543361e636",
  "prepared": true,
  "displayTab": "Help & changes"
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 1464 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/16?tab=support

[Focused view](<../screenshots/RC-scheduled-reschedule-draft-desktop-focus.png>) · [First screenful](<../screenshots/RC-scheduled-reschedule-draft-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/RC-scheduled-reschedule-draft-desktop-full.png>) · [Raw visible text](<../evidence/RC-scheduled-reschedule-draft-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/RC-scheduled-reschedule-draft-desktop.json>)

![Desktop screenshot of Help — reschedule fields filled before sending](<../screenshots/RC-scheduled-reschedule-draft-desktop-focus.png>)

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
Visit #8

ONE VISIT IN YOUR RECURRING CARE

Eleanor Bennett · Wed, Sep 9

Sep 09, 10:00 AM - 12:00 PM · Raleigh, NC

Caregiver: Sofia Martinez

Scheduled
Visit #8
Message caregiver
Get help
Care story
This visit
Care instructions
Help & changes

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
Proposed start
Proposed end
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
- **current:** page
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 7. Chat with support

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

#### 8. Change type

- **tag:** SELECT
- **type:** select-one
- **label:** Change type
- **text:** (empty)
- **value:** reschedule
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null
- **Dropdown options:**
  - Cancel visit — value: cancel; selected: false
  - Reschedule visit — value: reschedule; selected: true

#### 9. Reason

- **tag:** TEXTAREA
- **type:** textarea
- **label:** Reason
- **text:** (empty)
- **value:** A family appointment changed. Would the next morning work?
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 10. Proposed start

- **tag:** INPUT
- **type:** datetime-local
- **label:** Proposed start
- **text:** (empty)
- **value:** 2026-09-15T10:00
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 11. Proposed end

- **tag:** INPUT
- **type:** datetime-local
- **label:** Proposed end
- **text:** (empty)
- **value:** 2026-09-15T12:00
- **checked:** false
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
| Back to recurring care · Visits | http://127.0.0.1:8028/family/care/1?tab=visits&visit\_filter=upcoming&visitsPage=1#care-visit-8 |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/1 |
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
  "documentHeight": 1464,
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
    "headerText": "My care\n/\nBack to recurring care · Visits\n/\nVisit #8\n\nONE VISIT IN YOUR RECURRING CARE\n\nEleanor Bennett · Wed, Sep 9\n\nSep 09, 10:00 AM - 12:00 PM · Raleigh, NC\n\nCaregiver: Sofia Martinez\n\nScheduled\nVisit #8\nMessage caregiver\nGet help\nCare story",
    "primaryTitle": "",
    "selectedTabs": [
      "Help & changes"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/16?tab=support"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 1768 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/16?tab=support

[Focused view](<../screenshots/RC-scheduled-reschedule-draft-mobile-focus.png>) · [First screenful](<../screenshots/RC-scheduled-reschedule-draft-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/RC-scheduled-reschedule-draft-mobile-full.png>) · [Raw visible text](<../evidence/RC-scheduled-reschedule-draft-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/RC-scheduled-reschedule-draft-mobile.json>)

![Mobile screenshot of Help — reschedule fields filled before sending](<../screenshots/RC-scheduled-reschedule-draft-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
My care
/
Back to recurring care · Visits
/
Visit #8

ONE VISIT IN YOUR RECURRING CARE

Eleanor Bennett · Wed, Sep 9

Sep 09, 10:00 AM - 12:00 PM · Raleigh, NC

Caregiver: Sofia Martinez

Scheduled
Visit #8
Message caregiver
Get help
Care story
This visit
Care instructions
Help & changes

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
Proposed start
Proposed end
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
- **current:** page
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 7. Chat with support

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

#### 8. Change type

- **tag:** SELECT
- **type:** select-one
- **label:** Change type
- **text:** (empty)
- **value:** reschedule
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null
- **Dropdown options:**
  - Cancel visit — value: cancel; selected: false
  - Reschedule visit — value: reschedule; selected: true

#### 9. Reason

- **tag:** TEXTAREA
- **type:** textarea
- **label:** Reason
- **text:** (empty)
- **value:** A family appointment changed. Would the next morning work?
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 10. Proposed start

- **tag:** INPUT
- **type:** datetime-local
- **label:** Proposed start
- **text:** (empty)
- **value:** 2026-09-15T10:00
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 11. Proposed end

- **tag:** INPUT
- **type:** datetime-local
- **label:** Proposed end
- **text:** (empty)
- **value:** 2026-09-15T12:00
- **checked:** false
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
| Back to recurring care · Visits | http://127.0.0.1:8028/family/care/1?tab=visits&visit\_filter=upcoming&visitsPage=1#care-visit-8 |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/1 |

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
  "documentHeight": 1768,
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
    "headerText": "My care\n/\nBack to recurring care · Visits\n/\nVisit #8\n\nONE VISIT IN YOUR RECURRING CARE\n\nEleanor Bennett · Wed, Sep 9\n\nSep 09, 10:00 AM - 12:00 PM · Raleigh, NC\n\nCaregiver: Sofia Martinez\n\nScheduled\nVisit #8\nMessage caregiver\nGet help\nCare story",
    "primaryTitle": "",
    "selectedTabs": [
      "Help & changes"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/16?tab=support"
}
```
