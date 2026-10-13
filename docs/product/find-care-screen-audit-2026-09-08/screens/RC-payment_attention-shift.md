# RC-payment_attention-shift — payment attention — This visit

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | Recurring care |
| Lifecycle stage | Hired / before shift |
| Route | /family/requests/37?tab=shift |
| Selected tab | This visit |
| Preserved route key | shift |
| Panel / disclosure | default |
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
  "capturedAt": "2026-09-08T15:40:57.297Z",
  "buildHash": "58c0b4adc3736b3717582dffe837c3b2284299b26b123064150359543361e636",
  "prepared": false,
  "displayTab": "This visit"
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 1541 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/37?tab=shift

[Focused view](<../screenshots/RC-payment_attention-shift-desktop-focus.png>) · [First screenful](<../screenshots/RC-payment_attention-shift-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/RC-payment_attention-shift-desktop-full.png>) · [Raw visible text](<../evidence/RC-payment_attention-shift-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/RC-payment_attention-shift-desktop.json>)

![Desktop screenshot of payment attention — This visit](<../screenshots/RC-payment_attention-shift-desktop-focus.png>)

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
Visit #22

ONE VISIT IN YOUR RECURRING CARE

Eleanor Bennett · Wed, Sep 9

Sep 09, 10:00 AM - 12:00 PM · Raleigh, NC

Caregiver: Sofia Martinez

Scheduled
Visit #22
Message caregiver
Get help
Care story
This visit
Care instructions
Help & changes

VISIT SCHEDULED

Your visit is scheduled.

Sofia is coming on Sep 09, 10:00 AM - 12:00 PM.

Changes and approvals here apply to this dated visit. Your recurring schedule has its own controls.

Change or cancel this visit
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

Card authorization needs attention.

Your card was declined. Update your payment method to keep this visit on track.

Confirm card authorization

VISIT ESSENTIALS

Before the visit

Keep the caregiver, time, location, and late-arrival guidance in one simple place.

Scheduled

VISIT PLAN

Sofia is scheduled to come.

TIME

Sep 09

10:00 AM - 12:00 PM

CAREGIVER

Sofia Martinez

Check-in pending

PAYMENT

Failed

Authorized $62.00

CARE LOCATION

142 Maple Grove Lane, Apartment 2B, Raleigh, NC 27601

Open map
Eleanor Bennett - Mother
If the caregiver is late
Map and visit record

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
| H2 | Your visit is scheduled. |
| H2 | Important for this visit |
| H3 | Safety & support |
| H3 | Home access |
| H3 | Care contact |
| H2 | Before the visit |
| H3 | Sofia is scheduled to come. |

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

#### 7. Change or cancel this visit

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Change or cancel this visit
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

#### 9. Confirm card authorization

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
| Back to recurring care · Visits | http://127.0.0.1:8028/family/care/6?tab=visits&visit\_filter=upcoming&visitsPage=1#care-visit-22 |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/6 |
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
| If the caregiver is late | Closed |
| Map and visit record | Closed |

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
  "documentHeight": 1541,
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
    "headerText": "My care\n/\nBack to recurring care · Visits\n/\nVisit #22\n\nONE VISIT IN YOUR RECURRING CARE\n\nEleanor Bennett · Wed, Sep 9\n\nSep 09, 10:00 AM - 12:00 PM · Raleigh, NC\n\nCaregiver: Sofia Martinez\n\nScheduled\nVisit #22\nMessage caregiver\nGet help\nCare story",
    "primaryTitle": "Your visit is scheduled.",
    "selectedTabs": [
      "This visit"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/37?tab=shift"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 2485 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/37?tab=shift

[Focused view](<../screenshots/RC-payment_attention-shift-mobile-focus.png>) · [First screenful](<../screenshots/RC-payment_attention-shift-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/RC-payment_attention-shift-mobile-full.png>) · [Raw visible text](<../evidence/RC-payment_attention-shift-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/RC-payment_attention-shift-mobile.json>)

![Mobile screenshot of payment attention — This visit](<../screenshots/RC-payment_attention-shift-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
My care
/
Back to recurring care · Visits
/
Visit #22

ONE VISIT IN YOUR RECURRING CARE

Eleanor Bennett · Wed, Sep 9

Sep 09, 10:00 AM - 12:00 PM · Raleigh, NC

Caregiver: Sofia Martinez

Scheduled
Visit #22
Message caregiver
Get help
Care story
This visit
Care instructions
Help & changes

VISIT SCHEDULED

Your visit is scheduled.

Sofia is coming on Sep 09, 10:00 AM - 12:00 PM.

Changes and approvals here apply to this dated visit. Your recurring schedule has its own controls.

Change or cancel this visit
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

Card authorization needs attention.

Your card was declined. Update your payment method to keep this visit on track.

Confirm card authorization

VISIT ESSENTIALS

Before the visit

Keep the caregiver, time, location, and late-arrival guidance in one simple place.

Scheduled

VISIT PLAN

Sofia is scheduled to come.

TIME

Sep 09

10:00 AM - 12:00 PM

CAREGIVER

Sofia Martinez

Check-in pending

PAYMENT

Failed

Authorized $62.00

CARE LOCATION

142 Maple Grove Lane, Apartment 2B, Raleigh, NC 27601

Open map
Eleanor Bennett - Mother
If the caregiver is late
Map and visit record
Confirm card authorization

© 2026 LoLo

Legal links
```

### Visible headings

| Level | Heading |
|---|---|
| H1 | Eleanor Bennett · Wed, Sep 9 |
| H2 | Your visit is scheduled. |
| H2 | Important for this visit |
| H3 | Safety & support |
| H3 | Home access |
| H3 | Care contact |
| H2 | Before the visit |
| H3 | Sofia is scheduled to come. |

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

#### 7. Change or cancel this visit

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Change or cancel this visit
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

#### 9. Confirm card authorization

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

#### 10. Confirm card authorization

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

### Every visible link

| Label | Destination |
|---|---|
| Family workspace | http://127.0.0.1:8028/family/requests |
| Notifications | http://127.0.0.1:8028/family/notifications |
| My care | http://127.0.0.1:8028/family/care |
| Back to recurring care · Visits | http://127.0.0.1:8028/family/care/6?tab=visits&visit\_filter=upcoming&visitsPage=1#care-visit-22 |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/6 |
| +1 919 555 0153 | tel:+1 919 555 0153 |
| daniel.find-care-audit@example.com | mailto:daniel.find-care-audit@example.com |
| Open map | https://www.google.com/maps/search/?api=1&query=142%20Maple%20Grove%20Lane%2C%20Apartment%202B%2C%20Raleigh%2C%20NC%2027601 |

### Disclosure states

| Disclosure | State |
|---|---|
| If the caregiver is late | Closed |
| Map and visit record | Closed |
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
  "documentHeight": 2485,
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
    "headerText": "My care\n/\nBack to recurring care · Visits\n/\nVisit #22\n\nONE VISIT IN YOUR RECURRING CARE\n\nEleanor Bennett · Wed, Sep 9\n\nSep 09, 10:00 AM - 12:00 PM · Raleigh, NC\n\nCaregiver: Sofia Martinez\n\nScheduled\nVisit #22\nMessage caregiver\nGet help\nCare story",
    "primaryTitle": "Your visit is scheduled.",
    "selectedTabs": [
      "This visit"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/37?tab=shift"
}
```
