# RC-timesheet_review-confirmation — Review submitted hours and payment — confirmation

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | Recurring care |
| Lifecycle stage | Timesheet / review |
| Route | /family/requests/19?tab=shift |
| Selected tab | This visit |
| Preserved route key | shift |
| Panel / disclosure | completion review |
| Capture status | Captured |
| UX review | Implementation rationale; validation recorded separately |
| UX findings | Ending care and approving submitted hours are different decisions. |
| Redesign decision | This explicit preview describes the relevant decision, retains draft notes and delegates confirmation to the existing completion method. |
| Owner | Local care workspace implementation |
| Priority |  |

These screenshots show the current application with synthetic audit data. This is capture and source-audit evidence; the local UI redesign is implemented. Remote map/payment widgets were blocked in this local capture session, so their current fallback states are preserved. Brand fonts are served locally.

## Additional capture context

```json
{
  "focusSelector": "#care-decision-review",
  "addedByRedesign": true,
  "captureVersion": 5,
  "capturedAt": "2026-09-08T15:44:32.122Z",
  "buildHash": "58c0b4adc3736b3717582dffe837c3b2284299b26b123064150359543361e636",
  "prepared": true,
  "displayTab": "This visit"
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 1838 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/19?tab=shift

[Focused view](<../screenshots/RC-timesheet_review-confirmation-desktop-focus.png>) · [First screenful](<../screenshots/RC-timesheet_review-confirmation-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/RC-timesheet_review-confirmation-desktop-full.png>) · [Raw visible text](<../evidence/RC-timesheet_review-confirmation-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/RC-timesheet_review-confirmation-desktop.json>)

![Desktop screenshot of Review submitted hours and payment — confirmation](<../screenshots/RC-timesheet_review-confirmation-desktop-focus.png>)

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
Visit #11

ONE VISIT IN YOUR RECURRING CARE

Eleanor Bennett · Mon, Sep 7

Sep 07, 2:51 AM - 4:51 AM · Raleigh, NC

Caregiver: Sofia Martinez

Completed
Visit #11
Message caregiver
Get help
Care story
This visit
Care instructions
Help & changes

REVIEW HOURS

Caregiver hours need your review.

Check the worked time and payment amount. Approving will capture payment and move payout forward.

Changes and approvals here apply to this dated visit. Your recurring schedule has its own controls.

2h 00m · $62.00

Review hours & payment

REVIEW THIS VISIT’S HOURS

Eleanor Bennett · Mon, Sep 7

Sofia Martinez · Sep 07, 2:51 AM - 4:51 AM

Scheduled

120 minutes

Submitted

2h 00m

Check-in Sep 7, 2:51 AM
Check-out Sep 7, 4:51 AM

Care · $30.00/hour
$60.00
Processing fee
$2.00
Estimated total
$62.00

Your approval starts payment processing. The visit will show the actual payment result.

Approval note (optional)
Approve hours and pay $62.00
Back to visit
Question these hours

VISIT DETAILS

Visit details before approval

Confirm schedule, caregiver, and location here. The hours and payment decision stay above.

Completed

SCHEDULE

Sep 07, 2026

2:51 AM - 4:51 AM

CAREGIVER

Sofia Martinez

Checked in Sep 07, 2:51 AM

CARE LOCATION

142 Maple Grove Lane, Apartment 2B, Raleigh, NC 27601

Open map
Eleanor Bennett - Mother
Location map
Question hours
Detailed visit record

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
| H2 | Caregiver hours need your review. |
| H2 | Eleanor Bennett · Mon, Sep 7 |
| H2 | Visit details before approval |

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

#### 7. Review hours & payment

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Review hours & payment
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 8. Approval note (optional)

- **tag:** TEXTAREA
- **type:** textarea
- **label:** Approval note (optional)
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 9. Approve hours and pay $62.00

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Approve hours and pay $62.00
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 10. Back to visit

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Back to visit
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 11. Question these hours

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Question these hours
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 12. Question hours

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Question hours
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
| Back to recurring care · Visits | http://127.0.0.1:8028/family/care/1?tab=visits&visit\_filter=upcoming&visitsPage=1#care-visit-11 |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/1 |
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
| Location map | Closed |
| Detailed visit record | Closed |

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
  "documentHeight": 1838,
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
    "headerText": "My care\n/\nBack to recurring care · Visits\n/\nVisit #11\n\nONE VISIT IN YOUR RECURRING CARE\n\nEleanor Bennett · Mon, Sep 7\n\nSep 07, 2:51 AM - 4:51 AM · Raleigh, NC\n\nCaregiver: Sofia Martinez\n\nCompleted\nVisit #11\nMessage caregiver\nGet help\nCare story",
    "primaryTitle": "Caregiver hours need your review.",
    "selectedTabs": [
      "This visit"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/19?tab=shift"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 2735 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/19?tab=shift

[Focused view](<../screenshots/RC-timesheet_review-confirmation-mobile-focus.png>) · [First screenful](<../screenshots/RC-timesheet_review-confirmation-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/RC-timesheet_review-confirmation-mobile-full.png>) · [Raw visible text](<../evidence/RC-timesheet_review-confirmation-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/RC-timesheet_review-confirmation-mobile.json>)

![Mobile screenshot of Review submitted hours and payment — confirmation](<../screenshots/RC-timesheet_review-confirmation-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
My care
/
Back to recurring care · Visits
/
Visit #11

ONE VISIT IN YOUR RECURRING CARE

Eleanor Bennett · Mon, Sep 7

Sep 07, 2:51 AM - 4:51 AM · Raleigh, NC

Caregiver: Sofia Martinez

Completed
Visit #11
Message caregiver
Get help
Care story
This visit
Care instructions
Help & changes

REVIEW HOURS

Caregiver hours need your review.

Check the worked time and payment amount. Approving will capture payment and move payout forward.

Changes and approvals here apply to this dated visit. Your recurring schedule has its own controls.

2h 00m · $62.00

Review hours & payment

REVIEW THIS VISIT’S HOURS

Eleanor Bennett · Mon, Sep 7

Sofia Martinez · Sep 07, 2:51 AM - 4:51 AM

Scheduled

120 minutes

Submitted

2h 00m

Check-in Sep 7, 2:51 AM
Check-out Sep 7, 4:51 AM

Care · $30.00/hour
$60.00
Processing fee
$2.00
Estimated total
$62.00

Your approval starts payment processing. The visit will show the actual payment result.

Approval note (optional)
Approve hours and pay $62.00
Back to visit
Question these hours

VISIT DETAILS

Visit details before approval

Confirm schedule, caregiver, and location here. The hours and payment decision stay above.

Completed

SCHEDULE

Sep 07, 2026

2:51 AM - 4:51 AM

CAREGIVER

Sofia Martinez

Checked in Sep 07, 2:51 AM

CARE LOCATION

142 Maple Grove Lane, Apartment 2B, Raleigh, NC 27601

Open map
Eleanor Bennett - Mother
Location map
Question hours
Detailed visit record

© 2026 LoLo

Legal links
```

### Visible headings

| Level | Heading |
|---|---|
| H1 | Eleanor Bennett · Mon, Sep 7 |
| H2 | Caregiver hours need your review. |
| H2 | Eleanor Bennett · Mon, Sep 7 |
| H2 | Visit details before approval |

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

#### 7. Review hours & payment

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Review hours & payment
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 8. Approval note (optional)

- **tag:** TEXTAREA
- **type:** textarea
- **label:** Approval note (optional)
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 9. Approve hours and pay $62.00

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Approve hours and pay $62.00
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 10. Back to visit

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Back to visit
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 11. Question these hours

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Question these hours
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 12. Question hours

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Question hours
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
| Back to recurring care · Visits | http://127.0.0.1:8028/family/care/1?tab=visits&visit\_filter=upcoming&visitsPage=1#care-visit-11 |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/1 |
| Open map | https://www.google.com/maps/search/?api=1&query=142%20Maple%20Grove%20Lane%2C%20Apartment%202B%2C%20Raleigh%2C%20NC%2027601 |

### Disclosure states

| Disclosure | State |
|---|---|
| Location map | Closed |
| Detailed visit record | Closed |
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
  "documentHeight": 2735,
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
    "headerText": "My care\n/\nBack to recurring care · Visits\n/\nVisit #11\n\nONE VISIT IN YOUR RECURRING CARE\n\nEleanor Bennett · Mon, Sep 7\n\nSep 07, 2:51 AM - 4:51 AM · Raleigh, NC\n\nCaregiver: Sofia Martinez\n\nCompleted\nVisit #11\nMessage caregiver\nGet help\nCare story",
    "primaryTitle": "Caregiver hours need your review.",
    "selectedTabs": [
      "This visit"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/19?tab=shift"
}
```
