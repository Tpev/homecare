# RC-leave_review-overview — leave review — Care details

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | Recurring care |
| Lifecycle stage | Timesheet / review |
| Route | /family/requests/20?tab=overview |
| Selected tab | Care instructions |
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
  "capturedAt": "2026-09-08T15:30:19.480Z",
  "buildHash": "58c0b4adc3736b3717582dffe837c3b2284299b26b123064150359543361e636",
  "prepared": false,
  "displayTab": "Care instructions"
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 3115 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/20?tab=overview

[Focused view](<../screenshots/RC-leave_review-overview-desktop-focus.png>) · [First screenful](<../screenshots/RC-leave_review-overview-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/RC-leave_review-overview-desktop-full.png>) · [Raw visible text](<../evidence/RC-leave_review-overview-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/RC-leave_review-overview-desktop.json>)

![Desktop screenshot of leave review — Care details](<../screenshots/RC-leave_review-overview-desktop-focus.png>)

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
Visit #12

ONE VISIT IN YOUR RECURRING CARE

Eleanor Bennett · Sat, Sep 5

Sep 05, 10:00 AM - 12:00 PM · Raleigh, NC

Caregiver: Sofia Martinez

Reviewed
Visit #12
Message caregiver
Get help
Care story
This visit
Care instructions
Help & changes
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

Sofia Martinez

Care rate: $30.00/hr* · processing fee $1.00/hr

7 years experience - 4.9 stars from 24 reviews

View profile
Go to visit

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
| H1 | Eleanor Bennett · Sat, Sep 5 |
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
- **current:** page
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

#### 7. View profile

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** View profile
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 8. Go to visit

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Go to visit
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
| Back to recurring care · Visits | http://127.0.0.1:8028/family/care/1?tab=visits&visit\_filter=upcoming&visitsPage=1#care-visit-12 |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/1 |
| +1 919 555 0153 | tel:+1 919 555 0153 |
| daniel.find-care-audit@example.com | mailto:daniel.find-care-audit@example.com |
| Open full map | https://www.google.com/maps/search/?api=1&query=142%20Maple%20Grove%20Lane%2C%20Apartment%202B%2C%20Raleigh%2C%20NC%2027601 |
| View profile | http://127.0.0.1:8028/caregivers/sofia-find-care-audit?careRequest=20 |
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
  "documentHeight": 3115,
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
    "heading": "Eleanor Bennett · Sat, Sep 5",
    "status": "Reviewed",
    "headerText": "My care\n/\nBack to recurring care · Visits\n/\nVisit #12\n\nONE VISIT IN YOUR RECURRING CARE\n\nEleanor Bennett · Sat, Sep 5\n\nSep 05, 10:00 AM - 12:00 PM · Raleigh, NC\n\nCaregiver: Sofia Martinez\n\nReviewed\nVisit #12\nMessage caregiver\nGet help\nCare story",
    "primaryTitle": "",
    "selectedTabs": [
      "Care instructions"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/20?tab=overview"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 4202 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/20?tab=overview

[Focused view](<../screenshots/RC-leave_review-overview-mobile-focus.png>) · [First screenful](<../screenshots/RC-leave_review-overview-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/RC-leave_review-overview-mobile-full.png>) · [Raw visible text](<../evidence/RC-leave_review-overview-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/RC-leave_review-overview-mobile.json>)

![Mobile screenshot of leave review — Care details](<../screenshots/RC-leave_review-overview-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
My care
/
Back to recurring care · Visits
/
Visit #12

ONE VISIT IN YOUR RECURRING CARE

Eleanor Bennett · Sat, Sep 5

Sep 05, 10:00 AM - 12:00 PM · Raleigh, NC

Caregiver: Sofia Martinez

Reviewed
Visit #12
Message caregiver
Get help
Care story
This visit
Care instructions
Help & changes
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

Sofia Martinez

Care rate: $30.00/hr* · processing fee $1.00/hr

7 years experience - 4.9 stars from 24 reviews

View profile
Go to visit

© 2026 LoLo

Legal links
```

### Visible headings

| Level | Heading |
|---|---|
| H1 | Eleanor Bennett · Sat, Sep 5 |
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
- **current:** page
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

#### 7. View profile

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** View profile
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 8. Go to visit

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Go to visit
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
| Back to recurring care · Visits | http://127.0.0.1:8028/family/care/1?tab=visits&visit\_filter=upcoming&visitsPage=1#care-visit-12 |
| Care story | http://127.0.0.1:8028/family/care/journey/regular/1 |
| +1 919 555 0153 | tel:+1 919 555 0153 |
| daniel.find-care-audit@example.com | mailto:daniel.find-care-audit@example.com |
| Open full map | https://www.google.com/maps/search/?api=1&query=142%20Maple%20Grove%20Lane%2C%20Apartment%202B%2C%20Raleigh%2C%20NC%2027601 |
| View profile | http://127.0.0.1:8028/caregivers/sofia-find-care-audit?careRequest=20 |

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
  "documentHeight": 4202,
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
    "heading": "Eleanor Bennett · Sat, Sep 5",
    "status": "Reviewed",
    "headerText": "My care\n/\nBack to recurring care · Visits\n/\nVisit #12\n\nONE VISIT IN YOUR RECURRING CARE\n\nEleanor Bennett · Sat, Sep 5\n\nSep 05, 10:00 AM - 12:00 PM · Raleigh, NC\n\nCaregiver: Sofia Martinez\n\nReviewed\nVisit #12\nMessage caregiver\nGet help\nCare story",
    "primaryTitle": "",
    "selectedTabs": [
      "Care instructions"
    ]
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/20?tab=overview"
}
```
