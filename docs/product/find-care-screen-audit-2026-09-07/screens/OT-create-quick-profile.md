# OT-create-quick-profile — Create — simple care profile draft for a new family

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | One-time care |
| Lifecycle stage | Create |
| Route | /family/requests/create?type=one\_time |
| Selected tab | Single form |
| Panel / disclosure | simple care profile expanded |
| Capture status | Captured |
| UX review | Audited — grouped evidence |
| UX findings | Discovery DISC-06: Retain both quick-profile questions, entered answers, Skip and sharing acknowledgment; make this optional context concise and preserve the distinction from name/relationship and later visit notes. Keep one-time date, start, duration/end, exact-address fields and all cost/privacy details. |
| Redesign decision | Proposed; not implemented |
| Owner | Unassigned |
| Priority | P2 |

These screenshots show the current application with synthetic audit data. This is capture and source-audit evidence; no UI redesign is implemented. Remote map/payment widgets were blocked in this local capture session, so their current fallback states are preserved. Google Fonts were allowed.

## Additional capture context

```json
{
  "focusSelector": "textarea[wire\\:model=\"quick_profile_about\"]",
  "captureVersion": 4,
  "prepared": true
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 3117 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8027/family/requests/create?type=one\_time

[Focused view](<../screenshots/OT-create-quick-profile-desktop-focus.png>) · [First screenful](<../screenshots/OT-create-quick-profile-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/OT-create-quick-profile-desktop-full.png>) · [Raw visible text](<../evidence/OT-create-quick-profile-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/OT-create-quick-profile-desktop.json>)

![Desktop screenshot of Create — simple care profile draft for a new family](<../screenshots/OT-create-quick-profile-desktop-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
FAMILY WORKSPACE
Care
Caregivers
Messages
Request care
NB
Nora Brooks
Family

REQUEST CARE

What kind of care would help?

Choose one visit or a regular schedule. The same details and safeguards stay with your care from request through payment.

BEFORE PUBLISHING

2 of 4 essentials ready. Fill the missing pieces, then publish.

PERSON

Ready

Harold Brooks

HELP

Ready

Companionship

TIME

Needed

Choose a day and start time

ADDRESS

Needed

Add care address

Choose a care pattern

Start here. This choice changes the schedule questions below, not how the rest of your care works.

✓
One visit
Care on one specific date and time.
✓
Recurring care
A repeating weekly schedule with a familiar caregiver.
Who and what

Start with the person receiving care and the help they need.

Who is receiving care?

Me
A family member

Simple care profile

Caregivers who can view this request will see these answers. You can add more later.

Skip
What should a caregiver know?
What helps care go well?
I understand that these answers will be shared only with caregivers who can view this care request. Contact details, the exact address, and date of birth are not included.
Family member receiving care
Relationship to you

Help needed

✓
Companionship
✓
Daily living assistance
✓
Errands
✓
Light housekeeping
✓
Meal preparation
✓
Medication reminders
✓
Transportation

More details if you want

Skip this unless it helps the caregiver arrive prepared.

Optional
Visit schedule

Add only the dates and times that apply to the care pattern you chose.

Starting day
Starting time
Duration (HH:MM)
01:00 (1 hour)
01:30 (1h 30m)
02:00 (2 hours)
02:30 (2h 30m)
03:00 (3 hours)
03:30 (3h 30m)
04:00 (4 hours)
04:30 (4h 30m)
05:00 (5 hours)
05:30 (5h 30m)
06:00 (6 hours)
06:30 (6h 30m)
07:00 (7 hours)
07:30 (7h 30m)
08:00 (8 hours)
08:30 (8h 30m)
09:00 (9 hours)
09:30 (9h 30m)
10:00 (10 hours)
10:30 (10h 30m)
11:00 (11 hours)
11:30 (11h 30m)
12:00 (12 hours)
Schedule: Choose a day and start time
Where

Caregivers see the area before applying. Exact details stay with the request.

Street address
Apartment, unit, or gate (optional)
City
State
Alabama
Arizona
Arkansas
California
Colorado
Connecticut
Delaware
Florida
Georgia
Idaho
Illinois
Indiana
Iowa
Kansas
Kentucky
Louisiana
Maine
Maryland
Massachusetts
Michigan
Minnesota
Mississippi
Missouri
Montana
Nebraska
Nevada
New Hampshire
New Jersey
New Mexico
New York
North Carolina
North Dakota
Ohio
Oklahoma
Oregon
Pennsylvania
Rhode Island
South Carolina
South Dakota
Tennessee
Texas
Utah
Vermont
Virginia
Washington
West Virginia
Wisconsin
Wyoming
District of Columbia
ZIP
Review and publish

Check the essentials before caregivers see the request.

Make this request private
Leave this unchecked to let eligible caregivers discover and apply to the request. You can still invite specific caregivers.

FOR

Harold Brooks

Father

HELP

Companionship

SCHEDULE

Choose a day and start time

ADDRESS

Street address

Raleigh, NC

ESTIMATED ONE-TIME COST

Add the schedule to see the estimate.

Cancel
Publish request

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
| H1 | What kind of care would help? |
| H2 | Choose a care pattern |
| H2 | Who and what |
| H2 | Visit schedule |
| H2 | Where |
| H2 | Review and publish |

### Every visible control and its current value

#### 1. NB<br>Nora Brooks<br>Family

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** NB<br>Nora Brooks<br>Family
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 2. ✓<br>One visit<br>Care on one specific date and time.

- **tag:** INPUT
- **type:** radio
- **label:** ✓<br>One visit<br>Care on one specific date and time.
- **text:** (empty)
- **value:** one\_time
- **checked:** true
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 3. ✓<br>Recurring care<br>A repeating weekly schedule with a familiar caregiver.

- **tag:** INPUT
- **type:** radio
- **label:** ✓<br>Recurring care<br>A repeating weekly schedule with a familiar caregiver.
- **text:** (empty)
- **value:** recurring
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 4. Me

- **tag:** INPUT
- **type:** radio
- **label:** Me
- **text:** (empty)
- **value:** self
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 5. A family member

- **tag:** INPUT
- **type:** radio
- **label:** A family member
- **text:** (empty)
- **value:** other
- **checked:** true
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 6. Skip

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Skip
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 7. What should a caregiver know?

- **tag:** TEXTAREA
- **type:** textarea
- **label:** What should a caregiver know?
- **text:** (empty)
- **value:** Harold is a retired librarian who enjoys jazz, garden walks, and talking about books. He appreciates a friendly introduction and time to get to know someone.
- **disabled:** false
- **required:** false
- **placeholder:** A few warm, useful details about the person.

#### 8. What helps care go well?

- **tag:** TEXTAREA
- **type:** textarea
- **label:** What helps care go well?
- **text:** (empty)
- **value:** A relaxed pace, clear choices, and a cup of tea help Harold feel comfortable. Please ask about his usual routine and give him time to answer.
- **disabled:** false
- **required:** false
- **placeholder:** Routines, interests, reassurance, or communication tips.

#### 9. I understand that these answers will be shared only with caregivers who can view this care request. Contact details, the exact address, and date of birth are not included.

- **tag:** INPUT
- **type:** checkbox
- **label:** I understand that these answers will be shared only with caregivers who can view this care request. Contact details, the exact address, and date of birth are not included.
- **text:** (empty)
- **value:** on
- **checked:** true
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 10. Family member receiving care

- **tag:** INPUT
- **type:** text
- **label:** Family member receiving care
- **text:** (empty)
- **value:** Harold Brooks
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 11. Relationship to you

- **tag:** INPUT
- **type:** text
- **label:** Relationship to you
- **text:** (empty)
- **value:** Father
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** Mother, father, spouse, friend

#### 12. ✓<br>Companionship

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Companionship
- **text:** (empty)
- **value:** 1
- **checked:** true
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 13. ✓<br>Daily living assistance

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Daily living assistance
- **text:** (empty)
- **value:** 7
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 14. ✓<br>Errands

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Errands
- **text:** (empty)
- **value:** 6
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 15. ✓<br>Light housekeeping

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Light housekeeping
- **text:** (empty)
- **value:** 3
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 16. ✓<br>Meal preparation

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Meal preparation
- **text:** (empty)
- **value:** 2
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 17. ✓<br>Medication reminders

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Medication reminders
- **text:** (empty)
- **value:** 5
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 18. ✓<br>Transportation

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Transportation
- **text:** (empty)
- **value:** 4
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 19. Starting day

- **tag:** INPUT
- **type:** date
- **label:** Starting day
- **text:** (empty)
- **value:** (empty)
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 20. Starting time

- **tag:** INPUT
- **type:** time
- **label:** Starting time
- **text:** (empty)
- **value:** (empty)
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 21. Duration (HH:MM)

- **tag:** SELECT
- **type:** select-one
- **label:** Duration (HH:MM)
- **text:** (empty)
- **value:** 60
- **disabled:** false
- **required:** false
- **placeholder:** null
- **Dropdown options:**
  - 01:00 (1 hour) — value: 60; selected: true
  - 01:30 (1h 30m) — value: 90; selected: false
  - 02:00 (2 hours) — value: 120; selected: false
  - 02:30 (2h 30m) — value: 150; selected: false
  - 03:00 (3 hours) — value: 180; selected: false
  - 03:30 (3h 30m) — value: 210; selected: false
  - 04:00 (4 hours) — value: 240; selected: false
  - 04:30 (4h 30m) — value: 270; selected: false
  - 05:00 (5 hours) — value: 300; selected: false
  - 05:30 (5h 30m) — value: 330; selected: false
  - 06:00 (6 hours) — value: 360; selected: false
  - 06:30 (6h 30m) — value: 390; selected: false
  - 07:00 (7 hours) — value: 420; selected: false
  - 07:30 (7h 30m) — value: 450; selected: false
  - 08:00 (8 hours) — value: 480; selected: false
  - 08:30 (8h 30m) — value: 510; selected: false
  - 09:00 (9 hours) — value: 540; selected: false
  - 09:30 (9h 30m) — value: 570; selected: false
  - 10:00 (10 hours) — value: 600; selected: false
  - 10:30 (10h 30m) — value: 630; selected: false
  - 11:00 (11 hours) — value: 660; selected: false
  - 11:30 (11h 30m) — value: 690; selected: false
  - 12:00 (12 hours) — value: 720; selected: false

#### 22. Street address

- **tag:** INPUT
- **type:** text
- **label:** Street address
- **text:** (empty)
- **value:** (empty)
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 23. Apartment, unit, or gate (optional)

- **tag:** INPUT
- **type:** text
- **label:** Apartment, unit, or gate (optional)
- **text:** (empty)
- **value:** (empty)
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 24. City

- **tag:** INPUT
- **type:** text
- **label:** City
- **text:** (empty)
- **value:** Raleigh
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 25. State

- **tag:** SELECT
- **type:** select-one
- **label:** State
- **text:** (empty)
- **value:** NC
- **disabled:** false
- **required:** false
- **placeholder:** null
- **Dropdown options:**
  - Alabama — value: AL; selected: false
  - Arizona — value: AZ; selected: false
  - Arkansas — value: AR; selected: false
  - California — value: CA; selected: false
  - Colorado — value: CO; selected: false
  - Connecticut — value: CT; selected: false
  - Delaware — value: DE; selected: false
  - Florida — value: FL; selected: false
  - Georgia — value: GA; selected: false
  - Idaho — value: ID; selected: false
  - Illinois — value: IL; selected: false
  - Indiana — value: IN; selected: false
  - Iowa — value: IA; selected: false
  - Kansas — value: KS; selected: false
  - Kentucky — value: KY; selected: false
  - Louisiana — value: LA; selected: false
  - Maine — value: ME; selected: false
  - Maryland — value: MD; selected: false
  - Massachusetts — value: MA; selected: false
  - Michigan — value: MI; selected: false
  - Minnesota — value: MN; selected: false
  - Mississippi — value: MS; selected: false
  - Missouri — value: MO; selected: false
  - Montana — value: MT; selected: false
  - Nebraska — value: NE; selected: false
  - Nevada — value: NV; selected: false
  - New Hampshire — value: NH; selected: false
  - New Jersey — value: NJ; selected: false
  - New Mexico — value: NM; selected: false
  - New York — value: NY; selected: false
  - North Carolina — value: NC; selected: true
  - North Dakota — value: ND; selected: false
  - Ohio — value: OH; selected: false
  - Oklahoma — value: OK; selected: false
  - Oregon — value: OR; selected: false
  - Pennsylvania — value: PA; selected: false
  - Rhode Island — value: RI; selected: false
  - South Carolina — value: SC; selected: false
  - South Dakota — value: SD; selected: false
  - Tennessee — value: TN; selected: false
  - Texas — value: TX; selected: false
  - Utah — value: UT; selected: false
  - Vermont — value: VT; selected: false
  - Virginia — value: VA; selected: false
  - Washington — value: WA; selected: false
  - West Virginia — value: WV; selected: false
  - Wisconsin — value: WI; selected: false
  - Wyoming — value: WY; selected: false
  - District of Columbia — value: DC; selected: false

#### 26. ZIP

- **tag:** INPUT
- **type:** text
- **label:** ZIP
- **text:** (empty)
- **value:** (empty)
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 27. Make this request private<br>Leave this unchecked to let eligible caregivers discover and apply to the request. You can still invite specific caregivers.

- **tag:** INPUT
- **type:** checkbox
- **label:** Make this request private<br>Leave this unchecked to let eligible caregivers discover and apply to the request. You can still invite specific caregivers.
- **text:** (empty)
- **value:** on
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 28. Publish request

- **tag:** BUTTON
- **type:** submit
- **label:** (empty)
- **text:** Publish request
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 29. Chat with LoLo Support

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
| Cancel | http://127.0.0.1:8027/family/requests |
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
| More details if you want<br><br>Skip this unless it helps the caregiver arrive prepared.<br><br>Optional | Closed |

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
  "documentHeight": 3117,
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8027/family/requests/create?type=one_time"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 4642 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8027/family/requests/create?type=one\_time

[Focused view](<../screenshots/OT-create-quick-profile-mobile-focus.png>) · [First screenful](<../screenshots/OT-create-quick-profile-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/OT-create-quick-profile-mobile-full.png>) · [Raw visible text](<../evidence/OT-create-quick-profile-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/OT-create-quick-profile-mobile.json>)

![Mobile screenshot of Create — simple care profile draft for a new family](<../screenshots/OT-create-quick-profile-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
REQUEST CARE

What kind of care would help?

Choose one visit or a regular schedule. The same details and safeguards stay with your care from request through payment.

BEFORE PUBLISHING

2 of 4 essentials ready. Fill the missing pieces, then publish.

Person ready
Help ready
Time needed
Address needed
Choose a care pattern

Start here. This choice changes the schedule questions below, not how the rest of your care works.

✓
One visit
Care on one specific date and time.
✓
Recurring care
A repeating weekly schedule with a familiar caregiver.
Who and what

Start with the person receiving care and the help they need.

Who is receiving care?

Me
A family member

Simple care profile

Caregivers who can view this request will see these answers. You can add more later.

Skip
What should a caregiver know?
What helps care go well?
I understand that these answers will be shared only with caregivers who can view this care request. Contact details, the exact address, and date of birth are not included.
Family member receiving care
Relationship to you

Help needed

✓
Companionship
✓
Daily living assistance
✓
Errands
✓
Light housekeeping
✓
Meal preparation
✓
Medication reminders
✓
Transportation

More details if you want

Skip this unless it helps the caregiver arrive prepared.

Optional
Visit schedule

Add only the dates and times that apply to the care pattern you chose.

Starting day
Starting time
Duration (HH:MM)
01:00 (1 hour)
01:30 (1h 30m)
02:00 (2 hours)
02:30 (2h 30m)
03:00 (3 hours)
03:30 (3h 30m)
04:00 (4 hours)
04:30 (4h 30m)
05:00 (5 hours)
05:30 (5h 30m)
06:00 (6 hours)
06:30 (6h 30m)
07:00 (7 hours)
07:30 (7h 30m)
08:00 (8 hours)
08:30 (8h 30m)
09:00 (9 hours)
09:30 (9h 30m)
10:00 (10 hours)
10:30 (10h 30m)
11:00 (11 hours)
11:30 (11h 30m)
12:00 (12 hours)
Schedule: Choose a day and start time
Where

Caregivers see the area before applying. Exact details stay with the request.

Street address
Apartment, unit, or gate (optional)
City
State
Alabama
Arizona
Arkansas
California
Colorado
Connecticut
Delaware
Florida
Georgia
Idaho
Illinois
Indiana
Iowa
Kansas
Kentucky
Louisiana
Maine
Maryland
Massachusetts
Michigan
Minnesota
Mississippi
Missouri
Montana
Nebraska
Nevada
New Hampshire
New Jersey
New Mexico
New York
North Carolina
North Dakota
Ohio
Oklahoma
Oregon
Pennsylvania
Rhode Island
South Carolina
South Dakota
Tennessee
Texas
Utah
Vermont
Virginia
Washington
West Virginia
Wisconsin
Wyoming
District of Columbia
ZIP
Review and publish

Check the essentials before caregivers see the request.

Make this request private
Leave this unchecked to let eligible caregivers discover and apply to the request. You can still invite specific caregivers.

FOR

Harold Brooks

Father

HELP

Companionship

SCHEDULE

Choose a day and start time

ADDRESS

Street address

Raleigh, NC

ESTIMATED ONE-TIME COST

Add the schedule to see the estimate.

Cancel
Publish request

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
| H1 | What kind of care would help? |
| H2 | Choose a care pattern |
| H2 | Who and what |
| H2 | Visit schedule |
| H2 | Where |
| H2 | Review and publish |

### Every visible control and its current value

#### 1. Toggle navigation menu

- **tag:** BUTTON
- **type:** button
- **label:** Toggle navigation menu
- **text:** (empty)
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 2. ✓<br>One visit<br>Care on one specific date and time.

- **tag:** INPUT
- **type:** radio
- **label:** ✓<br>One visit<br>Care on one specific date and time.
- **text:** (empty)
- **value:** one\_time
- **checked:** true
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 3. ✓<br>Recurring care<br>A repeating weekly schedule with a familiar caregiver.

- **tag:** INPUT
- **type:** radio
- **label:** ✓<br>Recurring care<br>A repeating weekly schedule with a familiar caregiver.
- **text:** (empty)
- **value:** recurring
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 4. Me

- **tag:** INPUT
- **type:** radio
- **label:** Me
- **text:** (empty)
- **value:** self
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 5. A family member

- **tag:** INPUT
- **type:** radio
- **label:** A family member
- **text:** (empty)
- **value:** other
- **checked:** true
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 6. Skip

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Skip
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 7. What should a caregiver know?

- **tag:** TEXTAREA
- **type:** textarea
- **label:** What should a caregiver know?
- **text:** (empty)
- **value:** Harold is a retired librarian who enjoys jazz, garden walks, and talking about books. He appreciates a friendly introduction and time to get to know someone.
- **disabled:** false
- **required:** false
- **placeholder:** A few warm, useful details about the person.

#### 8. What helps care go well?

- **tag:** TEXTAREA
- **type:** textarea
- **label:** What helps care go well?
- **text:** (empty)
- **value:** A relaxed pace, clear choices, and a cup of tea help Harold feel comfortable. Please ask about his usual routine and give him time to answer.
- **disabled:** false
- **required:** false
- **placeholder:** Routines, interests, reassurance, or communication tips.

#### 9. I understand that these answers will be shared only with caregivers who can view this care request. Contact details, the exact address, and date of birth are not included.

- **tag:** INPUT
- **type:** checkbox
- **label:** I understand that these answers will be shared only with caregivers who can view this care request. Contact details, the exact address, and date of birth are not included.
- **text:** (empty)
- **value:** on
- **checked:** true
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 10. Family member receiving care

- **tag:** INPUT
- **type:** text
- **label:** Family member receiving care
- **text:** (empty)
- **value:** Harold Brooks
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 11. Relationship to you

- **tag:** INPUT
- **type:** text
- **label:** Relationship to you
- **text:** (empty)
- **value:** Father
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** Mother, father, spouse, friend

#### 12. ✓<br>Companionship

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Companionship
- **text:** (empty)
- **value:** 1
- **checked:** true
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 13. ✓<br>Daily living assistance

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Daily living assistance
- **text:** (empty)
- **value:** 7
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 14. ✓<br>Errands

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Errands
- **text:** (empty)
- **value:** 6
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 15. ✓<br>Light housekeeping

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Light housekeeping
- **text:** (empty)
- **value:** 3
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 16. ✓<br>Meal preparation

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Meal preparation
- **text:** (empty)
- **value:** 2
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 17. ✓<br>Medication reminders

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Medication reminders
- **text:** (empty)
- **value:** 5
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 18. ✓<br>Transportation

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Transportation
- **text:** (empty)
- **value:** 4
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 19. Starting day

- **tag:** INPUT
- **type:** date
- **label:** Starting day
- **text:** (empty)
- **value:** (empty)
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 20. Starting time

- **tag:** INPUT
- **type:** time
- **label:** Starting time
- **text:** (empty)
- **value:** (empty)
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 21. Duration (HH:MM)

- **tag:** SELECT
- **type:** select-one
- **label:** Duration (HH:MM)
- **text:** (empty)
- **value:** 60
- **disabled:** false
- **required:** false
- **placeholder:** null
- **Dropdown options:**
  - 01:00 (1 hour) — value: 60; selected: true
  - 01:30 (1h 30m) — value: 90; selected: false
  - 02:00 (2 hours) — value: 120; selected: false
  - 02:30 (2h 30m) — value: 150; selected: false
  - 03:00 (3 hours) — value: 180; selected: false
  - 03:30 (3h 30m) — value: 210; selected: false
  - 04:00 (4 hours) — value: 240; selected: false
  - 04:30 (4h 30m) — value: 270; selected: false
  - 05:00 (5 hours) — value: 300; selected: false
  - 05:30 (5h 30m) — value: 330; selected: false
  - 06:00 (6 hours) — value: 360; selected: false
  - 06:30 (6h 30m) — value: 390; selected: false
  - 07:00 (7 hours) — value: 420; selected: false
  - 07:30 (7h 30m) — value: 450; selected: false
  - 08:00 (8 hours) — value: 480; selected: false
  - 08:30 (8h 30m) — value: 510; selected: false
  - 09:00 (9 hours) — value: 540; selected: false
  - 09:30 (9h 30m) — value: 570; selected: false
  - 10:00 (10 hours) — value: 600; selected: false
  - 10:30 (10h 30m) — value: 630; selected: false
  - 11:00 (11 hours) — value: 660; selected: false
  - 11:30 (11h 30m) — value: 690; selected: false
  - 12:00 (12 hours) — value: 720; selected: false

#### 22. Street address

- **tag:** INPUT
- **type:** text
- **label:** Street address
- **text:** (empty)
- **value:** (empty)
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 23. Apartment, unit, or gate (optional)

- **tag:** INPUT
- **type:** text
- **label:** Apartment, unit, or gate (optional)
- **text:** (empty)
- **value:** (empty)
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 24. City

- **tag:** INPUT
- **type:** text
- **label:** City
- **text:** (empty)
- **value:** Raleigh
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 25. State

- **tag:** SELECT
- **type:** select-one
- **label:** State
- **text:** (empty)
- **value:** NC
- **disabled:** false
- **required:** false
- **placeholder:** null
- **Dropdown options:**
  - Alabama — value: AL; selected: false
  - Arizona — value: AZ; selected: false
  - Arkansas — value: AR; selected: false
  - California — value: CA; selected: false
  - Colorado — value: CO; selected: false
  - Connecticut — value: CT; selected: false
  - Delaware — value: DE; selected: false
  - Florida — value: FL; selected: false
  - Georgia — value: GA; selected: false
  - Idaho — value: ID; selected: false
  - Illinois — value: IL; selected: false
  - Indiana — value: IN; selected: false
  - Iowa — value: IA; selected: false
  - Kansas — value: KS; selected: false
  - Kentucky — value: KY; selected: false
  - Louisiana — value: LA; selected: false
  - Maine — value: ME; selected: false
  - Maryland — value: MD; selected: false
  - Massachusetts — value: MA; selected: false
  - Michigan — value: MI; selected: false
  - Minnesota — value: MN; selected: false
  - Mississippi — value: MS; selected: false
  - Missouri — value: MO; selected: false
  - Montana — value: MT; selected: false
  - Nebraska — value: NE; selected: false
  - Nevada — value: NV; selected: false
  - New Hampshire — value: NH; selected: false
  - New Jersey — value: NJ; selected: false
  - New Mexico — value: NM; selected: false
  - New York — value: NY; selected: false
  - North Carolina — value: NC; selected: true
  - North Dakota — value: ND; selected: false
  - Ohio — value: OH; selected: false
  - Oklahoma — value: OK; selected: false
  - Oregon — value: OR; selected: false
  - Pennsylvania — value: PA; selected: false
  - Rhode Island — value: RI; selected: false
  - South Carolina — value: SC; selected: false
  - South Dakota — value: SD; selected: false
  - Tennessee — value: TN; selected: false
  - Texas — value: TX; selected: false
  - Utah — value: UT; selected: false
  - Vermont — value: VT; selected: false
  - Virginia — value: VA; selected: false
  - Washington — value: WA; selected: false
  - West Virginia — value: WV; selected: false
  - Wisconsin — value: WI; selected: false
  - Wyoming — value: WY; selected: false
  - District of Columbia — value: DC; selected: false

#### 26. ZIP

- **tag:** INPUT
- **type:** text
- **label:** ZIP
- **text:** (empty)
- **value:** (empty)
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 27. Make this request private<br>Leave this unchecked to let eligible caregivers discover and apply to the request. You can still invite specific caregivers.

- **tag:** INPUT
- **type:** checkbox
- **label:** Make this request private<br>Leave this unchecked to let eligible caregivers discover and apply to the request. You can still invite specific caregivers.
- **text:** (empty)
- **value:** on
- **checked:** false
- **disabled:** false
- **required:** false
- **placeholder:** null

#### 28. Publish request

- **tag:** BUTTON
- **type:** submit
- **label:** (empty)
- **text:** Publish request
- **value:** (empty)
- **disabled:** false
- **placeholder:** null

#### 29. Chat with LoLo Support

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
| Cancel | http://127.0.0.1:8027/family/requests |
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
| More details if you want<br><br>Skip this unless it helps the caregiver arrive prepared.<br><br>Optional | Closed |

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
  "documentHeight": 4642,
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8027/family/requests/create?type=one_time"
}
```
