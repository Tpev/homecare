# OT-single_applicant-overview — single applicant — Care details

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | One-time care |
| Lifecycle stage | Waiting / inviting |
| Route | /family/requests/27?tab=overview |
| Selected tab | Care details |
| Preserved route key | overview |
| Panel / disclosure | default |
| Capture status | Captured |
| UX review | Implementation rationale; validation recorded separately |
| UX findings | The full care information needs a stable destination while the main task remains concise. |
| Redesign decision | The legacy overview route still opens care details/instructions, including the attached care-profile version, tasks, address and contact information. |
| Owner | Local care workspace implementation |
| Priority |  |

These screenshots show the current application with synthetic audit data. This is capture and source-audit evidence; the local UI redesign is implemented. Remote map/payment widgets were blocked in this local capture session, so their current fallback states are preserved. Brand fonts are served locally.

## Additional capture context

```json
{
  "captureVersion": 5,
  "capturedAt": "2026-09-08T15:36:50.023Z",
  "buildHash": "58c0b4adc3736b3717582dffe837c3b2284299b26b123064150359543361e636",
  "prepared": false,
  "displayTab": "Care details"
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 3141 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/27?tab=overview

[Focused view](<../screenshots/OT-single_applicant-overview-desktop-focus.png>) · [First screenful](<../screenshots/OT-single_applicant-overview-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/OT-single_applicant-overview-desktop-full.png>) · [Raw visible text](<../evidence/OT-single_applicant-overview-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/OT-single_applicant-overview-desktop.json>)

![Desktop screenshot of single applicant — Care details](<../screenshots/OT-single_applicant-overview-desktop-focus.png>)

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

Eleanor Bennett · Thu, Sep 10

Sep 10, 10:00 AM · Raleigh, NC

Finding a caregiver
Request #27
Get help
Care story
Overview
Caregivers
Care details
Help
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
Request context

SCOPE OF WORK

Companionship, preparing lunch, and light housekeeping.

TIME EXPECTATIONS

Please arrive on time and leave a short update after the visit.

HOME ACCESS

Use the main entrance and ring apartment 2B. Maria will meet you for the first visit.

ADDRESS

142 Maple Grove Lane, Apartment 2B, Raleigh, NC 27601

Open full map

RECIPIENT

Eleanor Bennett

Mother

THIRD-PARTY CONTACT

Daniel Bennett

+1 919 555 0153

daniel.find-care-audit@example.com

Task list

3 task(s)

Companionship

Conversation, a crossword puzzle, and a short walk if Ellie feels up to it.

Meal preparation

Prepare soup and a sandwich for lunch; leave the kitchen tidy.

Light housekeeping

Wash dishes and keep the living room and hallway clear.

Selected caregiver

0 invite(s) sent

No caregiver hired yet. Open the Caregivers tab to save, message, or hire someone.
Request options

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
| H1 | Eleanor Bennett · Thu, Sep 10 |
| H2 | About Ellie |
| H3 | At a glance |
| H3 | Communication |
| H3 | Support and mobility |
| H3 | Routine and comfort |
| H3 | Important for safety |
| H3 | What the family is looking for |
| H3 | Contacts and care coordination |
| H2 | Request context |
| H2 | Task list |
| H2 | Selected caregiver |

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
- **expanded:** null
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

#### 5. Care details

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

#### 6. Help

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Help
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
| Care story | http://127.0.0.1:8028/family/care/journey/request/27 |
| +1 919 555 0153 | tel:+1 919 555 0153 |
| daniel.find-care-audit@example.com | mailto:daniel.find-care-audit@example.com |
| Open full map | https://www.google.com/maps/search/?api=1&query=142%20Maple%20Grove%20Lane%2C%20Apartment%202B%2C%20Raleigh%2C%20NC%2027601 |
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
| Request options | Closed |

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
  "documentHeight": 3141,
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
    "heading": "Eleanor Bennett · Thu, Sep 10",
    "status": "Finding a caregiver",
    "headerText": "My care\n/\nOne-time care\n\nONE-TIME CARE\n\nEleanor Bennett · Thu, Sep 10\n\nSep 10, 10:00 AM · Raleigh, NC\n\nFinding a caregiver\nRequest #27\nGet help\nCare story",
    "primaryTitle": "",
    "selectedTabs": [
      "Care details"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/27?tab=overview"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 4096 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/27?tab=overview

[Focused view](<../screenshots/OT-single_applicant-overview-mobile-focus.png>) · [First screenful](<../screenshots/OT-single_applicant-overview-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/OT-single_applicant-overview-mobile-full.png>) · [Raw visible text](<../evidence/OT-single_applicant-overview-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/OT-single_applicant-overview-mobile.json>)

![Mobile screenshot of single applicant — Care details](<../screenshots/OT-single_applicant-overview-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
My care
/
One-time care

ONE-TIME CARE

Eleanor Bennett · Thu, Sep 10

Sep 10, 10:00 AM · Raleigh, NC

Finding a caregiver
Request #27
Get help
Care story
Overview
Caregivers
Care details
Help
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
Request context

SCOPE OF WORK

Companionship, preparing lunch, and light housekeeping.

TIME EXPECTATIONS

Please arrive on time and leave a short update after the visit.

HOME ACCESS

Use the main entrance and ring apartment 2B. Maria will meet you for the first visit.

ADDRESS

142 Maple Grove Lane, Apartment 2B, Raleigh, NC 27601

Open full map

RECIPIENT

Eleanor Bennett

Mother

THIRD-PARTY CONTACT

Daniel Bennett

+1 919 555 0153

daniel.find-care-audit@example.com

Task list

3 task(s)

Companionship

Conversation, a crossword puzzle, and a short walk if Ellie feels up to it.

Meal preparation

Prepare soup and a sandwich for lunch; leave the kitchen tidy.

Light housekeeping

Wash dishes and keep the living room and hallway clear.

Selected caregiver

0 invite(s) sent

No caregiver hired yet. Open the Caregivers tab to save, message, or hire someone.
Request options

© 2026 LoLo

Legal links
```

### Visible headings

| Level | Heading |
|---|---|
| H1 | Eleanor Bennett · Thu, Sep 10 |
| H2 | About Ellie |
| H3 | At a glance |
| H3 | Communication |
| H3 | Support and mobility |
| H3 | Routine and comfort |
| H3 | Important for safety |
| H3 | What the family is looking for |
| H3 | Contacts and care coordination |
| H2 | Request context |
| H2 | Task list |
| H2 | Selected caregiver |

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
- **expanded:** null
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

#### 5. Care details

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

#### 6. Help

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Help
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
| Care story | http://127.0.0.1:8028/family/care/journey/request/27 |
| +1 919 555 0153 | tel:+1 919 555 0153 |
| daniel.find-care-audit@example.com | mailto:daniel.find-care-audit@example.com |
| Open full map | https://www.google.com/maps/search/?api=1&query=142%20Maple%20Grove%20Lane%2C%20Apartment%202B%2C%20Raleigh%2C%20NC%2027601 |

### Disclosure states

| Disclosure | State |
|---|---|
| Request options | Closed |
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
  "documentHeight": 4096,
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
    "heading": "Eleanor Bennett · Thu, Sep 10",
    "status": "Finding a caregiver",
    "headerText": "My care\n/\nOne-time care\n\nONE-TIME CARE\n\nEleanor Bennett · Thu, Sep 10\n\nSep 10, 10:00 AM · Raleigh, NC\n\nFinding a caregiver\nRequest #27\nGet help\nCare story",
    "primaryTitle": "",
    "selectedTabs": [
      "Care details"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/27?tab=overview"
}
```
