# RC-inline-support-plan — Inline Help — support chat open

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | Recurring care |
| Lifecycle stage | Hired / before shift |
| Route | /family/care/1?tab=overview |
| Selected tab | Overview |
| Preserved route key | overview |
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
  "capturedAt": "2026-09-08T15:59:31.078Z",
  "buildHash": "58c0b4adc3736b3717582dffe837c3b2284299b26b123064150359543361e636",
  "prepared": true,
  "displayTab": "Overview"
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 2029 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/care/1?tab=overview

[Focused view](<../screenshots/RC-inline-support-plan-desktop-focus.png>) · [First screenful](<../screenshots/RC-inline-support-plan-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/RC-inline-support-plan-desktop-full.png>) · [Raw visible text](<../evidence/RC-inline-support-plan-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/RC-inline-support-plan-desktop.json>)

![Desktop screenshot of Inline Help — support chat open](<../screenshots/RC-inline-support-plan-desktop-focus.png>)

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
← Back to your care

Recurring care · Care #1

Recurring care for Eleanor Bennett

Sofia Martinez · Mon, Wed, Fri · 10:00 AM–12:00 PM

Recurring care is active
Caregiver & conversations
Get help
Overview
Caregivers
Visits
Care details
Help with your recurring care

For hours, payment, a change or a care concern, choose the affected visit. Its support history stays with that date.

Choose a visit
Chat with support
Contact LoLo Support
Close help
Current visit

Open this date for its care, check-in, hours and payment.

Recurring visit · Visit #9

Monday, September 7, 2026

7:56 AM–9:56 AM EDT · Sofia Martinez

Happening now

Care location

142 Maple Grove Lane, Apartment 2B
Raleigh, NC 27601

Payment

Card authorized

Checked in Sep 7 · 7:56 AM

View visit details
Get help with this visit
View all 2 booked visits →

ACROSS YOUR VISITS

3 visits to follow up

Each date keeps its own changes, hours, payment and review.

Monday, Sep 7 · 2:51 AM · Visit #11

Sofia Martinez

Hours awaiting approval
Review hours
for visit #11
Monday, Sep 7 · 5:51 AM · Visit #10

Sofia Martinez

Hours awaiting approval
Review hours
for visit #10
Saturday, Sep 5 · 10:00 AM · Visit #12

Sofia Martinez

Caregiver review available
Leave a review
for visit #12
Important for Ellie’s safety
Fall risk

Keep the hallway clear and remind Ellie to use the handrail.

Read full care details →
Care story
All past visits & payments

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
| H1 | Recurring care for Eleanor Bennett |
| H2 | Help with your recurring care |
| H2 | Current visit |
| H3 | Monday, September 7, 2026 |
| H2 | 3 visits to follow up |
| H3 | Monday, Sep 7 · 2:51 AM · Visit #11 |
| H3 | Monday, Sep 7 · 5:51 AM · Visit #10 |
| H3 | Saturday, Sep 5 · 10:00 AM · Visit #12 |
| H2 | Important for Ellie’s safety |
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

#### 2. Get help

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Get help
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** true
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

#### 7. Choose a visit

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Choose a visit
- **value:** (empty)
- **disabled:** false
- **current:** null
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

#### 9. Close help

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Close help
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 10. View all 2 booked visits →

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** View all 2 booked visits →
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 11. Read full care details →

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Read full care details →
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 12. Minimize support chat

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

#### 13. Message LoLo Support

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

#### 14. Send message to LoLo Support

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
| ← Back to your care | http://127.0.0.1:8028/family/care |
| Caregiver & conversations | http://127.0.0.1:8028/family/requests/16?tab=applicants |
| Contact LoLo Support | http://127.0.0.1:8028/support |
| View visit details | http://127.0.0.1:8028/family/requests/17?tab=shift&return\_tab=overview&visit\_filter=upcoming&return\_page=1 |
| Get help with this visit | http://127.0.0.1:8028/family/requests/17?tab=support&return\_tab=overview&visit\_filter=upcoming&return\_page=1 |
| Review hours<br>for visit #11 | http://127.0.0.1:8028/family/requests/19?tab=shift&return\_tab=overview&visit\_filter=upcoming&return\_page=1 |
| Review hours<br>for visit #10 | http://127.0.0.1:8028/family/requests/18?tab=shift&return\_tab=overview&visit\_filter=upcoming&return\_page=1 |
| Leave a review<br>for visit #12 | http://127.0.0.1:8028/family/requests/20?tab=shift&return\_tab=overview&visit\_filter=upcoming&return\_page=1 |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/1 |
| All past visits & payments | http://127.0.0.1:8028/family/care/history?plan=1 |
| Terms of Service | http://127.0.0.1:8028/legal/platform-terms-of-service |
| Privacy Policy | http://127.0.0.1:8028/legal/privacy-policy |
| Payments & Cancellation | http://127.0.0.1:8028/legal/payment-terms-and-cancellation-policy |
| Caregiver Terms | http://127.0.0.1:8028/legal/caregiver-terms |
| Family Terms | http://127.0.0.1:8028/legal/client-and-family-terms |
| Safety Policy | http://127.0.0.1:8028/legal/acceptable-use-and-safety-policy |
| All Legal Documents | http://127.0.0.1:8028/legal |
| Open Support Center | http://127.0.0.1:8028/support |

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
  "documentHeight": 2029,
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
    "status": "",
    "headerText": "← Back to your care\n\nRecurring care · Care #1\n\nRecurring care for Eleanor Bennett\n\nSofia Martinez · Mon, Wed, Fri · 10:00 AM–12:00 PM\n\nRecurring care is active\nCaregiver & conversations\nGet help",
    "primaryTitle": "",
    "selectedTabs": [
      "Overview"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/care/1?tab=overview"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 2595 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/care/1?tab=overview

[Focused view](<../screenshots/RC-inline-support-plan-mobile-focus.png>) · [First screenful](<../screenshots/RC-inline-support-plan-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/RC-inline-support-plan-mobile-full.png>) · [Raw visible text](<../evidence/RC-inline-support-plan-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/RC-inline-support-plan-mobile.json>)

![Mobile screenshot of Inline Help — support chat open](<../screenshots/RC-inline-support-plan-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
← Back to your care

Recurring care · Care #1

Recurring care for Eleanor Bennett

Sofia Martinez · Mon, Wed, Fri · 10:00 AM–12:00 PM

Recurring care is active
Caregiver & conversations
Get help
Overview
Caregivers
Visits
Care details
Help with your recurring care

For hours, payment, a change or a care concern, choose the affected visit. Its support history stays with that date.

Choose a visit
Chat with support
Contact LoLo Support
Close help
Current visit

Open this date for its care, check-in, hours and payment.

Recurring visit · Visit #9

Monday, September 7, 2026

7:56 AM–9:56 AM EDT · Sofia Martinez

Happening now

Care location

142 Maple Grove Lane, Apartment 2B
Raleigh, NC 27601

Payment

Card authorized

Checked in Sep 7 · 7:56 AM

View visit details
Get help with this visit
View all 2 booked visits →

ACROSS YOUR VISITS

3 visits to follow up

Each date keeps its own changes, hours, payment and review.

Monday, Sep 7 · 2:51 AM · Visit #11

Sofia Martinez

Hours awaiting approval
Review hours
for visit #11
Monday, Sep 7 · 5:51 AM · Visit #10

Sofia Martinez

Hours awaiting approval
Review hours
for visit #10
Saturday, Sep 5 · 10:00 AM · Visit #12

Sofia Martinez

Caregiver review available
Leave a review
for visit #12
Important for Ellie’s safety
Fall risk

Keep the hallway clear and remind Ellie to use the handrail.

Read full care details →
Care story
All past visits & payments

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
| H1 | Recurring care for Eleanor Bennett |
| H2 | Help with your recurring care |
| H2 | Current visit |
| H3 | Monday, September 7, 2026 |
| H2 | 3 visits to follow up |
| H3 | Monday, Sep 7 · 2:51 AM · Visit #11 |
| H3 | Monday, Sep 7 · 5:51 AM · Visit #10 |
| H3 | Saturday, Sep 5 · 10:00 AM · Visit #12 |
| H2 | Important for Ellie’s safety |
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

#### 2. Get help

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Get help
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** true
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

#### 7. Choose a visit

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Choose a visit
- **value:** (empty)
- **disabled:** false
- **current:** null
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

#### 9. Close help

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Close help
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 10. View all 2 booked visits →

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** View all 2 booked visits →
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 11. Read full care details →

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Read full care details →
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 12. Minimize support chat

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

#### 13. Message LoLo Support

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

#### 14. Send message to LoLo Support

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
| ← Back to your care | http://127.0.0.1:8028/family/care |
| Caregiver & conversations | http://127.0.0.1:8028/family/requests/16?tab=applicants |
| Contact LoLo Support | http://127.0.0.1:8028/support |
| View visit details | http://127.0.0.1:8028/family/requests/17?tab=shift&return\_tab=overview&visit\_filter=upcoming&return\_page=1 |
| Get help with this visit | http://127.0.0.1:8028/family/requests/17?tab=support&return\_tab=overview&visit\_filter=upcoming&return\_page=1 |
| Review hours<br>for visit #11 | http://127.0.0.1:8028/family/requests/19?tab=shift&return\_tab=overview&visit\_filter=upcoming&return\_page=1 |
| Review hours<br>for visit #10 | http://127.0.0.1:8028/family/requests/18?tab=shift&return\_tab=overview&visit\_filter=upcoming&return\_page=1 |
| Leave a review<br>for visit #12 | http://127.0.0.1:8028/family/requests/20?tab=shift&return\_tab=overview&visit\_filter=upcoming&return\_page=1 |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/1 |
| All past visits & payments | http://127.0.0.1:8028/family/care/history?plan=1 |
| Open Support Center | http://127.0.0.1:8028/support |

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
  "documentHeight": 2595,
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
    "status": "",
    "headerText": "← Back to your care\n\nRecurring care · Care #1\n\nRecurring care for Eleanor Bennett\n\nSofia Martinez · Mon, Wed, Fri · 10:00 AM–12:00 PM\n\nRecurring care is active\nCaregiver & conversations\nGet help",
    "primaryTitle": "",
    "selectedTabs": [
      "Overview"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/care/1?tab=overview"
}
```
