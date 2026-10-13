# RC-create-self-private — Create — care for me / private request ready to publish

[Master tracker](<../master-tracker.md>) · [Visual screenshot browser](<../index.html>)

| Audit field | Value |
|---|---|
| Care variation | Recurring care |
| Lifecycle stage | Create |
| Route | /family/requests/create?type=recurring |
| Selected tab | Single form |
| Preserved route key | Single form |
| Panel / disclosure | filled / self / private |
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
  "capturedAt": "2026-09-08T15:39:08.622Z",
  "buildHash": "58c0b4adc3736b3717582dffe837c3b2284299b26b123064150359543361e636",
  "prepared": true,
  "displayTab": "Single form"
}
```

## Desktop

Viewport: **1440 × 1000 CSS px**. Document height: 2846 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/create?type=recurring

[Focused view](<../screenshots/RC-create-self-private-desktop-focus.png>) · [First screenful](<../screenshots/RC-create-self-private-desktop-viewport.png>) · [Full-page screenshot](<../screenshots/RC-create-self-private-desktop-full.png>) · [Raw visible text](<../evidence/RC-create-self-private-desktop.txt>) · [Raw information inventory (JSON)](<../evidence/RC-create-self-private-desktop.json>)

![Desktop screenshot of Create — care for me / private request ready to publish](<../screenshots/RC-create-self-private-desktop-focus.png>)

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
✓
Person & help
— Ready
✓
Schedule
— Ready
✓
Location & notes
— Ready
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

Care recipient: Maria Bennett

This request will be marked as care for the person posting it.

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

What time on each day?

Each day can have a different start time and visit length.

Monday

Ends at 12:00 PM

Starts at
Visit length
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

Wednesday

Ends at 4:00 PM

Starts at
Visit length
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
When should this schedule begin?
How long should this repeat?
Until I stop it
End on a date
Last day
Schedule: Mon · 10:00 AM–12:00 PM; Wed · 1:00 PM–4:00 PM · starting Sep 14, 2026

Last day: Nov 6, 2026

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

All essentials are ready. Review once, then publish.

Estimated recurring care each week

$155.00/ week

Care · 5.00h × $30.00/hr*
$150.00
Processing fee · $1.00/hr
$5.00

*A $1.00/hour processing fee is added to the $30.00/hour care rate.

You are not paying for every future visit now. LoLo confirms your card before each visit and charges the final amount after that visit.

BEFORE PUBLISHING

Person Ready
Help Ready
Time Ready
Address Ready
For
Edit
Maria Bennett
Self
Help
Edit
Companionship
Schedule
Edit
Mon · 10:00 AM–12:00 PM; Wed · 1:00 PM–4:00 PM · starting Sep 14, 2026
Last day: Nov 6, 2026
FIRST PLANNED VISIT
Mon, Sep 14, 2026 · 10:00 AM–12:00 PM
Address
Edit
210 Oakwood Avenue
Side entrance
Raleigh, NC 27601
Who can find this request?
Make this request private
Invite only. Only caregivers you invite can view and respond to it. Hidden from the caregiver marketplace.

Next: choose caregivers to invite. You will need to invite someone to get replies.

Create private request
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
- **checked:** true
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
- **checked:** false
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

#### 8. ✓<br>Companionship

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Companionship
- **text:** (empty)
- **value:** 1
- **checked:** true
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 9. ✓<br>Daily living assistance

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

#### 10. ✓<br>Errands

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

#### 11. ✓<br>Light housekeeping

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

#### 12. ✓<br>Meal preparation

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

#### 13. ✓<br>Medication reminders

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

#### 14. ✓<br>Transportation

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

#### 15. Sunday

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

#### 16. Monday

- **tag:** INPUT
- **type:** checkbox
- **label:** Monday
- **text:** (empty)
- **value:** 1
- **checked:** true
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 17. Tuesday

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

#### 18. Wednesday

- **tag:** INPUT
- **type:** checkbox
- **label:** Wednesday
- **text:** (empty)
- **value:** 3
- **checked:** true
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 19. Thursday

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

#### 20. Friday

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

#### 21. Saturday

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

#### 22. Starts at

- **tag:** INPUT
- **type:** time
- **label:** Starts at
- **text:** (empty)
- **value:** 10:00
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 23. Visit length

- **tag:** SELECT
- **type:** select-one
- **label:** Visit length
- **text:** (empty)
- **value:** 120
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null
- **Dropdown options:**
  - 01:00 (1 hour) — value: 60; selected: false
  - 01:30 (1h 30m) — value: 90; selected: false
  - 02:00 (2 hours) — value: 120; selected: true
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

#### 24. Starts at

- **tag:** INPUT
- **type:** time
- **label:** Starts at
- **text:** (empty)
- **value:** 13:00
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 25. Visit length

- **tag:** SELECT
- **type:** select-one
- **label:** Visit length
- **text:** (empty)
- **value:** 180
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null
- **Dropdown options:**
  - 01:00 (1 hour) — value: 60; selected: false
  - 01:30 (1h 30m) — value: 90; selected: false
  - 02:00 (2 hours) — value: 120; selected: false
  - 02:30 (2h 30m) — value: 150; selected: false
  - 03:00 (3 hours) — value: 180; selected: true
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

#### 26. When should this schedule begin?

- **tag:** INPUT
- **type:** date
- **label:** When should this schedule begin?
- **text:** (empty)
- **value:** 2026-09-14
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 27. Until I stop it

- **tag:** INPUT
- **type:** radio
- **label:** Until I stop it
- **text:** (empty)
- **value:** ongoing
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 28. End on a date

- **tag:** INPUT
- **type:** radio
- **label:** End on a date
- **text:** (empty)
- **value:** date
- **checked:** true
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 29. Last day

- **tag:** INPUT
- **type:** date
- **label:** Last day
- **text:** (empty)
- **value:** 2026-11-06
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 30. Street address

- **tag:** INPUT
- **type:** text
- **label:** Street address
- **text:** (empty)
- **value:** 210 Oakwood Avenue
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 31. Apartment, unit, or gate (optional)

- **tag:** INPUT
- **type:** text
- **label:** Apartment, unit, or gate (optional)
- **text:** (empty)
- **value:** Side entrance
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 32. City

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

#### 33. State

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

#### 34. ZIP

- **tag:** INPUT
- **type:** text
- **label:** ZIP
- **text:** (empty)
- **value:** 27601
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 35. Make this request private<br>Invite only. Only caregivers you invite can view and respond to it. Hidden from the caregiver marketplace.

- **tag:** INPUT
- **type:** checkbox
- **label:** Make this request private<br>Invite only. Only caregivers you invite can view and respond to it. Hidden from the caregiver marketplace.
- **text:** (empty)
- **value:** on
- **checked:** true
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 36. Create private request

- **tag:** BUTTON
- **type:** submit
- **label:** (empty)
- **text:** Create private request
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
| ✓<br>Person & help<br>— Ready | #care-person |
| ✓<br>Schedule<br>— Ready | #care-schedule |
| ✓<br>Location & notes<br>— Ready | #care-location |
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
  "documentHeight": 2846,
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
  "finalUrl": "http://127.0.0.1:8028/family/requests/create?type=recurring"
}
```

## Mobile

Viewport: **390 × 844 CSS px**. Document height: 5663 px. Horizontal overflow: 0 px.

Captured URL: http://127.0.0.1:8028/family/requests/create?type=recurring

[Focused view](<../screenshots/RC-create-self-private-mobile-focus.png>) · [First screenful](<../screenshots/RC-create-self-private-mobile-viewport.png>) · [Full-page screenshot](<../screenshots/RC-create-self-private-mobile-full.png>) · [Raw visible text](<../evidence/RC-create-self-private-mobile.txt>) · [Raw information inventory (JSON)](<../evidence/RC-create-self-private-mobile.json>)

![Mobile screenshot of Create — care for me / private request ready to publish](<../screenshots/RC-create-self-private-mobile-focus.png>)

### Everything visible as text

This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.

```
FIND CARE

Request care

Tell us who needs help and when. Review your details before sharing with caregivers.

Review request
↓
Chat with support
✓
Person & help
— Ready
✓
Schedule
— Ready
✓
Location & notes
— Ready
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

Care recipient: Maria Bennett

This request will be marked as care for the person posting it.

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

What time on each day?

Each day can have a different start time and visit length.

Monday

Ends at 12:00 PM

Starts at
Visit length
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

Wednesday

Ends at 4:00 PM

Starts at
Visit length
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
When should this schedule begin?
How long should this repeat?
Until I stop it
End on a date
Last day
Schedule: Mon · 10:00 AM–12:00 PM; Wed · 1:00 PM–4:00 PM · starting Sep 14, 2026

Last day: Nov 6, 2026

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

All essentials are ready. Review once, then publish.

Estimated recurring care each week

$155.00/ week

Care · 5.00h × $30.00/hr*
$150.00
Processing fee · $1.00/hr
$5.00

*A $1.00/hour processing fee is added to the $30.00/hour care rate.

You are not paying for every future visit now. LoLo confirms your card before each visit and charges the final amount after that visit.

BEFORE PUBLISHING

Person Ready
Help Ready
Time Ready
Address Ready
For
Edit
Maria Bennett
Self
Help
Edit
Companionship
Schedule
Edit
Mon · 10:00 AM–12:00 PM; Wed · 1:00 PM–4:00 PM · starting Sep 14, 2026
Last day: Nov 6, 2026
FIRST PLANNED VISIT
Mon, Sep 14, 2026 · 10:00 AM–12:00 PM
Address
Edit
210 Oakwood Avenue
Side entrance
Raleigh, NC 27601
Who can find this request?
Make this request private
Invite only. Only caregivers you invite can view and respond to it. Hidden from the caregiver marketplace.

Next: choose caregivers to invite. You will need to invite someone to get replies.

Create private request
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
- **checked:** true
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
- **checked:** false
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

#### 8. ✓<br>Companionship

- **tag:** INPUT
- **type:** checkbox
- **label:** ✓<br>Companionship
- **text:** (empty)
- **value:** 1
- **checked:** true
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 9. ✓<br>Daily living assistance

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

#### 10. ✓<br>Errands

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

#### 11. ✓<br>Light housekeeping

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

#### 12. ✓<br>Meal preparation

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

#### 13. ✓<br>Medication reminders

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

#### 14. ✓<br>Transportation

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

#### 15. Sunday

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

#### 16. Monday

- **tag:** INPUT
- **type:** checkbox
- **label:** Monday
- **text:** (empty)
- **value:** 1
- **checked:** true
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 17. Tuesday

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

#### 18. Wednesday

- **tag:** INPUT
- **type:** checkbox
- **label:** Wednesday
- **text:** (empty)
- **value:** 3
- **checked:** true
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 19. Thursday

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

#### 20. Friday

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

#### 21. Saturday

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

#### 22. Starts at

- **tag:** INPUT
- **type:** time
- **label:** Starts at
- **text:** (empty)
- **value:** 10:00
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 23. Visit length

- **tag:** SELECT
- **type:** select-one
- **label:** Visit length
- **text:** (empty)
- **value:** 120
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null
- **Dropdown options:**
  - 01:00 (1 hour) — value: 60; selected: false
  - 01:30 (1h 30m) — value: 90; selected: false
  - 02:00 (2 hours) — value: 120; selected: true
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

#### 24. Starts at

- **tag:** INPUT
- **type:** time
- **label:** Starts at
- **text:** (empty)
- **value:** 13:00
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 25. Visit length

- **tag:** SELECT
- **type:** select-one
- **label:** Visit length
- **text:** (empty)
- **value:** 180
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null
- **Dropdown options:**
  - 01:00 (1 hour) — value: 60; selected: false
  - 01:30 (1h 30m) — value: 90; selected: false
  - 02:00 (2 hours) — value: 120; selected: false
  - 02:30 (2h 30m) — value: 150; selected: false
  - 03:00 (3 hours) — value: 180; selected: true
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

#### 26. When should this schedule begin?

- **tag:** INPUT
- **type:** date
- **label:** When should this schedule begin?
- **text:** (empty)
- **value:** 2026-09-14
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 27. Until I stop it

- **tag:** INPUT
- **type:** radio
- **label:** Until I stop it
- **text:** (empty)
- **value:** ongoing
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 28. End on a date

- **tag:** INPUT
- **type:** radio
- **label:** End on a date
- **text:** (empty)
- **value:** date
- **checked:** true
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 29. Last day

- **tag:** INPUT
- **type:** date
- **label:** Last day
- **text:** (empty)
- **value:** 2026-11-06
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 30. Street address

- **tag:** INPUT
- **type:** text
- **label:** Street address
- **text:** (empty)
- **value:** 210 Oakwood Avenue
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 31. Apartment, unit, or gate (optional)

- **tag:** INPUT
- **type:** text
- **label:** Apartment, unit, or gate (optional)
- **text:** (empty)
- **value:** Side entrance
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 32. City

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

#### 33. State

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

#### 34. ZIP

- **tag:** INPUT
- **type:** text
- **label:** ZIP
- **text:** (empty)
- **value:** 27601
- **checked:** false
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 35. Make this request private<br>Invite only. Only caregivers you invite can view and respond to it. Hidden from the caregiver marketplace.

- **tag:** INPUT
- **type:** checkbox
- **label:** Make this request private<br>Invite only. Only caregivers you invite can view and respond to it. Hidden from the caregiver marketplace.
- **text:** (empty)
- **value:** on
- **checked:** true
- **disabled:** false
- **required:** false
- **current:** null
- **expanded:** null
- **pressed:** null
- **placeholder:** null

#### 36. Create private request

- **tag:** BUTTON
- **type:** submit
- **label:** (empty)
- **text:** Create private request
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
| ✓<br>Person & help<br>— Ready | #care-person |
| ✓<br>Schedule<br>— Ready | #care-schedule |
| ✓<br>Location & notes<br>— Ready | #care-location |
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
  "documentHeight": 5663,
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
  "finalUrl": "http://127.0.0.1:8028/family/requests/create?type=recurring"
}
```
