# RC-in_progress-shift-expanded — in progress — shift / all details open

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | Recurring care |
| Lifecycle stage | During shift |
| Route | /family/requests/17?tab=shift |
| Selected tab | This visit |
| Preserved route key | shift |
| Panel / disclosure | expanded |
| Capture status | Captured |
| UX review | Implementation rationale; validation recorded separately |
| UX findings | The dated visit is the correct scope for care, hours, payment and feedback. |
| Redesign decision | The compact header retains exact identity and status. Relevant actions lead, with the existing detailed record, corrections, payment information and review fields still accessible. |
| Owner | Local care workspace implementation |
| Priority |  |

These screenshots show the current application with synthetic audit data. This is capture and source-audit evidence; the local UI redesign is implemented. Remote map/payment widgets were blocked in this local capture session, so their current fallback states are preserved. Brand fonts are served locally.

## Additional capture context

```json
{
  "captureVersion": 5,
  "capturedAt": "2026-09-08T15:32:19.036Z",
  "buildHash": "58c0b4adc3736b3717582dffe837c3b2284299b26b123064150359543361e636",
  "prepared": false,
  "displayTab": "This visit"
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 2054 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/17?tab=shift

[Focused view](<../screenshots/RC-in_progress-shift-expanded-desktop-focus.png>) · [First screenful](<../screenshots/RC-in_progress-shift-expanded-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/RC-in_progress-shift-expanded-desktop-full.png>) · [Raw visible text](<../evidence/RC-in_progress-shift-expanded-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/RC-in_progress-shift-expanded-desktop.json>)

![Desktop screenshot of in progress — shift / all details open](<../screenshots/RC-in_progress-shift-expanded-desktop-focus.png>)

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
Visit #9

ONE VISIT IN YOUR RECURRING CARE

Eleanor Bennett · Mon, Sep 7

Sep 07, 7:56 AM - 9:56 AM · Raleigh, NC

Caregiver: Sofia Martinez

In progress
Visit #9
Message caregiver
Get help
Care story
This visit
Care instructions
Help & changes

VISIT IN PROGRESS

Care is happening now.

Sofia checked in at 7:56 AM.

Changes and approvals here apply to this dated visit. Your recurring schedule has its own controls.

The visit has ended
Important for this visit
Full care instructions
Safety & support
Fall risk
Keep the hallway clear and remind Ellie to use the handrail.
Home access

Use the main entrance and ring apartment 2B. Maria will meet you for the first visit.

Care contact

Daniel Bennett

+1 919 555 0153

daniel.find-care-audit@example.com

LIVE DETAILS

Live visit details

Schedule, check-in, location, and payment stay here while live actions stay above.

In progress

LIVE CHECK-IN

Sofia is with Eleanor Bennett.

TIME

Sep 07

7:56 AM - 9:56 AM

CAREGIVER

Sofia Martinez

Checked in 7:56 AM

PAYMENT

Authorized

Authorized $62.00

CARE LOCATION

142 Maple Grove Lane, Apartment 2B, Raleigh, NC 27601

Open map
Eleanor Bennett - Mother

Checked in

Sofia checked in Sep 07, 7:56 AM.

GPS was captured at check-in.

Map and visit record

Started: Sep 07, 07:56

Completed: Pending

Payment: AUTHORIZED

Care during this visit
1 of 3 tasks done
Care tasks & notes

To do · Meal preparation

Prepare soup and a sandwich for lunch; leave the kitchen tidy.

To do · Light housekeeping

Wash dishes and keep the living room and hallway clear.

Completed · Companionship

Conversation, a crossword puzzle, and a short walk if Ellie feels up to it.

Visit activity

Shift started by caregiver

Sep 7, 7:56 AM · Sofia Martinez

Booking hired

Sep 6, 7:56 AM · Maria Bennett

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
| H2 | Care is happening now. |
| H2 | Important for this visit |
| H3 | Safety & support |
| H3 | Home access |
| H3 | Care contact |
| H2 | Live visit details |
| H3 | Sofia is with Eleanor Bennett. |
| H2 | Care during this visit |
| H3 | Care tasks & notes |
| H3 | Visit activity |

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
- **current:** page
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

#### 7. The visit has ended

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** The visit has ended
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 8. Full care instructions

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Full care instructions
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
| Back to recurring care · Visits | http://127.0.0.1:8028/family/care/1?tab=visits&visit\_filter=upcoming&visitsPage=1#care-visit-9 |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/1 |
| +1 919 555 0153 | tel:+1 919 555 0153 |
| daniel.find-care-audit@example.com | mailto:daniel.find-care-audit@example.com |
| Open map | https://www.google.com/maps/search/?api=1&query=142%20Maple%20Grove%20Lane%2C%20Apartment%202B%2C%20Raleigh%2C%20NC%2027601 |
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
| Map and visit record | Open |

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
  "documentHeight": 2054,
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
    "status": "In progress",
    "headerText": "My care\n/\nBack to recurring care · Visits\n/\nVisit #9\n\nONE VISIT IN YOUR RECURRING CARE\n\nEleanor Bennett · Mon, Sep 7\n\nSep 07, 7:56 AM - 9:56 AM · Raleigh, NC\n\nCaregiver: Sofia Martinez\n\nIn progress\nVisit #9\nMessage caregiver\nGet help\nCare story",
    "primaryTitle": "Care is happening now.",
    "selectedTabs": [
      "This visit"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/17?tab=shift"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 3288 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/17?tab=shift

[Focused view](<../screenshots/RC-in_progress-shift-expanded-mobile-focus.png>) · [First screenful](<../screenshots/RC-in_progress-shift-expanded-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/RC-in_progress-shift-expanded-mobile-full.png>) · [Raw visible text](<../evidence/RC-in_progress-shift-expanded-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/RC-in_progress-shift-expanded-mobile.json>)

![Mobile screenshot of in progress — shift / all details open](<../screenshots/RC-in_progress-shift-expanded-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
My care
/
Back to recurring care · Visits
/
Visit #9

ONE VISIT IN YOUR RECURRING CARE

Eleanor Bennett · Mon, Sep 7

Sep 07, 7:56 AM - 9:56 AM · Raleigh, NC

Caregiver: Sofia Martinez

In progress
Visit #9
Message caregiver
Get help
Care story
This visit
Care instructions
Help & changes

VISIT IN PROGRESS

Care is happening now.

Sofia checked in at 7:56 AM.

Changes and approvals here apply to this dated visit. Your recurring schedule has its own controls.

The visit has ended
Important for this visit
Full care instructions
Safety & support
Fall risk
Keep the hallway clear and remind Ellie to use the handrail.
Home access

Use the main entrance and ring apartment 2B. Maria will meet you for the first visit.

Care contact

Daniel Bennett

+1 919 555 0153

daniel.find-care-audit@example.com

LIVE DETAILS

Live visit details

Schedule, check-in, location, and payment stay here while live actions stay above.

In progress

LIVE CHECK-IN

Sofia is with Eleanor Bennett.

TIME

Sep 07

7:56 AM - 9:56 AM

CAREGIVER

Sofia Martinez

Checked in 7:56 AM

PAYMENT

Authorized

Authorized $62.00

CARE LOCATION

142 Maple Grove Lane, Apartment 2B, Raleigh, NC 27601

Open map
Eleanor Bennett - Mother

Checked in

Sofia checked in Sep 07, 7:56 AM.

GPS was captured at check-in.

Map and visit record

Started: Sep 07, 07:56

Completed: Pending

Payment: AUTHORIZED

Care during this visit
1 of 3 tasks done
Care tasks & notes

To do · Meal preparation

Prepare soup and a sandwich for lunch; leave the kitchen tidy.

To do · Light housekeeping

Wash dishes and keep the living room and hallway clear.

Completed · Companionship

Conversation, a crossword puzzle, and a short walk if Ellie feels up to it.

Visit activity

Shift started by caregiver

Sep 7, 7:56 AM · Sofia Martinez

Booking hired

Sep 6, 7:56 AM · Maria Bennett

The visit has ended

© 2026 LoLo

Legal links
```

### Visible headings

| Level | Heading |
|---|---|
| H1 | Eleanor Bennett · Mon, Sep 7 |
| H2 | Care is happening now. |
| H2 | Important for this visit |
| H3 | Safety & support |
| H3 | Home access |
| H3 | Care contact |
| H2 | Live visit details |
| H3 | Sofia is with Eleanor Bennett. |
| H2 | Care during this visit |
| H3 | Care tasks & notes |
| H3 | Visit activity |

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
- **current:** page
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

#### 7. The visit has ended

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** The visit has ended
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 8. Full care instructions

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Full care instructions
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 9. The visit has ended

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** The visit has ended
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
| Back to recurring care · Visits | http://127.0.0.1:8028/family/care/1?tab=visits&visit\_filter=upcoming&visitsPage=1#care-visit-9 |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/1 |
| +1 919 555 0153 | tel:+1 919 555 0153 |
| daniel.find-care-audit@example.com | mailto:daniel.find-care-audit@example.com |
| Open map | https://www.google.com/maps/search/?api=1&query=142%20Maple%20Grove%20Lane%2C%20Apartment%202B%2C%20Raleigh%2C%20NC%2027601 |

### Disclosure states

| Disclosure | State |
|---|---|
| Map and visit record | Open |
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
  "documentHeight": 3288,
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
    "status": "In progress",
    "headerText": "My care\n/\nBack to recurring care · Visits\n/\nVisit #9\n\nONE VISIT IN YOUR RECURRING CARE\n\nEleanor Bennett · Mon, Sep 7\n\nSep 07, 7:56 AM - 9:56 AM · Raleigh, NC\n\nCaregiver: Sofia Martinez\n\nIn progress\nVisit #9\nMessage caregiver\nGet help\nCare story",
    "primaryTitle": "Care is happening now.",
    "selectedTabs": [
      "This visit"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/17?tab=shift"
}
```
