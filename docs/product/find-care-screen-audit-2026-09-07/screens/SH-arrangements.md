# SH-arrangements — Arrangements

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | Shared navigation |
| Lifecycle stage | Hired / before shift |
| Route | /family/care |
| Selected tab | Arrangements |
| Panel / disclosure | default |
| Capture status | Captured |
| UX review | Audited — grouped evidence |
| UX findings | Recurring RC-UX-02/03/12: Clearly separate finding care, waiting for caregiver, active, paused and ended arrangements; retain list filters/counts/history and distinguish proposed dates from booked visits. Open the appropriate plan or source request. |
| Redesign decision | Proposed; not implemented |
| Owner | Unassigned |
| Priority | P1 |

These screenshots show the current application with synthetic audit data. This is capture and source-audit evidence; no UI redesign is implemented. Remote map/payment widgets were blocked in this local capture session, so their current fallback states are preserved. Google Fonts were allowed.

## Additional capture context

```json
{
  "captureVersion": 4,
  "prepared": false
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 1828 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8027/family/care

[Focused view](<../screenshots/SH-arrangements-desktop-focus.png>) · [First screenful](<../screenshots/SH-arrangements-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/SH-arrangements-desktop-full.png>) · [Raw visible text](<../evidence/SH-arrangements-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/SH-arrangements-desktop.json>)

![Desktop screenshot of Arrangements](<../screenshots/SH-arrangements-desktop-focus.png>)

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
Care arrangements

ARRANGEMENTS

Every care arrangement, in one place.

See care still being arranged and ongoing caregiver relationships. Confirmed dates belong in Schedule.

Request care
Overview
Arrangements
Schedule
History
Status
Current care
Being arranged
Ongoing care
Past care
All care
Care type
All care types
One-time care
Recurring care

REQUESTS AND RELATIONSHIPS

18 arrangements
Open visit schedule
RECURRING CARE
Payment needs attention
Eleanor Bennett with Sofia Martinez

Mon, Wed, Fri · 10:00 AM–12:00 PM

Next visit: Wednesday, September 9 · 10:00 AM–12:00 PM

Payment action needed

Recurring care #6
Fix payment
RECURRING CARE
Active
Eleanor Bennett with Sofia Martinez

Mon, Wed, Fri · 10:00 AM–12:00 PM

Payment authorized

Recurring care #5
Manage recurring care
RECURRING CARE
Active
Eleanor Bennett with Sofia Martinez

Mon, Wed, Fri · 10:00 AM–12:00 PM

Payment authorized

Recurring care #7
Manage recurring care
RECURRING CARE
Active
Eleanor Bennett with Sofia Martinez

Mon, Wed, Fri · 10:00 AM–12:00 PM

Next visit: Wednesday, September 9 · 10:00 AM–12:00 PM

Payment authorized

Recurring care #1
Manage recurring care
RECURRING CARE
Waiting for Sofia Martinez
Eleanor Bennett with Sofia Martinez

Mon, Wed, Fri · 10:00 AM–12:00 PM

Payment checked before each visit

Recurring care #2
Manage recurring care
RECURRING CARE
Paused
Eleanor Bennett with Sofia Martinez

Mon, Wed, Fri · 10:00 AM–12:00 PM

Payment authorized

Recurring care #3
Manage recurring care
ONE-TIME CARE
1 caregiver replied
Eleanor Bennett · Thu, Sep 10

Thursday, September 10 · 10:00 AM–12:00 PM

Raleigh, NC

Request #27
Review caregivers
ONE-TIME CARE
4 caregivers replied
Eleanor Bennett · Thu, Sep 10

Thursday, September 10 · 10:00 AM–12:00 PM

Raleigh, NC

Request #28
Review caregivers

Showing 8 of 18 arrangements.

Show more arrangements

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
| H1 | Every care arrangement, in one place. |
| H2 | 18 arrangements |
| H3 | Eleanor Bennett with Sofia Martinez |
| H3 | Eleanor Bennett with Sofia Martinez |
| H3 | Eleanor Bennett with Sofia Martinez |
| H3 | Eleanor Bennett with Sofia Martinez |
| H3 | Eleanor Bennett with Sofia Martinez |
| H3 | Eleanor Bennett with Sofia Martinez |
| H3 | Eleanor Bennett · Thu, Sep 10 |
| H3 | Eleanor Bennett · Thu, Sep 10 |

### Every visible control and its current value

#### 1. MB<br>Maria Bennett<br>Family

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** MB<br>Maria Bennett<br>Family
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 2. Status

- **tag:** SELECT
- **type:** select-one
- **label:** Status
- **text:** (empty)
- **value:** current
- **disabled:** false
- **required:** false
- **placeholder:** null
- **Dropdown options:**
  - Current care — value: current; selected: true
  - Being arranged — value: arranging; selected: false
  - Ongoing care — value: ongoing; selected: false
  - Past care — value: past; selected: false
  - All care — value: all; selected: false

#### 3. Care type

- **tag:** SELECT
- **type:** select-one
- **label:** Care type
- **text:** (empty)
- **value:** all
- **disabled:** false
- **required:** false
- **placeholder:** null
- **Dropdown options:**
  - All care types — value: all; selected: true
  - One-time care — value: one\_time; selected: false
  - Recurring care — value: regular; selected: false

#### 4. Show more arrangements

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Show more arrangements
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 5. Chat with LoLo Support

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
| Request care | http://127.0.0.1:8027/family/requests/create |
| Overview | http://127.0.0.1:8027/family/requests |
| Arrangements | http://127.0.0.1:8027/family/care |
| Schedule | http://127.0.0.1:8027/family/care/schedule |
| History | http://127.0.0.1:8027/family/care/history |
| Open visit schedule | http://127.0.0.1:8027/family/care/schedule?person=all |
| Fix payment | http://127.0.0.1:8027/family/care/6 |
| Manage recurring care | http://127.0.0.1:8027/family/care/5 |
| Manage recurring care | http://127.0.0.1:8027/family/care/7 |
| Manage recurring care | http://127.0.0.1:8027/family/care/1 |
| Manage recurring care | http://127.0.0.1:8027/family/care/2 |
| Manage recurring care | http://127.0.0.1:8027/family/care/3 |
| Review caregivers | http://127.0.0.1:8027/family/requests/27 |
| Review caregivers | http://127.0.0.1:8027/family/requests/28 |
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
  "documentHeight": 1828,
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8027/family/care"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 3507 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8027/family/care

[Focused view](<../screenshots/SH-arrangements-mobile-focus.png>) · [First screenful](<../screenshots/SH-arrangements-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/SH-arrangements-mobile-full.png>) · [Raw visible text](<../evidence/SH-arrangements-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/SH-arrangements-mobile.json>)

![Mobile screenshot of Arrangements](<../screenshots/SH-arrangements-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
Care arrangements

ARRANGEMENTS

Every care arrangement, in one place.
Request care
Overview
Arrangements
Schedule
History
Status
Current care
Being arranged
Ongoing care
Past care
All care
Care type
All care types
One-time care
Recurring care

REQUESTS AND RELATIONSHIPS

18 arrangements
Open visit schedule
RECURRING CARE
Payment needs attention
Eleanor Bennett with Sofia Martinez

Mon, Wed, Fri · 10:00 AM–12:00 PM

Next visit: Wednesday, September 9 · 10:00 AM–12:00 PM

Payment action needed

Recurring care #6
Fix payment
RECURRING CARE
Active
Eleanor Bennett with Sofia Martinez

Mon, Wed, Fri · 10:00 AM–12:00 PM

Payment authorized

Recurring care #5
Manage recurring care
RECURRING CARE
Active
Eleanor Bennett with Sofia Martinez

Mon, Wed, Fri · 10:00 AM–12:00 PM

Payment authorized

Recurring care #7
Manage recurring care
RECURRING CARE
Active
Eleanor Bennett with Sofia Martinez

Mon, Wed, Fri · 10:00 AM–12:00 PM

Next visit: Wednesday, September 9 · 10:00 AM–12:00 PM

Payment authorized

Recurring care #1
Manage recurring care
RECURRING CARE
Waiting for Sofia Martinez
Eleanor Bennett with Sofia Martinez

Mon, Wed, Fri · 10:00 AM–12:00 PM

Payment checked before each visit

Recurring care #2
Manage recurring care
RECURRING CARE
Paused
Eleanor Bennett with Sofia Martinez

Mon, Wed, Fri · 10:00 AM–12:00 PM

Payment authorized

Recurring care #3
Manage recurring care
ONE-TIME CARE
1 caregiver replied
Eleanor Bennett · Thu, Sep 10

Thursday, September 10 · 10:00 AM–12:00 PM

Raleigh, NC

Request #27
Review caregivers
ONE-TIME CARE
4 caregivers replied
Eleanor Bennett · Thu, Sep 10

Thursday, September 10 · 10:00 AM–12:00 PM

Raleigh, NC

Request #28
Review caregivers

Showing 8 of 18 arrangements.

Show more arrangements

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
| H1 | Every care arrangement, in one place. |
| H2 | 18 arrangements |
| H3 | Eleanor Bennett with Sofia Martinez |
| H3 | Eleanor Bennett with Sofia Martinez |
| H3 | Eleanor Bennett with Sofia Martinez |
| H3 | Eleanor Bennett with Sofia Martinez |
| H3 | Eleanor Bennett with Sofia Martinez |
| H3 | Eleanor Bennett with Sofia Martinez |
| H3 | Eleanor Bennett · Thu, Sep 10 |
| H3 | Eleanor Bennett · Thu, Sep 10 |

### Every visible control and its current value

#### 1. Toggle navigation menu

- **tag:** BUTTON
- **type:** button
- **label:** Toggle navigation menu
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 2. Status

- **tag:** SELECT
- **type:** select-one
- **label:** Status
- **text:** (empty)
- **value:** current
- **disabled:** false
- **required:** false
- **placeholder:** null
- **Dropdown options:**
  - Current care — value: current; selected: true
  - Being arranged — value: arranging; selected: false
  - Ongoing care — value: ongoing; selected: false
  - Past care — value: past; selected: false
  - All care — value: all; selected: false

#### 3. Care type

- **tag:** SELECT
- **type:** select-one
- **label:** Care type
- **text:** (empty)
- **value:** all
- **disabled:** false
- **required:** false
- **placeholder:** null
- **Dropdown options:**
  - All care types — value: all; selected: true
  - One-time care — value: one\_time; selected: false
  - Recurring care — value: regular; selected: false

#### 4. Show more arrangements

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Show more arrangements
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 5. Chat with LoLo Support

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
| Request care | http://127.0.0.1:8027/family/requests/create |
| Overview | http://127.0.0.1:8027/family/requests |
| Arrangements | http://127.0.0.1:8027/family/care |
| Schedule | http://127.0.0.1:8027/family/care/schedule |
| History | http://127.0.0.1:8027/family/care/history |
| Open visit schedule | http://127.0.0.1:8027/family/care/schedule?person=all |
| Fix payment | http://127.0.0.1:8027/family/care/6 |
| Manage recurring care | http://127.0.0.1:8027/family/care/5 |
| Manage recurring care | http://127.0.0.1:8027/family/care/7 |
| Manage recurring care | http://127.0.0.1:8027/family/care/1 |
| Manage recurring care | http://127.0.0.1:8027/family/care/2 |
| Manage recurring care | http://127.0.0.1:8027/family/care/3 |
| Review caregivers | http://127.0.0.1:8027/family/requests/27 |
| Review caregivers | http://127.0.0.1:8027/family/requests/28 |
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
  "documentHeight": 3507,
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8027/family/care"
}
```
