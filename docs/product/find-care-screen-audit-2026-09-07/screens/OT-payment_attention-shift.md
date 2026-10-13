# OT-payment_attention-shift — payment attention — Visit

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | One-time care |
| Lifecycle stage | Hired / before shift |
| Route | /family/requests/31?tab=shift |
| Selected tab | shift |
| Panel / disclosure | default |
| Capture status | Captured |
| UX review | Audited — grouped evidence |
| UX findings | Delivery D10 and recurring RC-UX-06: Put exact visit payment action first, separate booking status from saved card/authorization/capture and retain amounts/errors. A synthetic authorized amount does not establish a successful current charge. |
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

Viewport: **1440 × 1000 CSS px**. Document height: 2822 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8027/family/requests/31?tab=shift

[Focused view](<../screenshots/OT-payment_attention-shift-desktop-focus.png>) · [First screenful](<../screenshots/OT-payment_attention-shift-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/OT-payment_attention-shift-desktop-full.png>) · [Raw visible text](<../evidence/OT-payment_attention-shift-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/OT-payment_attention-shift-desktop.json>)

![Desktop screenshot of payment attention — Visit](<../screenshots/OT-payment_attention-shift-desktop-focus.png>)

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

Card authorization needs attention.

Your card was declined. Update your payment method to keep this visit on track.

Confirm card authorization

Eleanor Bennett · Wed, Sep 9
FILLED
VISIT Scheduled
Payment Failed

One-time care - Sep 09, 10:00 AM - 12:00 PM - Raleigh, NC

Visit #19

Care story

VISIT SCHEDULED

Your visit is scheduled.

Your caregiver is booked. Check the time and address, message them if needed, or change the visit before it starts.

RIGHT NOW

Sofia is coming on Sep 09, 10:00 AM - 12:00 PM.

Message caregiver
Change or cancel

Visit

Time, location, payment

Caregiver

Profile, chat

Help

Change or get help

Care details

Needs, address, tasks

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
Support
```

### Visible headings

| Level | Heading |
|---|---|
| H2 | About Ellie |
| H3 | At a glance |
| H3 | Communication |
| H3 | Support and mobility |
| H3 | Routine and comfort |
| H3 | Important for safety |
| H3 | What the family is looking for |
| H3 | Contacts and care coordination |
| H1 | Eleanor Bennett · Wed, Sep 9 |
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
- **placeholder:** null

#### 2. Confirm card authorization

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Confirm card authorization
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 3. Message caregiver

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Message caregiver
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 4. Change or cancel

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Change or cancel
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 5. Visit<br><br>Time, location, payment

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Visit<br><br>Time, location, payment
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 6. Caregiver<br><br>Profile, chat

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Caregiver<br><br>Profile, chat
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 7. Help<br><br>Change or get help

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Help<br><br>Change or get help
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 8. Care details<br><br>Needs, address, tasks

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Care details<br><br>Needs, address, tasks
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 9. Chat with LoLo Support

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
| +1 919 555 0153 | tel:+1 919 555 0153 |
| daniel.find-care-audit@example.com | mailto:daniel.find-care-audit@example.com |
| Care story | http://127.0.0.1:8027/family/care/journey/request/31 |
| Open map | https://www.google.com/maps/search/?api=1&query=142%20Maple%20Grove%20Lane%2C%20Apartment%202B%2C%20Raleigh%2C%20NC%2027601 |
| Terms of Service | http://127.0.0.1:8027/legal/platform-terms-of-service |
| Privacy Policy | http://127.0.0.1:8027/legal/privacy-policy |
| Payments & Cancellation | http://127.0.0.1:8027/legal/payment-terms-and-cancellation-policy |
| Caregiver Terms | http://127.0.0.1:8027/legal/caregiver-terms |
| Family Terms | http://127.0.0.1:8027/legal/client-and-family-terms |
| Safety Policy | http://127.0.0.1:8027/legal/acceptable-use-and-safety-policy |
| All Legal Documents | http://127.0.0.1:8027/legal |

### Disclosure states

| Disclosure | State |
|---|---|
| If the caregiver is late | Closed |
| Map and visit record | Closed |

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
  "documentHeight": 2822,
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8027/family/requests/31?tab=shift"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 4043 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8027/family/requests/31?tab=shift

[Focused view](<../screenshots/OT-payment_attention-shift-mobile-focus.png>) · [First screenful](<../screenshots/OT-payment_attention-shift-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/OT-payment_attention-shift-mobile-full.png>) · [Raw visible text](<../evidence/OT-payment_attention-shift-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/OT-payment_attention-shift-mobile.json>)

![Mobile screenshot of payment attention — Visit](<../screenshots/OT-payment_attention-shift-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
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

Card authorization needs attention.

Your card was declined. Update your payment method to keep this visit on track.

Confirm card authorization

Eleanor Bennett · Wed, Sep 9
FILLED
VISIT Scheduled
Payment Failed

One-time care - Sep 09, 10:00 AM - 12:00 PM - Raleigh, NC

Visit #19

Care story

VISIT SCHEDULED

Your visit is scheduled.

Your caregiver is booked. Check the time and address, message them if needed, or change the visit before it starts.

RIGHT NOW

Sofia is coming on Sep 09, 10:00 AM - 12:00 PM.

Message caregiver
Change or cancel

Visit

Time, location, payment

Caregiver

Profile, chat

Help

Change or get help

Care details

Needs, address, tasks

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
| H2 | About Ellie |
| H3 | At a glance |
| H3 | Communication |
| H3 | Support and mobility |
| H3 | Routine and comfort |
| H3 | Important for safety |
| H3 | What the family is looking for |
| H3 | Contacts and care coordination |
| H1 | Eleanor Bennett · Wed, Sep 9 |
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
- **placeholder:** null

#### 2. Confirm card authorization

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Confirm card authorization
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 3. Message caregiver

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Message caregiver
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 4. Change or cancel

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Change or cancel
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 5. Visit<br><br>Time, location, payment

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Visit<br><br>Time, location, payment
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 6. Caregiver<br><br>Profile, chat

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Caregiver<br><br>Profile, chat
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 7. Help<br><br>Change or get help

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Help<br><br>Change or get help
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 8. Care details<br><br>Needs, address, tasks

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Care details<br><br>Needs, address, tasks
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 9. Confirm card authorization

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Confirm card authorization
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 10. Chat with LoLo Support

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
| +1 919 555 0153 | tel:+1 919 555 0153 |
| daniel.find-care-audit@example.com | mailto:daniel.find-care-audit@example.com |
| Care story | http://127.0.0.1:8027/family/care/journey/request/31 |
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
| LoLo | http://127.0.0.1:8027/images/marketing/lolo/lolo-app-icon.svg | true |
| LoLo | http://127.0.0.1:8027/images/marketing/lolo/lolo-wordmark-evergreen.svg | true |

### Capture metadata

```json
{
  "width": 390,
  "height": 844,
  "documentWidth": 390,
  "documentHeight": 4043,
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8027/family/requests/31?tab=shift"
}
```
