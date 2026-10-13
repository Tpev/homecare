# OT-invite-confirmation — Invite caregiver — message preview before sending

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | One-time care |
| Lifecycle stage | Waiting / inviting |
| Route | /family/requests/2?tab=applicants |
| Selected tab | applicants |
| Panel / disclosure | invitation confirmation |
| Capture status | Captured |
| UX review | Audited — grouped evidence |
| UX findings | Discovery DISC-08: Use one Invite \[name\] confirmation with complete request schedule/location, retained certification context, editable message and visible Send invitation. Preserve Back/query/filter/draft context, Cancel/Close, maximum length and validation; do not auto-send. |
| Redesign decision | Proposed; not implemented |
| Owner | Unassigned |
| Priority | P2 |

These screenshots show the current application with synthetic audit data. This is capture and source-audit evidence; no UI redesign is implemented. Remote map/payment widgets were blocked in this local capture session, so their current fallback states are preserved. Google Fonts were allowed.

## Additional capture context

```json
{
  "captureVersion": 4,
  "prepared": true
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 3030 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8027/family/requests/2?tab=applicants

[Focused view](<../screenshots/OT-invite-confirmation-desktop-focus.png>) · [First screenful](<../screenshots/OT-invite-confirmation-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/OT-invite-confirmation-desktop-full.png>) · [Raw visible text](<../evidence/OT-invite-confirmation-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/OT-invite-confirmation-desktop.json>)

![Desktop screenshot of Invite caregiver — message preview before sending](<../screenshots/OT-invite-confirmation-desktop-focus.png>)

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
Eleanor Bennett · Thu, Sep 10
OPEN

One-time care - Sep 10, 10:00 AM - Raleigh, NC

Request #2

Care story

FINDING CARE

Your request is live.

Caregivers can reply now. Invite a good match if you want to move faster.

Find matching caregivers

Caregivers

Invite matching people

Care details

Needs, address, tasks

SUGGESTED CAREGIVERS

Invite one or two caregivers

Your request is live. Inviting a strong match can help you get a reply faster.

LOOKING FOR A SPECIFIC PERSON?

Invite someone you know

Search by name and invite them without leaving this request or choosing the request again.

Search and invite
People you invited

Invitations are separate from caregivers who replied.

0 total
No active invitations yet.
Recommended caregivers

Suggestions based on this request’s schedule and location.

Certifications & training
Any
⌄

3 suggested caregivers found

AR

Aisha Robinson

Same ZIP service area - Match score 124

5.0
31 reviews
Identity verified
Background check
Top caregiver

Same ZIP service area - Matches requested time window

Invite Aisha
SM

Sofia Martinez

Same ZIP service area - Match score 124

4.9
24 reviews
Identity verified
Background check
Top caregiver

Same ZIP service area - Matches requested time window

Invite Sofia
JW

James Wilson

Same ZIP service area - Match score 124

4.8
18 reviews
Identity verified
Background check
Top caregiver

Same ZIP service area - Matches requested time window

Invite James
After a caregiver replies, this screen changes to compare, chat, and hire.

CAREGIVER INVITATION

Invite a caregiver to this request
Close

A helping hand for Ellie — Waiting

When
Sep 10, 10:00 AM
Where
Raleigh, NC
Care for
Eleanor Bennett
Back to caregiver results
Invite Sofia to “A helping hand for Ellie — Waiting”?

Sep 10, 10:00 AM in Raleigh, NC. The caregiver can review the request before replying.

Invitation message (optional)

You can change this note before sending.

Cancel
Send invitation

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
| H1 | Eleanor Bennett · Thu, Sep 10 |
| H2 | Invite one or two caregivers |
| H3 | Invite someone you know |
| H3 | People you invited |
| H3 | Recommended caregivers |
| H2 | Invite a caregiver to this request |
| H3 | Invite Sofia to “A helping hand for Ellie — Waiting”? |

### Every visible control and its current value

#### 1. MB<br>Maria Bennett<br>Family

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** MB<br>Maria Bennett<br>Family
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 2. Find matching caregivers

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Find matching caregivers
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 3. Caregivers<br><br>Invite matching people

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Caregivers<br><br>Invite matching people
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 4. Care details<br><br>Needs, address, tasks

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Care details<br><br>Needs, address, tasks
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 5. Search and invite

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Search and invite
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 6. Invite Aisha

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Invite Aisha
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 7. Invite Sofia

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Invite Sofia
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 8. Invite James

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Invite James
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 9. Close caregiver invitation

- **tag:** BUTTON
- **type:** button
- **label:** Close caregiver invitation
- **text:** Close
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 10. Back to caregiver results

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Back to caregiver results
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 11. Invitation message (optional)

- **tag:** TEXTAREA
- **type:** textarea
- **label:** Invitation message (optional)
- **text:** (empty)
- **value:** Hello Sofia, Ellie would enjoy a calm visit with conversation and lunch. Please review the schedule when convenient.
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 12. Cancel

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Cancel
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 13. Send invitation

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Send invitation
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 14. Chat with LoLo Support

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
| Care story | http://127.0.0.1:8027/family/care/journey/request/2 |
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
| Certifications & training<br>Any<br>⌄ | Closed |

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
  "documentHeight": 3030,
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8027/family/requests/2?tab=applicants"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 4397 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8027/family/requests/2?tab=applicants

[Focused view](<../screenshots/OT-invite-confirmation-mobile-focus.png>) · [First screenful](<../screenshots/OT-invite-confirmation-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/OT-invite-confirmation-mobile-full.png>) · [Raw visible text](<../evidence/OT-invite-confirmation-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/OT-invite-confirmation-mobile.json>)

![Mobile screenshot of Invite caregiver — message preview before sending](<../screenshots/OT-invite-confirmation-mobile-focus.png>)

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
Eleanor Bennett · Thu, Sep 10
OPEN

One-time care - Sep 10, 10:00 AM - Raleigh, NC

Request #2

Care story

FINDING CARE

Your request is live.

Caregivers can reply now. Invite a good match if you want to move faster.

Find matching caregivers

Caregivers

Invite matching people

Care details

Needs, address, tasks

SUGGESTED CAREGIVERS

Invite one or two caregivers

Your request is live. Inviting a strong match can help you get a reply faster.

LOOKING FOR A SPECIFIC PERSON?

Invite someone you know

Search by name and invite them without leaving this request or choosing the request again.

Search and invite
People you invited

Invitations are separate from caregivers who replied.

0 total
No active invitations yet.
Recommended caregivers

Suggestions based on this request’s schedule and location.

Certifications & training
Any
⌄

3 suggested caregivers found

AR

Aisha Robinson

Same ZIP service area - Match score 124

5.0
31 reviews
Identity verified
Background check
Top caregiver

Same ZIP service area - Matches requested time window

Invite Aisha
SM

Sofia Martinez

Same ZIP service area - Match score 124

4.9
24 reviews
Identity verified
Background check
Top caregiver

Same ZIP service area - Matches requested time window

Invite Sofia
JW

James Wilson

Same ZIP service area - Match score 124

4.8
18 reviews
Identity verified
Background check
Top caregiver

Same ZIP service area - Matches requested time window

Invite James
After a caregiver replies, this screen changes to compare, chat, and hire.

CAREGIVER INVITATION

Invite a caregiver to this request
Close

A helping hand for Ellie — Waiting

When
Sep 10, 10:00 AM
Where
Raleigh, NC
Care for
Eleanor Bennett
Back to caregiver results
Invite Sofia to “A helping hand for Ellie — Waiting”?

Sep 10, 10:00 AM in Raleigh, NC. The caregiver can review the request before replying.

Invitation message (optional)

You can change this note before sending.

Cancel
Send invitation
Find matching caregivers

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
| H1 | Eleanor Bennett · Thu, Sep 10 |
| H2 | Invite one or two caregivers |
| H3 | Invite someone you know |
| H3 | People you invited |
| H3 | Recommended caregivers |
| H2 | Invite a caregiver to this request |
| H3 | Invite Sofia to “A helping hand for Ellie — Waiting”? |

### Every visible control and its current value

#### 1. Toggle navigation menu

- **tag:** BUTTON
- **type:** button
- **label:** Toggle navigation menu
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 2. Find matching caregivers

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Find matching caregivers
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 3. Caregivers<br><br>Invite matching people

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Caregivers<br><br>Invite matching people
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 4. Care details<br><br>Needs, address, tasks

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Care details<br><br>Needs, address, tasks
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 5. Search and invite

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Search and invite
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 6. Invite Aisha

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Invite Aisha
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 7. Invite Sofia

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Invite Sofia
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 8. Invite James

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Invite James
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 9. Close caregiver invitation

- **tag:** BUTTON
- **type:** button
- **label:** Close caregiver invitation
- **text:** Close
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 10. Back to caregiver results

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Back to caregiver results
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 11. Invitation message (optional)

- **tag:** TEXTAREA
- **type:** textarea
- **label:** Invitation message (optional)
- **text:** (empty)
- **value:** Hello Sofia, Ellie would enjoy a calm visit with conversation and lunch. Please review the schedule when convenient.
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 12. Cancel

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Cancel
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 13. Send invitation

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Send invitation
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 14. Find matching caregivers

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Find matching caregivers
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 15. Chat with LoLo Support

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
| Care story | http://127.0.0.1:8027/family/care/journey/request/2 |

### Disclosure states

| Disclosure | State |
|---|---|
| Certifications & training<br>Any<br>⌄ | Closed |
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
  "documentHeight": 4397,
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8027/family/requests/2?tab=applicants"
}
```
