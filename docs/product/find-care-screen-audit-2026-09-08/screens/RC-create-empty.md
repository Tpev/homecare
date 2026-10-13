# RC-create-empty — Create care request — initial form

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | Recurring care |
| Lifecycle stage | Create |
| Route | /family/requests/create |
| Selected tab | Single form |
| Preserved route key | Single form |
| Panel / disclosure | default |
| Capture status | Captured |
| UX review | Implementation rationale; validation recorded separately |
| UX findings | Creation needs a readable sequence and a complete check before publishing. |
| Redesign decision | Person/help, schedule, location/notes and review use the existing form fields and publish action. Recurring schedule previews remain requests until booked. |
| Owner | Local care workspace implementation |
| Priority |  |

These screenshots show the current application with synthetic audit data. This is capture and source-audit evidence; the local UI redesign is implemented. Remote map/payment widgets were blocked in this local capture session, so their current fallback states are preserved. Brand fonts are served locally.

## Additional capture context

```json
{
  "captureVersion": 5,
  "capturedAt": "2026-09-08T15:35:09.011Z",
  "buildHash": "58c0b4adc3736b3717582dffe837c3b2284299b26b123064150359543361e636",
  "prepared": true,
  "displayTab": "Single form"
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 2456 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/create

[Focused view](<../screenshots/RC-create-empty-desktop-focus.png>) · [First screenful](<../screenshots/RC-create-empty-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/RC-create-empty-desktop-full.png>) · [Raw visible text](<../evidence/RC-create-empty-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/RC-create-empty-desktop.json>)

![Desktop screenshot of Create care request — initial form](<../screenshots/RC-create-empty-desktop-focus.png>)

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

FIND CARE

Request care

Tell us who needs help and when. Review your details before sharing with caregivers.

Review request
↓
Chat with support
1
Person & help
2
Schedule
3
Location & notes
4
Review & publish
Use saved information (optional)

01 · PERSON & HELP

What kind of care would help?
✓
One visit
Care on one specific date and time.
✓
Recurring care
A repeating weekly schedule with a familiar caregiver.

Who is receiving care?

Me
A family member

Use a saved care profile (optional)

Choose the person once and we will share the same caregiver preview with this request.

Ellie
Mother · Reviewed Sep 7, 2026
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

02 · SCHEDULE

Recurring care schedule

Which days each week?

Choose the days first, then set the time for each day.

Sunday
Monday
Tuesday
Wednesday
Thursday
Friday
Saturday
When should this schedule begin?
How long should this repeat?
Until I stop it
End on a date
Schedule: Choose a time for each day

Repeats until you end it

03 · LOCATION & NOTES

Where will care happen?

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

More details if you want

Skip this unless it helps the caregiver arrive prepared.

Optional

04 · REVIEW

Review and publish

0 of 4 essentials ready. Fill the missing pieces, then publish.

Estimated recurring care each week

Add the schedule to see the estimate.

BEFORE PUBLISHING

Person Needed
Help Needed
Time Needed
Address Needed
For
Edit
Care recipient
Family member
Help
Edit
Choose at least one service
Schedule
Edit
Choose a time for each day
Repeats until you end it
Address
Edit
Street address
Raleigh, NC
Who can find this request?
Make this request private
Leave this unchecked to let eligible caregivers discover and apply to the request. You can still invite specific caregivers.

Next: eligible caregivers can apply. Choose a caregiver when you are ready.

Publish request
Cancel

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
| H1 | Request care |
| H2 | What kind of care would help? |
| H2 | Recurring care schedule |
| H2 | Where will care happen? |
| H2 | Review and publish |

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

#### 2. Chat with support

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

#### 3. ✓<br>One visit<br>Care on one specific date and time.

- **tag:** INPUT
- **type:** radio
- **label:** ✓<br>One visit<br>Care on one specific date and time.
- **text:** (empty)
- **value:** one\_time
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 4. ✓<br>Recurring care<br>A repeating weekly schedule with a familiar caregiver.

- **tag:** INPUT
- **type:** radio
- **label:** ✓<br>Recurring care<br>A repeating weekly schedule with a familiar caregiver.
- **text:** (empty)
- **value:** recurring
- **checked:** true
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 5. Me

- **tag:** INPUT
- **type:** radio
- **label:** Me
- **text:** (empty)
- **value:** self
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 6. A family member

- **tag:** INPUT
- **type:** radio
- **label:** A family member
- **text:** (empty)
- **value:** other
- **checked:** true
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 7. Ellie<br>Mother · Reviewed Sep 7, 2026

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Ellie<br>Mother · Reviewed Sep 7, 2026
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 8. Family member receiving care

- **tag:** INPUT
- **type:** text
- **label:** Family member receiving care
- **text:** (empty)
- **value:** (empty)
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 9. Relationship to you

- **tag:** INPUT
- **type:** text
- **label:** Relationship to you
- **text:** (empty)
- **value:** (empty)
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** Mother, father, spouse, friend

#### 10. ✓<br>Companionship

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Companionship
- **text:** (empty)
- **value:** 1
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 11. ✓<br>Daily living assistance

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Daily living assistance
- **text:** (empty)
- **value:** 7
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 12. ✓<br>Errands

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Errands
- **text:** (empty)
- **value:** 6
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 13. ✓<br>Light housekeeping

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Light housekeeping
- **text:** (empty)
- **value:** 3
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 14. ✓<br>Meal preparation

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Meal preparation
- **text:** (empty)
- **value:** 2
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 15. ✓<br>Medication reminders

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Medication reminders
- **text:** (empty)
- **value:** 5
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 16. ✓<br>Transportation

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Transportation
- **text:** (empty)
- **value:** 4
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 17. Sunday

- **tag:** INPUT
- **type:** checkbox
- **label:** Sunday
- **text:** (empty)
- **value:** 0
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 18. Monday

- **tag:** INPUT
- **type:** checkbox
- **label:** Monday
- **text:** (empty)
- **value:** 1
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 19. Tuesday

- **tag:** INPUT
- **type:** checkbox
- **label:** Tuesday
- **text:** (empty)
- **value:** 2
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 20. Wednesday

- **tag:** INPUT
- **type:** checkbox
- **label:** Wednesday
- **text:** (empty)
- **value:** 3
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 21. Thursday

- **tag:** INPUT
- **type:** checkbox
- **label:** Thursday
- **text:** (empty)
- **value:** 4
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 22. Friday

- **tag:** INPUT
- **type:** checkbox
- **label:** Friday
- **text:** (empty)
- **value:** 5
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 23. Saturday

- **tag:** INPUT
- **type:** checkbox
- **label:** Saturday
- **text:** (empty)
- **value:** 6
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 24. When should this schedule begin?

- **tag:** INPUT
- **type:** date
- **label:** When should this schedule begin?
- **text:** (empty)
- **value:** (empty)
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 25. Until I stop it

- **tag:** INPUT
- **type:** radio
- **label:** Until I stop it
- **text:** (empty)
- **value:** ongoing
- **checked:** true
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 26. End on a date

- **tag:** INPUT
- **type:** radio
- **label:** End on a date
- **text:** (empty)
- **value:** date
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 27. Street address

- **tag:** INPUT
- **type:** text
- **label:** Street address
- **text:** (empty)
- **value:** (empty)
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 28. Apartment, unit, or gate (optional)

- **tag:** INPUT
- **type:** text
- **label:** Apartment, unit, or gate (optional)
- **text:** (empty)
- **value:** (empty)
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 29. City

- **tag:** INPUT
- **type:** text
- **label:** City
- **text:** (empty)
- **value:** Raleigh
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 30. State

- **tag:** SELECT
- **type:** select-one
- **label:** State
- **text:** (empty)
- **value:** NC
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
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

#### 31. ZIP

- **tag:** INPUT
- **type:** text
- **label:** ZIP
- **text:** (empty)
- **value:** (empty)
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 32. Make this request private<br>Leave this unchecked to let eligible caregivers discover and apply to the request. You can still invite specific caregivers.

- **tag:** INPUT
- **type:** checkbox
- **label:** Make this request private<br>Leave this unchecked to let eligible caregivers discover and apply to the request. You can still invite specific caregivers.
- **text:** (empty)
- **value:** on
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 33. Publish request

- **tag:** BUTTON
- **type:** submit
- **label:** (empty)
- **text:** Publish request
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
| Review request<br>↓ | #care-review |
| 1<br>Person & help | #care-person |
| 2<br>Schedule | #care-schedule |
| 3<br>Location & notes | #care-location |
| 4<br>Review & publish | #care-review |
| Edit care recipient | #care-person |
| Edit help needed | #care-person |
| Edit care schedule | #care-schedule |
| Edit care address and notes | #care-location |
| Cancel | http://127.0.0.1:8028/family/requests |
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
| Use saved information (optional) | Closed |
| More details if you want<br><br>Skip this unless it helps the caregiver arrive prepared.<br><br>Optional | Closed |

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
  "documentHeight": 2456,
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
    "heading": "Request care",
    "status": "",
    "headerText": "FIND CARE\n\nRequest care\n\nTell us who needs help and when. Review your details before sharing with caregivers.\n\nReview request\n↓\nChat with support",
    "primaryTitle": "",
    "selectedTabs": []
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/create"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 4664 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/create

[Focused view](<../screenshots/RC-create-empty-mobile-focus.png>) · [First screenful](<../screenshots/RC-create-empty-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/RC-create-empty-mobile-full.png>) · [Raw visible text](<../evidence/RC-create-empty-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/RC-create-empty-mobile.json>)

![Mobile screenshot of Create care request — initial form](<../screenshots/RC-create-empty-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
FIND CARE

Request care

Tell us who needs help and when. Review your details before sharing with caregivers.

Review request
↓
Chat with support
1
Person & help
2
Schedule
3
Location & notes
4
Review & publish
Use saved information (optional)

01 · PERSON & HELP

What kind of care would help?
✓
One visit
Care on one specific date and time.
✓
Recurring care
A repeating weekly schedule with a familiar caregiver.

Who is receiving care?

Me
A family member

Use a saved care profile (optional)

Choose the person once and we will share the same caregiver preview with this request.

Ellie
Mother · Reviewed Sep 7, 2026
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

02 · SCHEDULE

Recurring care schedule

Which days each week?

Choose the days first, then set the time for each day.

Sunday
Monday
Tuesday
Wednesday
Thursday
Friday
Saturday
When should this schedule begin?
How long should this repeat?
Until I stop it
End on a date
Schedule: Choose a time for each day

Repeats until you end it

03 · LOCATION & NOTES

Where will care happen?

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

More details if you want

Skip this unless it helps the caregiver arrive prepared.

Optional

04 · REVIEW

Review and publish

0 of 4 essentials ready. Fill the missing pieces, then publish.

Estimated recurring care each week

Add the schedule to see the estimate.

BEFORE PUBLISHING

Person Needed
Help Needed
Time Needed
Address Needed
For
Edit
Care recipient
Family member
Help
Edit
Choose at least one service
Schedule
Edit
Choose a time for each day
Repeats until you end it
Address
Edit
Street address
Raleigh, NC
Who can find this request?
Make this request private
Leave this unchecked to let eligible caregivers discover and apply to the request. You can still invite specific caregivers.

Next: eligible caregivers can apply. Choose a caregiver when you are ready.

Publish request
Cancel

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
| H1 | Request care |
| H2 | What kind of care would help? |
| H2 | Recurring care schedule |
| H2 | Where will care happen? |
| H2 | Review and publish |

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

#### 2. Chat with support

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

#### 3. ✓<br>One visit<br>Care on one specific date and time.

- **tag:** INPUT
- **type:** radio
- **label:** ✓<br>One visit<br>Care on one specific date and time.
- **text:** (empty)
- **value:** one\_time
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 4. ✓<br>Recurring care<br>A repeating weekly schedule with a familiar caregiver.

- **tag:** INPUT
- **type:** radio
- **label:** ✓<br>Recurring care<br>A repeating weekly schedule with a familiar caregiver.
- **text:** (empty)
- **value:** recurring
- **checked:** true
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 5. Me

- **tag:** INPUT
- **type:** radio
- **label:** Me
- **text:** (empty)
- **value:** self
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 6. A family member

- **tag:** INPUT
- **type:** radio
- **label:** A family member
- **text:** (empty)
- **value:** other
- **checked:** true
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 7. Ellie<br>Mother · Reviewed Sep 7, 2026

- **tag:** BUTTON
- **type:** button
- **label:** (empty)
- **text:** Ellie<br>Mother · Reviewed Sep 7, 2026
- **value:** (empty)
- **disabled:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 8. Family member receiving care

- **tag:** INPUT
- **type:** text
- **label:** Family member receiving care
- **text:** (empty)
- **value:** (empty)
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 9. Relationship to you

- **tag:** INPUT
- **type:** text
- **label:** Relationship to you
- **text:** (empty)
- **value:** (empty)
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** Mother, father, spouse, friend

#### 10. ✓<br>Companionship

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Companionship
- **text:** (empty)
- **value:** 1
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 11. ✓<br>Daily living assistance

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Daily living assistance
- **text:** (empty)
- **value:** 7
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 12. ✓<br>Errands

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Errands
- **text:** (empty)
- **value:** 6
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 13. ✓<br>Light housekeeping

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Light housekeeping
- **text:** (empty)
- **value:** 3
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 14. ✓<br>Meal preparation

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Meal preparation
- **text:** (empty)
- **value:** 2
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 15. ✓<br>Medication reminders

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Medication reminders
- **text:** (empty)
- **value:** 5
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 16. ✓<br>Transportation

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Transportation
- **text:** (empty)
- **value:** 4
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 17. Sunday

- **tag:** INPUT
- **type:** checkbox
- **label:** Sunday
- **text:** (empty)
- **value:** 0
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 18. Monday

- **tag:** INPUT
- **type:** checkbox
- **label:** Monday
- **text:** (empty)
- **value:** 1
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 19. Tuesday

- **tag:** INPUT
- **type:** checkbox
- **label:** Tuesday
- **text:** (empty)
- **value:** 2
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 20. Wednesday

- **tag:** INPUT
- **type:** checkbox
- **label:** Wednesday
- **text:** (empty)
- **value:** 3
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 21. Thursday

- **tag:** INPUT
- **type:** checkbox
- **label:** Thursday
- **text:** (empty)
- **value:** 4
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 22. Friday

- **tag:** INPUT
- **type:** checkbox
- **label:** Friday
- **text:** (empty)
- **value:** 5
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 23. Saturday

- **tag:** INPUT
- **type:** checkbox
- **label:** Saturday
- **text:** (empty)
- **value:** 6
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 24. When should this schedule begin?

- **tag:** INPUT
- **type:** date
- **label:** When should this schedule begin?
- **text:** (empty)
- **value:** (empty)
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 25. Until I stop it

- **tag:** INPUT
- **type:** radio
- **label:** Until I stop it
- **text:** (empty)
- **value:** ongoing
- **checked:** true
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 26. End on a date

- **tag:** INPUT
- **type:** radio
- **label:** End on a date
- **text:** (empty)
- **value:** date
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 27. Street address

- **tag:** INPUT
- **type:** text
- **label:** Street address
- **text:** (empty)
- **value:** (empty)
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 28. Apartment, unit, or gate (optional)

- **tag:** INPUT
- **type:** text
- **label:** Apartment, unit, or gate (optional)
- **text:** (empty)
- **value:** (empty)
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 29. City

- **tag:** INPUT
- **type:** text
- **label:** City
- **text:** (empty)
- **value:** Raleigh
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 30. State

- **tag:** SELECT
- **type:** select-one
- **label:** State
- **text:** (empty)
- **value:** NC
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
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

#### 31. ZIP

- **tag:** INPUT
- **type:** text
- **label:** ZIP
- **text:** (empty)
- **value:** (empty)
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 32. Make this request private<br>Leave this unchecked to let eligible caregivers discover and apply to the request. You can still invite specific caregivers.

- **tag:** INPUT
- **type:** checkbox
- **label:** Make this request private<br>Leave this unchecked to let eligible caregivers discover and apply to the request. You can still invite specific caregivers.
- **text:** (empty)
- **value:** on
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 33. Publish request

- **tag:** BUTTON
- **type:** submit
- **label:** (empty)
- **text:** Publish request
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
| Review request<br>↓ | #care-review |
| 1<br>Person & help | #care-person |
| 2<br>Schedule | #care-schedule |
| 3<br>Location & notes | #care-location |
| 4<br>Review & publish | #care-review |
| Edit care recipient | #care-person |
| Edit help needed | #care-person |
| Edit care schedule | #care-schedule |
| Edit care address and notes | #care-location |
| Cancel | http://127.0.0.1:8028/family/requests |
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
| Use saved information (optional) | Closed |
| More details if you want<br><br>Skip this unless it helps the caregiver arrive prepared.<br><br>Optional | Closed |

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
  "documentHeight": 4664,
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
    "heading": "Request care",
    "status": "",
    "headerText": "FIND CARE\n\nRequest care\n\nTell us who needs help and when. Review your details before sharing with caregivers.\n\nReview request\n↓\nChat with support",
    "primaryTitle": "",
    "selectedTabs": []
  },
  "horizontalOverflow": 0,
  "finalUrl": "http://127.0.0.1:8028/family/requests/create"
}
```
