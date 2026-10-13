# One-time find-care: source inventory

Audit date: 2026-09-07. This is a source-derived inventory of the current family-facing implementation. It describes what the view can display; screenshots and live data values are tracked in the master audit. No UI changes are proposed or implemented here. The same request-management view also renders individual recurring visits; plan-level differences belong in the recurring inventory.

## Reading and capture conventions

- Each matrix row needs a desktop and mobile capture. For a long page, retain a full-page capture and enough viewport/section captures to read its content. A tab hidden below a long recipient profile is still a separate screen.
- A source-backed field does not prove that the capture fixture contains a value. Empty and conditional fields are called out below; use screenshot evidence for actual values.
- Capture expanded disclosures separately. Capture drawer contents through its own scroll area, including below-fold results. Merely photographing the unopened disclosure does not cover its fields.
- Ordinary browser-native confirmation dialogs are separate transient states. Stripe authentication is an external/payment-dependent branch, not a static local screen.
- Source notation uses repository-relative paths and one-based anchor lines. The project root is `C:/Users/Epicpp/homecare`.

## Routes and lifecycle

The create screen is one continuous form despite its `CreateCareRequestWizard` class name. There are no numbered wizard-step pages or tabs in the rendered form. Its route is `/family/requests/create?type=one_time` (`routes/web.php:352`). Selecting Recurring care changes the schedule section in place.

Request management is `/family/requests/{careRequest}?tab={key}` (`routes/web.php:362`). Accepted tab keys are `overview`, `applicants`, `shift`, and `support`, restricted to the tabs of the current lifecycle. Loading an unavailable tab falls back to the lifecycle's default. The visible labels/order change with state (`app/Livewire/Family/ManageCareRequest.php:47`, `:158`, `:199`; `app/Support/CareRequestProgress.php:177`).

| Audit ID | User stage / actual variant | Trigger | Visible tabs in order; default first unless noted |
|---|---|---|---|
| OT-C01 | Create, blank or partially filled | Create route; `request_type=one_time` | No tabs: care pattern → who/what → schedule → where → review/publish |
| OT-C02 | Create, complete with optional sections expanded | Same route; other recipient; quick profile or saved profile; extra notes/contact | Same long form |
| OT-C03 | Create, self / private / reuse variants | `care_for=self`; `is_private=true`; saved-profile or last-request load | Same long form; private publish CTA changes |
| OT-W01 | Waiting, public request, no active replies | Request open, no booking, zero applied/shortlisted candidates | Caregivers (`applicants`), Care details (`overview`) |
| OT-W02 | Waiting, private request | Open private request; zero invitations triggers warning | Same tabs; `?tab=applicants&invite=1` opens invitation drawer on arrival |
| OT-W03 | Exactly one reply | Open, no booking, one visible application, lifecycle reviewing | Same tabs, but application list is removed and featured decision card is in shared header |
| OT-W04 | Multiple replies | Open, no booking, 2+ visible applications | Caregivers, Care details |
| OT-W05 | Filterable replies | Open with 4+ total applications | Same tabs; Filter or sort caregivers disclosure appears |
| OT-W06 | Invitation drawer, initial groups / search results | `openCaregiverInvitePanel` / search at least 2 letters | Modal over current request; initial groups and search results are distinct states |
| OT-W07 | Invitation confirmation / reinvite / sent feedback | `beginCaregiverInvitation(id[, true])` | Modal confirmation replaces results; sent feedback appears after action |
| OT-H01 | Hired, awaiting shift | Booking `scheduled` | Visit (`shift`), Caregiver (`applicants`), Help (`support`), Care details (`overview`) |
| OT-H02 | Hired, no-show window available | Scheduled, current time ≥ scheduled start + 30 minutes | Same tabs; late disclosure replaced with no-show action |
| OT-H03 | Caregiver selected but visit pending | No booking, request neither open nor cancelled | Care details (default), Caregiver; source fallback, not normal successful one-time hiring |
| OT-D01 | During shift | Booking `in_progress` | Visit, Help, Caregiver, Care details |
| OT-D02 | Paused shift | Booking `paused` | Visit, Help, Caregiver, Care details |
| OT-A01 | After shift / timesheet awaiting review | Booking completed or reviewed; no family confirmation; no active correction | Visit, Help, Caregiver, Care details |
| OT-A02 | Submitted-time correction pending | Feature enabled, latest correction pending family | Same management route; correction panel on Visit; standard approval strip suppressed while active correction exists |
| OT-A03 | Time-correction confirmation / changes requested / payment needed / escalated | Corresponding correction status or confirmation state | Correction panel content changes; payment-attention banner may appear globally |
| OT-R01 | Hours confirmed, leave review | Completed/reviewed booking with family confirmation, no review by current family user | Visit, Caregiver, Help, Care details; review form at bottom of Visit |
| OT-X01 | Closed, reviewed | Family confirmation + family review; usually booking `reviewed` | Visit, Caregiver, Help, Care details |
| OT-X02 | Cancelled / caregiver no-show | Booking `cancelled`; distinguish `no_show_flag` | Visit, Help, Caregiver, Care details |
| OT-X03 | Disputed | Booking `disputed` | Help (`support`, default), Visit, Caregiver, Care details |
| OT-X04 | Withdrawn before hiring | Request `cancelled`, no booking | Care details (`overview`), Caregivers (`applicants`) |
| OT-X05 | Original date passed without hire | Open/draft one-time request with application window ended | Care details (`overview`, default), Caregivers, Help; Help is only an unavailable-tools message without a booking |
| OT-B01 | Book caregiver again | Eligible confirmed/completed non-plan visit | `/family/requests/{id}/book-again`; single page with one-visit form and recurring-care handoff |
| OT-J01 | Care story | Header link | `/family/care/journey/request/{id}`; connected journey page, no request-management tabs |

The user terms “After the shift” and “review timesheet” map to the same primary implemented state, OT-A01. Hours confirmed but review not yet submitted is the distinct OT-R01 state. The top-level lifecycle says “This visit is complete” before a family review necessarily exists. A review is keyed to the currently signed-in reviewer, so shared-account users can have different review-form visibility.

## Shared chrome and request header

Shared shell is `resources/views/layouts/app.blade.php:44`; navigation is `resources/views/livewire/layout/navigation.blade.php:402`. In ordinary family mode, navigation offers Care, Caregivers, Messages (unread count if applicable), and Request care; LoLo branding links back to Care. The header also offers notifications with an unread badge, account/avatar menu, and a mobile navigation toggle. Capture mobile menu/account menu once as shared chrome if navigation is in redesign scope.

Account menu family actions include account settings, change password, Support Center with unread count, messages, Care profiles, Family access, Billing & Payments, notifications, and Log Out. Footer contains copyright, Terms of Service, Privacy Policy, Payments & Cancellation, Caregiver Terms, Family Terms, Safety Policy, All Legal Documents. On request management mobile, these are inside “Legal links”; desktop exposes them directly. A support chat widget is mounted for family users. Its expanded conversation is a shared application surface rather than a lifecycle tab.

Every request-management tab includes these shared items (`manage-care-request.blade.php:1`, `:350`, `:386`):

- Optional AI-prepared correction notice; warning flash; success/status flash.
- Private/open/no-invitation warning explaining marketplace invisibility and inviting at least one caregiver.
- Optional full care-recipient profile summary before the request header; its full field inventory is below.
- Conditional payment-attention banner: card authorization or approved-hours payment needs attention; payment error/explanation, corrected duration and amount if applicable; Confirm card authorization / Confirm $amount payment action; “Opening secure confirmation…” busy label.
- Headline: recipient name plus request/visit weekday and date, or recurring recipient title; uppercase request status; PRIVATE badge; visit status badge; payment status badge.
- Care type (One-time care / Recurring care), scheduled date/start/end (request-only one-time line shows its start), city/state; Request # or Visit #.
- Care story link; Review caregivers / Invite caregivers action on other tabs while open and date valid; Open chat link when hired conversation exists except scheduled/live.
- Lifecycle eyebrow, headline and explanatory text. These remain on every tab.
- Scheduled/live “Right now” panel: caregiver first name + scheduled time, actual check-in time, or paused explanation.
- Stage actions: waiting Find matching caregivers; scheduled Message caregiver + Change or cancel; live Message caregiver + The visit has ended + Get help; passed date Choose another date + Back to Care; eligible completed non-plan visit Book {first name} again.
- Timesheet-awaiting-approval header: submitted duration in hours/minutes and raw minutes; estimated payment total; care amount and hourly rate; processing-fee amount/rate or legacy platform percentage; Approve hours and pay $amount; Question hours.
- Multiple-reply header: visible reply count, instruction to review cards, Invite someone else.
- Exactly-one-reply header: “Ready to choose”, caregiver name, Interested/Saved status, years experience if nonzero, rating if reviews exist, up to three certification tags and overflow count, cover-note excerpt up to 180 characters, repeated status badge, Hire {first name}, Chat first/Open chat, View profile if slug, Invite someone you know. This card is shared header content, including on Care details; the normal applicant list is entirely suppressed for this condition.
- Tab buttons show label and description: Care details / “Needs, address, tasks”; Caregivers / either “Invite matching people”, “Review, chat, hire”, or “Past responses”; Caregiver / “Profile, chat”; Visit / “Time, location, payment”; Help / “Change or get help”.
- Withdrawn request adds a notice below header stating applications/invitation replies are closed.

Mobile adds a fixed bottom CTA, in priority order (`manage-care-request.blade.php:332`, `:1816`): payment confirmation; Choose another date; Approve hours and pay $amount; The visit has ended; Open visit details; Find matching caregivers; Review caregivers. No bottom primary CTA is generated for a normal closed/confirmed record. It is shown across tabs, not only the primary tab.

## Create: all visible information and controls

Source: `resources/views/livewire/family/create-care-request-wizard.blade.php:1`; logic/options `app/Livewire/Family/CreateCareRequestWizard.php:156`, `:207`, `:1329`.

1. Header: Request care, “What kind of care would help?”, explanatory copy about one visit or a regular schedule.
2. Before publishing: readiness count out of four, ready/incomplete guidance; Person, Help, Time, Address. Desktop shows each status and value/helper text. Mobile shows compact ready/needed chips without the detailed helper text.
3. Saved-information panel if a last request or saved household/recipient exists: Save time; reuse explanation; Use last request and/or Use saved details; loaded-state message reminding schedule review. Mobile collapses this under “Use saved information (optional)”.
4. Care pattern: One visit and Recurring care radio cards, selected state/check mark, specific-date vs repeating-week explanation. Feature-gated Around-the-clock care card with Set up link exists outside the requested two-variant scope.
5. Who and what: Me / A family member choice. Me displays resolved recipient name and request-for-self explanation. Other displays Family member receiving care and Relationship to you fields.
6. Optional saved care profile: profile display name, relationship, last-reviewed date per card, selected state, Change button. If no saved profile, Add a simple care profile invitation. Expanded quick profile shows What should a caregiver know?, What helps care go well?, sharing acknowledgment checkbox and detailed sharing copy, Skip button. It does not expose full care-profile editor fields here.
7. Help needed: selected/unselected service checkboxes, each task's database name sorted alphabetically. Do not infer the live set from fixtures; source loads all CareTask rows. Field-level validation messages can appear.
8. “More details if you want” optional disclosure: Anything important for the caregiver?; Care notes; Home access; Timing note; examples/placeholders; Add another contact checkbox and explanatory copy. When checked: Contact name, Relationship, Phone, Email (optional). Disclosure opens automatically when relevant notes/contact data is present.
9. Visit schedule: Starting day (minimum available day), Starting time, Duration (HH:MM), resolved Schedule line. Duration options are 01:00–12:00 in 30-minute increments. Input/date/duration validation appears below each respective field.
10. Recurring schedule branch in same create form: Sunday–Saturday checkboxes; selected-day sections each with day name, calculated end time or choose-time message, Starts at, Visit length; schedule begin date with possible adjustment message; Until I stop it / End on a date choice; conditional Last day; resolved Schedule. The weekly estimate replaces one-time estimate. Included here because switching the main care pattern is a single form variation.
11. Where: exact Street address; Apartment, unit, or gate (optional); City; State selector; ZIP. Helper explains general area before application and exact details retained with request.
12. Review and publish: Make this request private checkbox; private/public discovery explanation; For (resolved name + relationship), Help (selected task names), Schedule, Address (street + city/state/ZIP); incomplete-placeholder values when not ready.
13. Estimated one-time cost (or estimated recurring care each week): care hours × hourly rate and amount; processing hourly fee and amount; estimated total; processing fee footnote; missing-schedule message if unavailable. Recurring adds explanation that card is confirmed before each visit and final amount charged after each visit.
14. Footer actions: Cancel, Publish request / Create private request, disabled loading state. Public creation navigates to management; private creation adds `tab=applicants&invite=1` (`CreateCareRequestWizard.php:754`).
15. Optional top alerts: AI copied details without publishing; loaded homepage-request draft status; other session success. These are data-entry origins, not distinct lifecycle stages.

Class properties for Fast Track/Complete Setup, gender, mobility, and other former workflow fields are not currently rendered by this create template. They must not be added to the visible-screen inventory merely because the class still contains them.

## Care details tab (`overview`)

Source: `manage-care-request.blade.php:594` and `:1792`. Includes the shared header, then:

- Request context: Scope of work; Time expectations; Home access; full Address with optional line 2; service map iframe; Open full map.
- Recipient card: full name and relationship to family; placeholders if absent.
- Conditional third-party contact: full name, phone, email. Its relationship is not shown in this card even though it is editable at creation.
- Task list: task count; each task name; task note or “No additional notes”; empty-task message.
- Selected caregiver: invite count; if hired, name, family care rate per hour, processing fee per hour, years experience, average rating and review count if available, View profile, Open chat if conversation, Go to visit if booking. Otherwise no-caregiver-hired message and guidance to Caregivers tab.
- Request options disclosure only for draft/open and no booking: withdrawal effect explanation; Withdraw request; browser confirmation “Withdraw this request? Caregivers will no longer be able to apply.”

## Caregivers tab (`applicants`)

Source: `manage-care-request.blade.php:738`. The entire section below is absent for the exactly-one-visible-reply featured-card state described above.

- Stage heading: waiting “Suggested caregivers / Invite one or two caregivers”; reviewing “Caregiver replies / Review replies below”; selected/past “Caregiver selection / Selected caregiver” or “Caregivers”. Corresponding explanatory copy. Non-waiting/non-reviewing has Ready / number to review.
- When open: Invite someone you know panel, search-by-name explanation, Search and invite button.
- People you invited: explanatory distinction from replies; total invitations; active cards show caregiver name, Invitation sent or Accepted invitation badge, relative Sent/Replied time. Empty state “No active invitations yet.”
- Past invitations disclosure if any: count; each caregiver name, effective status (expired overrides stored status), updated date.
- Waiting: Recommended caregivers, schedule/location matching explanation, certification filter, suggestion cards (field inventory below), after-reply explanation.
- Filter or sort caregivers disclosure when open and total applications ≥4: Status (All caregivers, Interested, Saved, Hired, Declined, Not selected, Withdrawn); Sort (Latest first, Oldest first, Rate high-low, Rate low-high); application-specific certification filter. Filtered result count is announced to screen readers.
- Closed/filled message: hired caregiver name and profile/chat availability, or generic no-longer-open explanation.
- Caregivers who replied: explanatory text; each visible application card contains photo/initials, name, application status badge, city/state, years experience, star average + review count or No reviews yet, reliability percentage if truthy, certification tags.
- Profile details disclosure per card: Identity verified, Background check, Top Caregiver, Accepting clients/Limited availability badges as applicable; biography excerpt up to 220 characters; first five skills; first four language names.
- Full cover note if supplied.
- Open-request actions: Hire {first name}; View profile if slug; Open chat or Save & chat or Start chat when application state allows; More options for {first name} disclosure → Save for later; Not this caregiver.
- Filled/closed actions: View profile; Open chat if conversation. No hire action.
- Empty applications result: No caregivers have replied yet (also used when filters produce no visible rows).
- Reviewing request footer: more Recommended caregivers with suggestion cards and further-invitation copy.

### Recommendation cards

Source: `resources/views/livewire/family/partials/caregiver-suggestions.blade.php:1`.

Photo or initials; caregiver name; proximity; numeric match score; average stars and review count or no reviews; identity, background, top-caregiver badges if present; certification tags (compact, label omitted); care-experience tags; first two match reasons; Invite {first name}. Empty state distinguishes no suggestions from no certification matches.

### Certification filters and tags

Source: `resources/views/components/caregiver-certification-filter.blade.php:22`, `caregiver-certification-filter-chips.blade.php:14`, `caregiver-certification-tags.blade.php:13`.

- Disclosure “Certifications & training” with Any or first selection + count summary.
- Expanded: Required certifications & training, all catalog option labels with checkboxes, explanation that selecting several requires all. The catalog is data-backed and scrolls internally.
- With selections: Verification radio choices Any current credential (LoLo-verified and caregiver-reported) / LoLo verified only; Clear certifications.
- Active removable chips for each selected certification; optional LoLo verified only chip; individual remove actions.
- Profile tags show label + status label (verified/reported), with +N more overflow badge. No absent credential should be inferred from an omitted tag.

## Invitation drawer / confirmation

Source: `resources/views/livewire/family/partials/caregiver-invite-panel.blade.php:1`, `caregiver-invite-card.blade.php:14`; grouping/status values in `app/Services/Marketplace/CaregiverInvitationDiscoveryService.php:107`, `:297`.

Desktop: centered modal, maximum 920px wide and 90vh, inner scroll area. Mobile: full-height overlay with separate scroll. Close/Escape return to request; focus restoration is implemented.

Common: Caregiver invitation eyebrow; Invite a caregiver to this request title; Close; request title, When (schedule), Where (city/state), Care for (recipient); optional success/error/info feedback.

Search state:

- Search by caregiver name field, 2-letter/city guidance, Example: Charles placeholder, Clear search if text exists.
- Certification filter; loading spinner and Searching caregivers; screen-reader result count.
- Initially groups: Caregivers you hired before / people already providing family care; Saved caregivers / saved while browsing; Recommended for this request / schedule and location fit. Empty groups omitted.
- Empty initial fallback: Search for someone you know and name guidance, or no-certification-match explanation with Include credentials reported by caregivers / Clear certifications.
- One-letter search: Enter at least 2 letters. No results: No active caregivers found + spelling guidance, or search-and-certification mismatch copy and possible include-reported action.
- Successful query: Search results, caregiver count, result cards.

Each invitation card: photo/initials; name; city/state or Location not listed; availability statement (Matches the requested time / Schedule may not match / Schedule not provided for one-time); identity/background/top-caregiver badges; average stars + review count or No reviews yet; certification tags; Care experience heading and tags; relationship status and explanation; View profile; Invite {name}, Invite {name} again, View reply, or static state label.

Relationship statuses cover Available to invite, Selected caregiver, Already replied, Invitation sent/accepted/declined/expired/cancelled, Request closed, Not accepting new clients. Pending state shows relative sent time. Reinvite is allowed for historical invitation states only while request open and caregiver accepts clients.

Confirmation state: Back to caregiver results; Invite {first name} [again] to “request title”?; schedule + city/state + response explanation; caregiver certification tags; Invitation message (optional) multiline field, edit hint, maximum 1200 characters; Cancel; Send invitation; Sending invitation… disabled/busy state; field validation.

## Visit tab (`shift`): scheduled and during shift

Source: `manage-care-request.blade.php:1117`–`:1297` (shared conditional controls defined at `:130` and `:289`).

- Header: Visit essentials / Live details; Before the visit / Live visit details / Paused visit details; state-specific explanatory paragraph; booking status badge.
- Paused-only notice: Visit is paused; caregiver can resume/end on their screen.
- Latest time correction component if enabled and present (see separate inventory).
- Visit plan / Live check-in panel; caregiver first name scheduled to come, or caregiver with named recipient.
- Time card: date, scheduled start and end.
- Caregiver card: full name, Checked in actual time / Check-in pending.
- Payment card: normalized status / Needs setup; authorized amount if nonzero / Card authorization pending.
- Care location: exact address, Open map, recipient full name + relationship.
- Live sidebar: Checked in; caregiver first name + check-in date/time; GPS was captured at check-in if both coordinates present. Actual coordinates are not displayed.
- Scheduled-before-no-show-window: “If the caregiver is late” disclosure; precise no-show eligible date/time (30 minutes after scheduled start), message/support guidance.
- Scheduled-after-window: Caregiver has not arrived?; no-show-window explanation; Mark caregiver no-show; browser confirmation states this cancels the visit and affects reliability.
- “Map and visit record” disclosure: embedded map while non-final; Started (timestamp/pending); Completed (timestamp/pending); uppercase Payment status / NOT READY.
- Pending caregiver-change panel when present: heading, current-schedule-remains explanation; each change type, reason, proposed start/end if present; Accept change, Keep current schedule.

The live/paused branch does **not** render the generic Detailed visit record below. Care tasks and the event timeline are not visible in this branch. Source condition nesting matters; do not infer that a later non-scheduled check includes live states, because it is nested in the `else` of scheduled/live.

## After shift / timesheet / review / closed Visit tab

Source: `manage-care-request.blade.php:1298`, `:1403`, `:1454`, `:1493`, `:1602`.

Shared page header includes the approval amount/actions while hours need review. Visit content includes:

- Visit details / Visit record eyebrow; Visit details before approval / Final details / Cancelled visit details / No-show details / Support review details heading; corresponding explanation; status badge.
- Cancelled/disputed notice: Visit was cancelled, no-show explanation if flagged, or Visit is in dispute and support-review explanation.
- Latest time correction component if available.
- Schedule card: scheduled date and start/end; Caregiver card: name, actual check-in date/time or pending; Care location card: full address, Open map, recipient + relationship.
- Embedded map for non-final records; a normal confirmed final record omits it.
- Non-final/non-timesheet-review payment panel: no-payment message with Confirm card authorization + Billing & Payments; otherwise payment status, authorized amount, captured amount, last error and Use a different card when present.
- Non-final action strip: Open chat when applicable, Question hours/Get support/Safety-support appropriate to status. Stage conditions suppress unnecessary controls in normal final records.
- Normal unconfirmed timesheet: submitted time/payment approval decision is in the shared header; generic receipt is not repeated below.
- Confirmed final Visit receipt if a timesheet/worked value exists: Worked time; captured payment amount (fallback to care amount), payment status (fallback Finalized); Timesheet Confirmed; family-confirmed timestamp/fallback.
- Other non-final records with submitted/worked values: Worked time; Estimated visit total; hourly rate/processing note; timesheet Confirmed/Awaiting your confirmation; submitted timestamp.
- Reviews if final and available: Your review, five stars/numeric rating out of five, comment or no-comment fallback; Caregiver feedback with own five stars/numeric rating, comment/fallback. Either side can be absent.
- Detailed visit record disclosure: caregiver check-in; check-out; family confirmation timestamps or Pending; uppercase dispute state or NONE; expected minutes and worked minutes if either exists; Care tasks, each label with strikethrough if completed and task-check notes, or no checks; Visit timeline with up to 20 event timestamp/type rows and an internal scroll box; optional Change requests, each uppercase type/status, reason, proposed start/end, Accept/Reject for pending request by someone else.
- The template contains a scheduled Change requests disclosure inside the generic branch, but the outer scheduled/live branch prevents ordinary scheduled records from reaching it. Scheduled records do expose pending caregiver requests in their dedicated panel.
- Confirmed with no current-user family review: Leave a caregiver review card; “Tap stars to rate this visit”; Rating; five clickable stars with 1–5 accessible labels; selected rating n/5 or no-selection text; Review comment field; Submit review; rating/comment errors. Comment validation maximum is 1500 (`ManageCareRequest.php:694`).
- After successful review, form disappears and the displayed review/closed record takes its place. No separate standalone review URL or review tab exists.

## Time-correction review branch

Source: `resources/views/livewire/family/partials/time-correction-review.blade.php:1`. Rendered only when `marketplace.time_corrections.enabled` and latest correction exists. Anchor `#time-correction-review-{id}` scrolls to the panel.

- Time correction · Visit #; caregiver asked to review visit time or status label; visit calendar date; human-readable timezone; status pill.
- Scheduled start/end; App recorded check-in/end or absence labels; Requested proposed start/end; corrected duration after unpaid break minutes.
- What happened: reason label, full explanation; optional Your note from previous family response.
- Final amount if approved; amount, corrected duration, care amount + processing fee when present.
- Pending family: Approve duration and pay amount; Ask {caregiver} to change this textarea (max 2000), explanatory placeholder; Send change request; Get help from LoLo; validation.
- Approval confirmation: Confirm approval; Approve duration and pay amount?; changes/payment explanation; Go back; Confirm approval and payment; validation.
- Payment action required: Hours approved — payment confirmation needed; approval-saved guidance; Confirm payment only if global payment action is not already shown.
- Changes requested: caregiver can revise; new version will need review. Approved admin required / escalated: saved hours/evidence and LoLo payment-review explanation.
- Applied and processing states retain the comparison/status card, but do not show the pending approval controls.

Lifecycle helper calculates timesheet-review status from confirmation alone, whereas the Blade standard approval-strip condition additionally excludes active corrections. Capture both the lifecycle banner and correction content; wording may not fully align while a correction is active.

## Help tab (`support`)

Source: `manage-care-request.blade.php:1650`. Shared header and lifecycle actions remain visible.

- No booking (e.g. original date passed): Support heading, “Support tools appear after a caregiver is hired and a visit exists.”
- Scheduled: Before the visit; Change or cancel this visit; check-in restriction guidance. Request cancellation or reschedule card; Before check-in badge; Change type (Cancel visit / Reschedule visit); Reason; proposed start/end datetime fields for reschedule; Send request.
- Scheduled “Need to cancel now?” disclosure: direct cancellation/payment-release explanation; conditional late-cancellation-window sentence; Cancellation reason; Cancel visit; browser confirmation “Cancel this scheduled visit?”.
- Unconfirmed completed: Timesheet help; Question hours or payment; approval-before-help guidance; Question the submitted hours card; What looks wrong? field; Open dispute.
- Live/paused: Live help; Need help during this visit?; ordinary-message vs report guidance; During the visit card; Open chat if conversation.
- Confirmed completed: Get help with this completed visit, billing/safety/record copy.
- Cancelled: Cancelled visit help / Get help with this cancelled visit; cancellation/billing copy.
- Disputed: Support review / Support is reviewing this visit; existing-dispute explanation.
- All booking states: Support ticket disclosure → Subject; Category (General question, The hours look wrong, I am worried about safety, I need to cancel or reschedule, Payment question, Time correction); Describe issue; Create support ticket.
- All booking states: Report incident disclosure → Incident title; Severity (Low, Medium, High); Description; Submit incident.
- Completed/reviewed but no normal pending-timesheet approval: Open dispute disclosure → Dispute reason; Open dispute.

Changing the Change type uses `wire:model` rather than `wire:model.live`; verify whether proposed fields appear immediately in runtime before considering the reschedule capture complete.

## Optional care profile preceding every management tab

Source: `resources/views/components/care-recipient-profile-summary.blade.php:1`.

Conditional profile heading About {preferred name}; Updated badge; relationship context, age range, pronouns chips. Only populated fields/sections render. Possible sections and fields:

- At a glance: about; Interests and comforts; A good visit; Everyday health or memory context.
- Communication: preference chips/text and notes.
- Support and mobility: support areas; Support details with per-area label/value; Mobility; mobility notes.
- Routine and comfort: Usual routine; Food and drink; Personal-care preferences; Sleep and overnight; Comfort and reassurance; May feel uncomfortable when; What helps.
- Important for safety: safety items; notes.
- What the family is looking for: Helpful caregiver qualities; Please do; Please avoid.
- Contacts and care coordination: Full name; contact name + relationship; phone with telephone link; email with email link; escalation note. If contacts absent, full name may stand alone.
- Sharing footer: Shared by {preferred name}'s family; last-reviewed date if provided.

The exact grouping/value types depend on the presenter snapshot; arrays render as chips, support-detail arrays as description lists, text as full paragraphs. This component is not a disclosure. A populated profile can materially alter the first visible screen and page length on every tab.

## Connected pages that close the loop

### Book again

Source: `resources/views/livewire/family/book-again.blade.php:1`; route `routes/web.php:359`.

- Header: Request care with caregiver first name; one-visit/recurring headline; last visit date and copied-details explanation.
- One visit panel: selected caregiver first name; inbox/acceptance explanation; Starting day, Starting time, Duration; estimated total, care amount, processing fee, care/fee hourly rates; Message to caregiver; optional Use the latest care profile checkbox with recipient profile name and preserved-old-profile explanation.
- Copied from last visit: caregiver full name, recipient name, full location.
- Back to visit; Send one-time invite; validation/status messages.
- Recurring care panel: Set up a repeating schedule, caregiver name, days/time/start/end explanation; Set up recurring care link to `/family/requests/{id}/regular-care`.
- What happens next: 1 caregiver accepts/declines; 2 family hires; 3 check-in/timesheet/approval/payment flow explanation.

### Care story

Source: `resources/views/livewire/family/care-journey.blade.php:1`; route `routes/web.php:343`.

- Back to Care; care type, status badge, title, subtitle, reference.
- Next step / Needs your attention; consequence copy; primary action link.
- Family care navigation: Overview, Arrangements, Schedule, History (shared nav, not the four management tabs; `resources/views/components/family-care-nav.blade.php:4`).
- The complete story / How this care is progressing; sequential stage names, complete/current/future marker, detail, timestamp when available. One-time stage labels begin Care requested, Caregiver selected, Visit scheduled, followed by delivery and payment stages (`app/Livewire/Family/CareJourney.php:78`).
- Visits connected to this care: each date, start/end time, caregiver, booking status, payment status, Open visit; empty-state message if none.
- Care details: recipient, caregiver, schedule, Open full management.
- Care tasks chips if any.
- Mobile fixed bottom primary action.

## Capture completeness checklist

For every main lifecycle stage, capture every visible management tab, even when its content repeats, because the shared header/status/CTA and tab order differ. Additionally capture these branch states in both sizes:

1. Create with optional details/contact, simple profile, saved profile, self vs other, private review/publish, schedule completed and missing/invalid essentials.
2. Waiting public/private; suggestions populated/empty; active vs past invitations; exactly one reply vs multiple vs filterable; applicant Profile details + More options opened.
3. Invitation initial groups, populated search, empty/short query, certifications expanded/selected, invite confirmation, reinvite, sent feedback.
4. Scheduled late disclosure, map disclosure, no-show eligible, caregiver reschedule/cancel request pending, Help reschedule fields and immediate cancel disclosure.
5. Live and paused with map record opened. Preserve evidence that live task checks are not rendered.
6. Timesheet review, Detailed visit record expanded, Help Question hours, correction review/confirmation and non-pending correction statuses, payment attention.
7. Hours confirmed with empty review, selected stars/comment, reviewed with family + caregiver feedback; receipt and detailed record.
8. Cancelled, no-show, dispute, withdrawn, original-date-passed; all allowed tabs and relevant support/withdraw controls.
9. Shared Help Support ticket + Report incident + Open dispute disclosures (where available), shared care-profile-rich page, mobile navigation/legal disclosure, connected Book again and Care story.

Screenshots should be recorded as “captured”, “same rendered surface as [ID]”, “source-only / fixture missing”, or “external dependency”, rather than silently treating an uncovered branch as complete. Native payment authentication, map loading, catalog-backed labels, real-time relative timestamps, and state-dependent review availability need runtime verification.

The main audit family has an existing saved care profile. The create template only renders “Add a simple care profile” when the saved-profile collection is empty. A separate synthetic family, Nora Brooks, was therefore used for `OT-create-quick-profile` and `RC-create-quick-profile`; both branches now have desktop/mobile screenshots and information inventories. The profile fields remain unsaved drafts, opened through the actual UI.
