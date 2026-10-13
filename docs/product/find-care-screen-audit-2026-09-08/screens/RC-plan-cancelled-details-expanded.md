# RC-plan-cancelled-details-expanded — Recurring care cancelled — Complete care profile and plan record

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | Recurring care |
| Lifecycle stage | Closed |
| Route | /family/care/10?tab=details |
| Selected tab | Care details |
| Preserved route key | details |
| Panel / disclosure | expanded |
| Capture status | Captured |
| UX review | Implementation rationale; validation recorded separately |
| UX findings | Care instructions should be findable without repeating the full profile above every action. |
| Redesign decision | The complete attached profile, plan notes, tasks, address and plan record stay here. Full event payloads are retained in the database, not presented as a new timeline. |
| Owner | Local care workspace implementation |
| Priority |  |

These screenshots show the current application with synthetic audit data. This is capture and source-audit evidence; the local UI redesign is implemented. Remote map/payment widgets were blocked in this local capture session, so their current fallback states are preserved. Brand fonts are served locally.

## Additional capture context

```json
{
  "addedByRedesign": true,
  "captureVersion": 5,
  "capturedAt": "2026-09-08T15:49:07.508Z",
  "buildHash": "58c0b4adc3736b3717582dffe837c3b2284299b26b123064150359543361e636",
  "prepared": true,
  "displayTab": "Care details"
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 3654 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/care/10?tab=details

[Focused view](<../screenshots/RC-plan-cancelled-details-expanded-desktop-focus.png>) · [First screenful](<../screenshots/RC-plan-cancelled-details-expanded-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/RC-plan-cancelled-details-expanded-desktop-full.png>) · [Raw visible text](<../evidence/RC-plan-cancelled-details-expanded-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/RC-plan-cancelled-details-expanded-desktop.json>)

![Desktop screenshot of Recurring care cancelled — Complete care profile and plan record](<../screenshots/RC-plan-cancelled-details-expanded-desktop-focus.png>)

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

Recurring care · Care #10

Recurring care for Eleanor Bennett

Sofia Martinez · Mon, Wed, Fri · 10:00 AM–12:00 PM

Recurring care cancelled
Caregiver & conversations
Get help
Overview
Caregivers
Visits
Care details
Care details for this recurring care

These are the details saved for this care. Each booked visit keeps the care profile and agreement assigned to that visit.

Care location
142 Maple Grove Lane
Apartment 2B
Raleigh, NC 27601
Care notes
Please follow Ellie’s familiar morning routine: companionship, lunch, and a tidy kitchen.
Tasks and instructions

Companionship

Conversation, a crossword puzzle, and a short walk if Ellie feels up to it.

Meal preparation

Prepare soup and a sandwich for lunch; leave the kitchen tidy.

Light housekeeping

Wash dishes and keep the living room and hallway clear.

About Ellie
Mother
75-84
She/her
At a glance

A retired teacher who enjoys gardening, family stories, and a friendly conversation.

Interests and comforts

Crossword puzzles, jazz, and sitting in the garden when the weather is mild.

A good visit

A relaxed pace, a warm lunch, and a short walk make a good visit.

Communication
Use short, clear sentences
Give extra time to answer

Please introduce yourself and give Ellie time to answer.

Support and mobility
Companionship and conversation
Meal preparation or eating support
Light household help
Walking or mobility support

Mobility

Uses an aid

Uses a walking cane outdoors; stay nearby on steps.

Routine and comfort

Usual routine

Lunch around noon, followed by a short walk and a cup of tea.

Food and drink

Prefers soup and a sandwich. Encourage a glass of water at lunch.

Comfort and reassurance

Benefits from reminders or reassurance
Important for safety
Fall risk

Keep the hallway clear and remind Ellie to use the handrail.

What the family is looking for

Helpful caregiver qualities

Patient
Calm
Conversational

Please do

Explain what you are doing and offer choices.

Contacts and care coordination

Full name: Eleanor Bennett

Daniel Bennett · Son

+1 919 555 0153

daniel.find-care-audit@example.com

Contact Maria first; Daniel is the backup family contact.

Shared by Ellie's family · last reviewed September 7, 2026.
Instructions from your original request
Scope of care
Companionship, preparing lunch, and light housekeeping.
Time expectations
Please arrive on time and leave a short update after the visit.
Home access
Use the main entrance and ring apartment 2B. Maria will meet you for the first visit.
Additional information
We are looking for a friendly caregiver to help Ellie feel comfortable and supported at home.
Additional care contact

Daniel Bennett

Son

+1 919 555 0153

daniel.find-care-audit@example.com

Care record
Care reference
Care #10
Schedule version
1
Offered
Sep 7, 2026 · 11:03 AM
Ended
Sep 8, 2026 · 9:03 AM
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
```

### Visible headings

| Level | Heading |
|---|---|
| H1 | Recurring care for Eleanor Bennett |
| H2 | Care details for this recurring care |
| H3 | Tasks and instructions |
| H2 | About Ellie |
| H3 | At a glance |
| H3 | Communication |
| H3 | Support and mobility |
| H3 | Routine and comfort |
| H3 | Important for safety |
| H3 | What the family is looking for |
| H3 | Contacts and care coordination |
| H2 | Instructions from your original request |
| H2 | Additional care contact |
| H2 | Care record |

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
- **expanded:** false
- **pressed:** null
- **placeholder:** null

#### 3. Overview

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
- **current:** page
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
| Caregiver & conversations | http://127.0.0.1:8028/family/requests/41?tab=applicants |
| +1 919 555 0153 | tel:+1 919 555 0153 |
| daniel.find-care-audit@example.com | mailto:daniel.find-care-audit@example.com |
| +1 919 555 0153 | tel:+1 919 555 0153 |
| daniel.find-care-audit@example.com | mailto:daniel.find-care-audit@example.com |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/10 |
| All past visits & payments | http://127.0.0.1:8028/family/care/history?plan=10 |
| Terms of Service | http://127.0.0.1:8028/legal/platform-terms-of-service |
| Privacy Policy | http://127.0.0.1:8028/legal/privacy-policy |
| Payments & Cancellation | http://127.0.0.1:8028/legal/payment-terms-and-cancellation-policy |
| Caregiver Terms | http://127.0.0.1:8028/legal/caregiver-terms |
| Family Terms | http://127.0.0.1:8028/legal/client-and-family-terms |
| Safety Policy | http://127.0.0.1:8028/legal/acceptable-use-and-safety-policy |
| All Legal Documents | http://127.0.0.1:8028/legal |

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
  "documentHeight": 3654,
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
    "headerText": "← Back to your care\n\nRecurring care · Care #10\n\nRecurring care for Eleanor Bennett\n\nSofia Martinez · Mon, Wed, Fri · 10:00 AM–12:00 PM\n\nRecurring care cancelled\nCaregiver & conversations\nGet help",
    "primaryTitle": "",
    "selectedTabs": [
      "Care details"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/care/10?tab=details"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 4719 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/care/10?tab=details

[Focused view](<../screenshots/RC-plan-cancelled-details-expanded-mobile-focus.png>) · [First screenful](<../screenshots/RC-plan-cancelled-details-expanded-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/RC-plan-cancelled-details-expanded-mobile-full.png>) · [Raw visible text](<../evidence/RC-plan-cancelled-details-expanded-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/RC-plan-cancelled-details-expanded-mobile.json>)

![Mobile screenshot of Recurring care cancelled — Complete care profile and plan record](<../screenshots/RC-plan-cancelled-details-expanded-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
← Back to your care

Recurring care · Care #10

Recurring care for Eleanor Bennett

Sofia Martinez · Mon, Wed, Fri · 10:00 AM–12:00 PM

Recurring care cancelled
Caregiver & conversations
Get help
Overview
Caregivers
Visits
Care details
Care details for this recurring care

These are the details saved for this care. Each booked visit keeps the care profile and agreement assigned to that visit.

Care location
142 Maple Grove Lane
Apartment 2B
Raleigh, NC 27601
Care notes
Please follow Ellie’s familiar morning routine: companionship, lunch, and a tidy kitchen.
Tasks and instructions

Companionship

Conversation, a crossword puzzle, and a short walk if Ellie feels up to it.

Meal preparation

Prepare soup and a sandwich for lunch; leave the kitchen tidy.

Light housekeeping

Wash dishes and keep the living room and hallway clear.

About Ellie
Mother
75-84
She/her
At a glance

A retired teacher who enjoys gardening, family stories, and a friendly conversation.

Interests and comforts

Crossword puzzles, jazz, and sitting in the garden when the weather is mild.

A good visit

A relaxed pace, a warm lunch, and a short walk make a good visit.

Communication
Use short, clear sentences
Give extra time to answer

Please introduce yourself and give Ellie time to answer.

Support and mobility
Companionship and conversation
Meal preparation or eating support
Light household help
Walking or mobility support

Mobility

Uses an aid

Uses a walking cane outdoors; stay nearby on steps.

Routine and comfort

Usual routine

Lunch around noon, followed by a short walk and a cup of tea.

Food and drink

Prefers soup and a sandwich. Encourage a glass of water at lunch.

Comfort and reassurance

Benefits from reminders or reassurance
Important for safety
Fall risk

Keep the hallway clear and remind Ellie to use the handrail.

What the family is looking for

Helpful caregiver qualities

Patient
Calm
Conversational

Please do

Explain what you are doing and offer choices.

Contacts and care coordination

Full name: Eleanor Bennett

Daniel Bennett · Son

+1 919 555 0153

daniel.find-care-audit@example.com

Contact Maria first; Daniel is the backup family contact.

Shared by Ellie's family · last reviewed September 7, 2026.
Instructions from your original request
Scope of care
Companionship, preparing lunch, and light housekeeping.
Time expectations
Please arrive on time and leave a short update after the visit.
Home access
Use the main entrance and ring apartment 2B. Maria will meet you for the first visit.
Additional information
We are looking for a friendly caregiver to help Ellie feel comfortable and supported at home.
Additional care contact

Daniel Bennett

Son

+1 919 555 0153

daniel.find-care-audit@example.com

Care record
Care reference
Care #10
Schedule version
1
Offered
Sep 7, 2026 · 11:03 AM
Ended
Sep 8, 2026 · 9:03 AM
Care story
All past visits & payments

© 2026 LoLo

Legal links
```

### Visible headings

| Level | Heading |
|---|---|
| H1 | Recurring care for Eleanor Bennett |
| H2 | Care details for this recurring care |
| H3 | Tasks and instructions |
| H2 | About Ellie |
| H3 | At a glance |
| H3 | Communication |
| H3 | Support and mobility |
| H3 | Routine and comfort |
| H3 | Important for safety |
| H3 | What the family is looking for |
| H3 | Contacts and care coordination |
| H2 | Instructions from your original request |
| H2 | Additional care contact |
| H2 | Care record |

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
- **expanded:** false
- **pressed:** null
- **placeholder:** null

#### 3. Overview

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
- **current:** page
- **expanded:** null
- **pressed:** null
- **placeholder:** null

### Every visible link

| Label | Destination |
|---|---|
| Family workspace | http://127.0.0.1:8028/family/requests |
| Notifications | http://127.0.0.1:8028/family/notifications |
| ← Back to your care | http://127.0.0.1:8028/family/care |
| Caregiver & conversations | http://127.0.0.1:8028/family/requests/41?tab=applicants |
| +1 919 555 0153 | tel:+1 919 555 0153 |
| daniel.find-care-audit@example.com | mailto:daniel.find-care-audit@example.com |
| +1 919 555 0153 | tel:+1 919 555 0153 |
| daniel.find-care-audit@example.com | mailto:daniel.find-care-audit@example.com |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/10 |
| All past visits & payments | http://127.0.0.1:8028/family/care/history?plan=10 |

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
  "documentHeight": 4719,
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
    "headerText": "← Back to your care\n\nRecurring care · Care #10\n\nRecurring care for Eleanor Bennett\n\nSofia Martinez · Mon, Wed, Fri · 10:00 AM–12:00 PM\n\nRecurring care cancelled\nCaregiver & conversations\nGet help",
    "primaryTitle": "",
    "selectedTabs": [
      "Care details"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/care/10?tab=details"
}
```
