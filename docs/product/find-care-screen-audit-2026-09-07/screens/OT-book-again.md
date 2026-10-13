# OT-book-again — Book caregiver again — one visit or recurring handoff

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | One-time care |
| Lifecycle stage | Closed |
| Route | /family/requests/10/book-again |
| Selected tab | Single form |
| Panel / disclosure | default |
| Capture status | Captured |
| UX review | Audited — grouped evidence |
| UX findings | Delivery D11: Make the previous caregiver/visit and new schedule explicit before rebooking; preserve copied care/task/address data, historic-profile versus latest-profile choice and all one-time/recurring options and eligibility. Do not silently update the old visit snapshot. |
| Redesign decision | Proposed; not implemented |
| Owner | Unassigned |
| Priority | P2 |

These screenshots show the current application with synthetic audit data. This is capture and source-audit evidence; no UI redesign is implemented. Remote map/payment widgets were blocked in this local capture session, so their current fallback states are preserved. Google Fonts were allowed.

## Additional capture context

```json
{
  "captureVersion": 4,
  "prepared": false
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 1217 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8027/family/requests/10/book-again

[Focused view](<../screenshots/OT-book-again-desktop-focus.png>) · [First screenful](<../screenshots/OT-book-again-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/OT-book-again-desktop-full.png>) · [Raw visible text](<../evidence/OT-book-again-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/OT-book-again-desktop.json>)

![Desktop screenshot of Book caregiver again — one visit or recurring handoff](<../screenshots/OT-book-again-desktop-focus.png>)

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

REQUEST CARE WITH SOFIA

One visit or recurring care—without starting over.

We copied the care details from Aug 31, 2026. Choose how often you need care; the existing invitation, approval, and payment flow stays the same.

ONE VISIT

Invite Sofia for a specific date

They will see it in their work inbox. If they accept, you can hire them from the request page.

Starting day
Starting time
Duration
1h
1h 30m
2h
2h 30m
3h
3h 30m
4h
4h 30m
5h
5h 30m
6h
6h 30m
7h
7h 30m
8h
8h 30m
9h
9h 30m
10h
10h 30m
11h
11h 30m
12h

Estimated visit total

Care $60.00 + $2.00 processing fee

$62.00

$30/hour care* plus a $1/hour processing fee.

Message to caregiver

Copied from last visit

CAREGIVER

Sofia Martinez

FOR

Eleanor Bennett

LOCATION

142 Maple Grove Lane, Apartment 2B, Raleigh, NC 27601

Back to visit
Send one-time invite

RECURRING CARE

Set up a repeating schedule

Choose this when Sofia should come every week. You can set the days, time, start date, and end date before sending the offer.

Set up recurring care
What happens next

1. Sofia accepts or declines

They get a direct invite with the copied care details.

2. You hire when ready

The normal request page handles chat, profile review, hiring, and payment authorization.

3. The visit follows the usual flow

Check-in, timesheet, approval, and Stripe balance transfer stay in the same system.

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
| H1 | One visit or recurring care—without starting over. |
| H2 | Invite Sofia for a specific date |
| H2 | Set up a repeating schedule |
| H2 | What happens next |

### Every visible control and its current value

#### 1. MB<br>Maria Bennett<br>Family

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** MB<br>Maria Bennett<br>Family
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 2. Starting day

- **tag:** INPUT
- **type:** date
- **label:** Starting day
- **text:** (empty)
- **value:** 2026-09-07
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 3. Starting time

- **tag:** INPUT
- **type:** time
- **label:** Starting time
- **text:** (empty)
- **value:** 10:00
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 4. Duration

- **tag:** SELECT
- **type:** select-one
- **label:** Duration
- **text:** (empty)
- **value:** 120
- **disabled:** false
- **required:** false
- **placeholder:** null
- **Dropdown options:**
  - 1h — value: 60; selected: false
  - 1h 30m — value: 90; selected: false
  - 2h — value: 120; selected: true
  - 2h 30m — value: 150; selected: false
  - 3h — value: 180; selected: false
  - 3h 30m — value: 210; selected: false
  - 4h — value: 240; selected: false
  - 4h 30m — value: 270; selected: false
  - 5h — value: 300; selected: false
  - 5h 30m — value: 330; selected: false
  - 6h — value: 360; selected: false
  - 6h 30m — value: 390; selected: false
  - 7h — value: 420; selected: false
  - 7h 30m — value: 450; selected: false
  - 8h — value: 480; selected: false
  - 8h 30m — value: 510; selected: false
  - 9h — value: 540; selected: false
  - 9h 30m — value: 570; selected: false
  - 10h — value: 600; selected: false
  - 10h 30m — value: 630; selected: false
  - 11h — value: 660; selected: false
  - 11h 30m — value: 690; selected: false
  - 12h — value: 720; selected: false

#### 5. Message to caregiver

- **tag:** TEXTAREA
- **type:** textarea
- **label:** Message to caregiver
- **text:** (empty)
- **value:** Hi Sofia, we would like to book you again for one more visit.
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 6. Send one-time invite

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Send one-time invite
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 7. Chat with LoLo Support

- **tag:** BUTTON
- **type:** button
- **label:** Chat with LoLo Support
- **text:** Support
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

### Every visible link

| Label | Destination |
|---|---|
| FAMILY WORKSPACE | http://127.0.0.1:8027/family/requests |
| Care | http://127.0.0.1:8027/family/requests |
| Caregivers | http://127.0.0.1:8027/caregivers/search |
| Messages | http://127.0.0.1:8027/messages |
| Request care | http://127.0.0.1:8027/family/requests/create |
| Notifications | http://127.0.0.1:8027/family/notifications |
| Back to visit | http://127.0.0.1:8027/family/requests/10 |
| Set up recurring care | http://127.0.0.1:8027/family/requests/10/regular-care |
| Terms of Service | http://127.0.0.1:8027/legal/platform-terms-of-service |
| Privacy Policy | http://127.0.0.1:8027/legal/privacy-policy |
| Payments & Cancellation | http://127.0.0.1:8027/legal/payment-terms-and-cancellation-policy |
| Caregiver Terms | http://127.0.0.1:8027/legal/caregiver-terms |
| Family Terms | http://127.0.0.1:8027/legal/client-and-family-terms |
| Safety Policy | http://127.0.0.1:8027/legal/acceptable-use-and-safety-policy |
| All Legal Documents | http://127.0.0.1:8027/legal |

### Disclosure states

No native disclosures recorded.

### Visible images and loading state

| Alternative text | Source | Loaded |
|---|---|---|
| LoLo | http://127.0.0.1:8027/images/marketing/lolo/lolo-app-icon.svg | true |
| LoLo | http://127.0.0.1:8027/images/marketing/lolo/lolo-wordmark-evergreen.svg | true |

### Capture metadata

```json
{
  "width": 1440,
  "height": 1000,
  "documentWidth": 1440,
  "documentHeight": 1217,
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8027/family/requests/10/book-again"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 2491 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8027/family/requests/10/book-again

[Focused view](<../screenshots/OT-book-again-mobile-focus.png>) · [First screenful](<../screenshots/OT-book-again-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/OT-book-again-mobile-full.png>) · [Raw visible text](<../evidence/OT-book-again-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/OT-book-again-mobile.json>)

![Mobile screenshot of Book caregiver again — one visit or recurring handoff](<../screenshots/OT-book-again-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
REQUEST CARE WITH SOFIA

One visit or recurring care—without starting over.

We copied the care details from Aug 31, 2026. Choose how often you need care; the existing invitation, approval, and payment flow stays the same.

ONE VISIT

Invite Sofia for a specific date

They will see it in their work inbox. If they accept, you can hire them from the request page.

Starting day
Starting time
Duration
1h
1h 30m
2h
2h 30m
3h
3h 30m
4h
4h 30m
5h
5h 30m
6h
6h 30m
7h
7h 30m
8h
8h 30m
9h
9h 30m
10h
10h 30m
11h
11h 30m
12h

Estimated visit total

Care $60.00 + $2.00 processing fee

$62.00

$30/hour care* plus a $1/hour processing fee.

Message to caregiver

Copied from last visit

CAREGIVER

Sofia Martinez

FOR

Eleanor Bennett

LOCATION

142 Maple Grove Lane, Apartment 2B, Raleigh, NC 27601

Back to visit
Send one-time invite

RECURRING CARE

Set up a repeating schedule

Choose this when Sofia should come every week. You can set the days, time, start date, and end date before sending the offer.

Set up recurring care
What happens next

1. Sofia accepts or declines

They get a direct invite with the copied care details.

2. You hire when ready

The normal request page handles chat, profile review, hiring, and payment authorization.

3. The visit follows the usual flow

Check-in, timesheet, approval, and Stripe balance transfer stay in the same system.

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
| H1 | One visit or recurring care—without starting over. |
| H2 | Invite Sofia for a specific date |
| H2 | Set up a repeating schedule |
| H2 | What happens next |

### Every visible control and its current value

#### 1. Toggle navigation menu

- **tag:** BUTTON
- **type:** button
- **label:** Toggle navigation menu
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 2. Starting day

- **tag:** INPUT
- **type:** date
- **label:** Starting day
- **text:** (empty)
- **value:** 2026-09-07
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 3. Starting time

- **tag:** INPUT
- **type:** time
- **label:** Starting time
- **text:** (empty)
- **value:** 10:00
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 4. Duration

- **tag:** SELECT
- **type:** select-one
- **label:** Duration
- **text:** (empty)
- **value:** 120
- **disabled:** false
- **required:** false
- **placeholder:** null
- **Dropdown options:**
  - 1h — value: 60; selected: false
  - 1h 30m — value: 90; selected: false
  - 2h — value: 120; selected: true
  - 2h 30m — value: 150; selected: false
  - 3h — value: 180; selected: false
  - 3h 30m — value: 210; selected: false
  - 4h — value: 240; selected: false
  - 4h 30m — value: 270; selected: false
  - 5h — value: 300; selected: false
  - 5h 30m — value: 330; selected: false
  - 6h — value: 360; selected: false
  - 6h 30m — value: 390; selected: false
  - 7h — value: 420; selected: false
  - 7h 30m — value: 450; selected: false
  - 8h — value: 480; selected: false
  - 8h 30m — value: 510; selected: false
  - 9h — value: 540; selected: false
  - 9h 30m — value: 570; selected: false
  - 10h — value: 600; selected: false
  - 10h 30m — value: 630; selected: false
  - 11h — value: 660; selected: false
  - 11h 30m — value: 690; selected: false
  - 12h — value: 720; selected: false

#### 5. Message to caregiver

- **tag:** TEXTAREA
- **type:** textarea
- **label:** Message to caregiver
- **text:** (empty)
- **value:** Hi Sofia, we would like to book you again for one more visit.
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 6. Send one-time invite

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Send one-time invite
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 7. Chat with LoLo Support

- **tag:** BUTTON
- **type:** button
- **label:** Chat with LoLo Support
- **text:** Support
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

### Every visible link

| Label | Destination |
|---|---|
| Family workspace | http://127.0.0.1:8027/family/requests |
| Notifications | http://127.0.0.1:8027/family/notifications |
| Back to visit | http://127.0.0.1:8027/family/requests/10 |
| Set up recurring care | http://127.0.0.1:8027/family/requests/10/regular-care |
| Terms of Service | http://127.0.0.1:8027/legal/platform-terms-of-service |
| Privacy Policy | http://127.0.0.1:8027/legal/privacy-policy |
| Payments & Cancellation | http://127.0.0.1:8027/legal/payment-terms-and-cancellation-policy |
| Caregiver Terms | http://127.0.0.1:8027/legal/caregiver-terms |
| Family Terms | http://127.0.0.1:8027/legal/client-and-family-terms |
| Safety Policy | http://127.0.0.1:8027/legal/acceptable-use-and-safety-policy |
| All Legal Documents | http://127.0.0.1:8027/legal |

### Disclosure states

No native disclosures recorded.

### Visible images and loading state

| Alternative text | Source | Loaded |
|---|---|---|
| LoLo | http://127.0.0.1:8027/images/marketing/lolo/lolo-app-icon.svg | true |
| LoLo | http://127.0.0.1:8027/images/marketing/lolo/lolo-wordmark-evergreen.svg | true |

### Capture metadata

```json
{
  "width": 390,
  "height": 844,
  "documentWidth": 390,
  "documentHeight": 2491,
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8027/family/requests/10/book-again"
}
```
