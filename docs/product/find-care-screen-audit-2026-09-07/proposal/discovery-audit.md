# Discovery and hiring audit — proposal only

Reviewed 7 September 2026. Scope: Create, Waiting/inviting, reviewing applicants, and the decision to hire, for one-time and recurring care. No application code or database records changed. This is a heuristic review of existing capture evidence and current source, not a usability study or conversion measurement. Upwork references are handled in the main proposal; none are claimed here.

The main problem is the order and repetition of information. The product already has useful care details, meaningful profile information, invitation history, per-weekday schedules, and contextual messaging. Those should survive. Put the immediate decision first and make the full care brief and history consistently reachable.

The proposed stable request workspace uses **Overview · Caregivers · Care details · Help**, in the same order before and after hiring. It may initially select Caregivers while recruiting and Overview once hired; Caregivers then retains the chosen person and collapsed recruitment history. A recurring parent plan uses **Overview · Visits · Care details · Help**. Applicants, Invited, Find caregivers and Saved filters below are local views inside Caregivers, not more primary tabs. Care story/history remains reachable without a separate Activity tab.

## Evidence reviewed

Visually inspected desktop and mobile images for `OT-create-ready`, `RC-create-ready`, `OT-waiting-applicants`, `OT-many_applicants-applicants`, `OT-single_applicant-applicants`, `OT-invite-drawer`, `RC-invite-confirmation`, and `OT-existing-chat`; additional images for saved/quick profiles, private recurring waiting, and expanded recurring applicants. Full-page images were checked as well as focused images, because focused images sometimes hide content above the request heading.

Read raw rendered text and control inventories for those states and the self/private, expanded form, invitation history, and Care details branches; cross-checked the one-time and recurring source inventories. Source checks include `resources/views/livewire/family/manage-care-request.blade.php:319–354,522–569,738,894–920,1052–1077` and `app/Livewire/Family/ManageCareRequest.php:258–413`.

| Capture | Desktop document height | Mobile document height |
|---|---:|---:|
| OT-create-empty | 2,963 px | 4,404 px |
| OT-create-ready | 3,027 px | 4,587 px |
| RC-create-ready | 3,643 px | 5,679 px |
| OT-waiting-applicants | 3,030 px | 4,397 px |
| OT-many_applicants-applicants | 4,681 px | 6,468 px |
| OT-single_applicant-applicants | 2,459 px | 3,408 px |

These are whole-document dimensions from evidence JSON at 1440×1000 / 390×844, including shell and footer; they are not task completion times or precise distances to a button. The available first-screenful and focused images demonstrate hierarchy issues directly.

## Ranked findings and proposed behavior

### DISC-01 — P1: The full care profile precedes the task itself

**Observed:** The full desktop `OT-many_applicants-applicants` capture begins with About Ellie, age range, pronouns, communication, mobility, routine, safety, caregiver qualities, and contacts. The request heading and applicant list follow. The default source renders the recipient profile summary before the request header whenever a snapshot exists (`manage-care-request.blade.php:352–354`). This is not merely an expanded Care details tab. Focused gallery images can conceal it.

**Likely impact:** The family repeatedly scrolls through information they supplied before reaching the current job. This is especially costly on mobile and gives the impression that tabs do not control the whole page.

**Proposal:** Start with recipient + one-time date or recurring schedule, current status, and next action. Put the complete profile into a persistent **Care details** destination, with a compact **Care brief** shortcut near the header. On desktop it can open a side panel; on mobile it should be a full page with return to the same tab/scroll position. Surface existing relevant safety information at the visit/preparation stage rather than making the family reread all profile sections during hiring.

**Retain:** All profile text, relationship, age range, pronouns, preference and support tags, mobility, routines, food/drink, safety notes, caregiver qualities, contacts and coordination, source/last-reviewed information. Preserve access permissions and disclosure rules. Do not merge distinct fields into an unstructured summary or discard the original.

### DISC-02 — P1: Hire is a commitment without a final terms view

**Observed:** Single and multiple applicant screens feature bright **Hire Sofia** buttons. The card does not show the complete price or schedule immediately beside that decision. Source buttons call `hire(...)` directly; the handler changes application status and creates a visit, or calls recurring activation. This is source evidence; no hire was executed for this audit.

**Likely impact:** Families may interpret Hire as the start of a review step and discover that it commits them, particularly when recurring care implies many visits. Confidence also suffers when the care rate and processing fee were last visible on the creation form or a different tab.

**Proposal:** First action **Review hire** opens a focused confirmation screen with caregiver, care recipient, date/start/end/duration, exact address available under the existing rules, care tasks, rate, processing fee, estimate, card/payment requirement and relevant cancellation-policy link. Final CTA names the outcome: **Confirm one-time care** or **Confirm recurring care**. For recurring care show each weekday/time, start and end choice, estimated weekly cost, first confirmed visit and per-visit payment explanation. Preserve existing server eligibility/payment handling and distinguish a selected caregiver from successfully ready visits.

**Desktop/mobile:** Desktop can use a wide modal or dedicated review page; mobile needs a dedicated page and bottom confirmation action with the final cost visible. Do not squeeze all terms into an alert dialog or silently accept the first schedule shown in chat.

**Retain:** Existing hire behavior, eligibility gates, appointment/plan identity, rate/fee basis, payment outcomes, conversations, and histories. The new screen is a review surface, not a change to business rules.

### DISC-03 — P1: Private waiting copy implies passive discovery will work

**Observed:** `OT-private_waiting-applicants` and `RC-private_waiting-applicants` show PRIVATE, zero active invitations, and the same “Your request is live” / “Caregivers can reply now” / invite “if you want to move faster” guidance used for public requests. Creation correctly explains that private requests are invite-only.

**Likely impact:** A family can wait for applicants who cannot discover the private request. The badge alone does not explain the required action.

**Proposal:** Explicit state-specific headline: **Private request created — invite a caregiver to get replies.** Show **Invite caregivers** as the next action until at least one invitation is sent. After sending, show **Waiting for 2 invited caregivers** with visible invitation statuses and sent times. Public requests can say **Accepting applications** and offer optional invitations. Do not promise an unverified response time.

**Retain:** Public/private setting, current private-creation invitation entry, eligible discovery rules, active and past invitations, suggestion/search tools, expiry/reinvite behavior and privacy restrictions.

### DISC-04 — P1: Filtering to one applicant removes the controls needed to change the filter

**Observed:** `OT-single_applicant-applicants` replaces the normal list with a featured card. It includes name, Saved twice, experience, star average, cover note, Hire, Chat first, View profile and invite. Unlike the normal list, it does not expose in-page reliability, review count, credential details, detailed profile disclosure, Save for later, or Not this caregiver. The source hides the whole application-list region for exactly one **filtered visible** application (`:319–324`), and filter controls live inside that region (`:738,894–920`).

**Reproduced interaction:** The primary audit walkthrough opened `OT-many_applicants-applicants` at 390×844, expanded the filters, and selected Status=Saved. The result became the featured Sofia card; the Status control and Filter or sort caregivers disclosure both disappeared (`statusControlCount=0`, `filterSummaryCount=0`). The user cannot change or clear the active filter from those controls. Evidence: [live walkthrough](live-walkthrough.json), result `OT-many_applicants-filter-saved`, and [filtered mobile screenshot](filter-single-result-mobile.png). Only local UI filtering/navigation was performed against the existing isolated audit app; no care/business records were mutated. The original baseline screenshots remain the visual-style reference because external asset restrictions affected the later walkthrough.

**Proposal:** Keep one candidate-card model whether there are one, two, or many applicants. For one, use the extra space for a fuller card, without removing decision controls or filters. Display **1 of 4 caregivers • Saved** when filtered, with persistent **Clear filters** outside the results area. With zero matches, say **No caregivers match these filters** and retain the applicant total; distinguish this from zero applications. Use **Applicants**, **Saved**, and **Invited** as clearly labeled filters or subviews, with counts.

**Desktop/mobile:** Desktop can add optional side-by-side comparison of 2–3 selected candidates. Mobile should use the same comparison criteria in stacked rows or a dedicated comparison view. Comparison is a proposed feature, not an existing one.

**Retain:** All application states and dates, invitation distinction, filter/sort values, credential verification filters, personal details, reliability, reviews, notes, links, and Save/decline/chat/hire controls. A candidate hidden by a filter remains a record and can be restored.

### DISC-05 — P2: Ready applicants sit below invitation instructions and empty invitation history

**Observed:** In `OT-many_applicants-applicants` and `RC-applicants-applicants-expanded`, the page says caregivers are ready, says to review cards, shows Invite someone else, tabs, another Review replies heading/helper, an Invite someone you know panel, then People you invited with an empty state, before the candidates. The focused 390×844 screenshot contains no candidate, and even the focused desktop screen only reaches the list heading. Waiting similarly repeats invitation guidance and an empty invitation panel before recommendations.

**Likely impact:** The hierarchy competes with the stated next step. A family can reasonably assume they have to find or invite more people even when applicants are available.

**Proposal:** Inside Caregivers, use a compact toolbar: **Applicants (4) | Invited (2) | Find caregivers**. Open Applicants when replies exist, Find caregivers when public/no replies, and Invited after a private invitation is sent. Keep the primary request tabs stable; these are local candidate views. Display one short status line and the first candidate immediately below the toolbar. Hide an empty invitation panel behind its zero-count view rather than inserting it above live applicants.

**Desktop/mobile:** Desktop list rows should expose comparable essentials together. Mobile uses compact stacked cards, with name, review count, relevant verified/reported credentials, application note preview, and actions; the floating Support control and sticky action must have reserved space and must not cover card text/actions. Current mobile captures show Support overlapping text and the chat Send area, so verify this in the prototype with the keyboard open.

**Retain:** Every invitation and application status/history; recommendations and name search remain one action away. Do not combine an invitation accepted status with an application or imply that either means hired.

### DISC-06 — P2: The creation form spends its first screen explaining the form

**Observed:** `OT-create-ready-mobile-focus` shows the hero, Before publishing ready chips, saved-data disclosure, section introduction and the first care-pattern card; recipient and schedule are below the fold. The filled recurring form is 5,679 px high on mobile. Four readiness summaries at the top repeat a four-card review near the bottom. Saved details, last request and saved profile are separate concepts without a concise explanation of which fields each replaces.

**Proposal:** Prototype four sections: **Person & help → Schedule → Location & notes → Review**. Show section names and completion status, keep values when going back or switching care pattern, and let returning families jump from a concise saved-person card into schedule/review. Compare this with a compact single-page progressive form in usability testing; a wizard is a hypothesis, not a guaranteed improvement. Remove repeated instructional paragraphs, not fields. Provide one live price summary once schedule is valid; make it expandable with fee details.

**Branch requirements:** Me uses the poster's identity; family uses name/relationship. Saved profile shows a compact selected card, last-reviewed date and Change. Quick profile retains both questions, Skip and sharing acknowledgment. Last request/saved household actions should say what is copied and show a reviewable changed-fields summary; they must not silently replace newer entered details. The existing expanded optional notes/contact branch stays available and opens with a nonempty summary when populated.

**Mobile/desktop:** Desktop may use an editable progress rail and cost summary; mobile needs one primary Continue/Review action below the active section. Invalid sections should show field errors, retain values and lead directly to the field. Do not put Publish on a sticky bar throughout data entry as if incomplete form submission were the next step.

**Retain:** All fields, conditional options, loaded-draft messages and source data; the UI may collapse or step through them but must not reset them on navigation.

### DISC-07 — P2: Recurring schedule editing is good; summaries need to be complete

**Observed:** `RC-create-ready` correctly supports different per-day start/duration values and calculated end times. In the filled fixture, End on a date is selected with Last day set, but readiness and final Schedule summaries show weekdays/times and start date without the end date. The schedule information is repeated in multiple prose strings. One-time duration exposes internal-style “02:00 (2 hours)” formatting.

**Proposal:** Retain day-specific editing. Add optional **Use the same time on selected days** and **Copy Monday to…**, without overwriting day-specific changes silently. Use a readable mini schedule: **Mon 10am–12pm; Wed 1–4pm · 5h/week · Sep 14–Nov 6**. For ongoing show **Repeats until you end it**. Every review, invitation, and hire confirmation must carry the same complete schedule meaning. One-time use **2 hours**, with **10am–12pm** beside the date. Include the relevant time zone where date/time interpretation could otherwise be ambiguous.

**Retain:** Selected weekdays, every distinct start/duration/end, adjusted-start feedback, begin date, ongoing/end choice and last date, weekly hour/fee estimate and per-visit billing explanation. Avoid inventing a single recurring end time when weekdays differ.

### DISC-08 — P2: Inviting and messaging preserve context but devote too much space to it

**Observed:** `OT-invite-drawer` already has request context, useful previously-hired and recommended groups, name/city search, certification filters, and no need to reselect the request. This is a strength. On mobile the request summary plus guidance pushes the first candidate's actions below the first screen. `RC-invite-confirmation-mobile-focus` repeats the request context inside a second bordered card and places Send below the shown screen. `OT-existing-chat` explicitly tells the user to leave chat and open the request to hire; the banner occupies substantial mobile space. Desktop inbox exposes two Sofia conversations distinguished by request title; mobile truncates request title.

**Proposal:** Use a one-line persistent request context with **Details** expansion inside invitation search, then familiar/recommended/search-result rows. Confirmation title **Invite Sofia** plus one complete schedule summary, editable message, and fixed bottom **Send invitation**. Keep Back to results restoring query/filters/scroll. In chat use a compact caregiver + request header with visible request type/date and **Review hire** leading to the same hire-review screen with caregiver preselected. Preserve inbox distinctions between conversations for the same caregiver across requests.

**Retain:** All search states, two-character guidance when needed, city support, result counts, credential filter logic, statuses/reasons and unavailable controls, View profile, already-replied links, reinvite behavior, message draft/validation/character limit, explicit send, Close/Escape/focus return and in-context conversation history. Do not auto-send invitations or messages.

### DISC-09 — P3: Explain fit rather than foreground an opaque score

**Observed:** Recommendation cards show values such as “Match score 124” with useful concrete reasons (same ZIP, requested time). The numeric scale and meaning are not explained on these screens. Applicant cards and invitation cards also use different visual treatments and amounts of credential detail.

**Proposal:** Prioritize **Matches your requested times**, service area, relevant experience and credential status. Put the exact existing score into a secondary **Why suggested** detail if it must remain user-accessible; do not delete it from the data. Keep verified and caregiver-reported credentials distinct. Standardize the same factual card vocabulary across search, invitation and applications. Use one strong action color and a restrained hierarchy of secondary buttons; verify contrast/focus behavior rather than inferring WCAG conformance from a screenshot.

## Preservation map for the proposed screens

The existing screen inventories remain the field-by-field authority. This map states where each current group goes; hidden is not deleted, and every nonempty hidden group needs a clear label/count/summary.

| Existing information or action | Proposed home | Preservation rule |
|---|---|---|
| Person, self/family relationship, selected profile, last-reviewed/source | Person & help; Care details | Keep self identity and profile selection separate; preserve full profile snapshot |
| Quick profile's two answers and sharing acknowledgment | Optional About this person in Person & help | Retain exact answers, acknowledgment, Skip and permissions |
| Service selections and task notes | Person & help; full Care details/task list | Keep every selected task and its individual note, including empty-state meaning |
| Request scope, care notes, access, timing note | Location & notes; Care details | Preserve separate fields and clear populated-section summaries |
| Third-party contact name, relationship, phone, email, coordination | Location & notes; Care details | Keep relationship even where the current Care details card omits it |
| Exact address and unit/gate, city/state/ZIP, map/full-map link | Location & notes; Care details; hire review | Preserve existing visibility boundaries and location actions |
| One-time date/start/duration; recurring per-day times/start/end choice | Schedule; compact header; hire review | All values persist; summaries must represent differing weekday times and end date |
| Care rate, fee rate, amounts, total, per-visit payment explanation | Review; hire review; expandable header cost | Preserve numeric basis and estimate/final distinctions |
| Public/private choice, publish/create private, cancel | Review | Exact effect remains visible at commitment; invite-only follow-up is explicit |
| Four readiness states, validation and loaded-draft notices | Section progress + inline errors/status | Preserve errors and loaded-data provenance; avoid duplicate full summaries |
| Applicant status/count, filter/sort, credential filters, filter results | Caregivers / Applicants toolbar | Controls remain reachable for zero, one and many filtered results |
| Photo/name/location, experience/bio/skills/languages, rating/count, reliability, credentials | Consistent candidate summary + details | A single reply must not lose facts/actions available for multiple replies |
| Cover note, full profile, save/decline/chat/hire | Candidate row/detail and common hire review | Preserve every action and existing gating; chat/send stays explicit |
| Active/past invitations, sent/replied/updated times, status | Caregivers / Invited | Separate invitations from applications; history never disappears after hiring |
| Suggestions, score/reasons, search by name/city, prior hired/saved groups | Caregivers / Find caregivers | Preserve ranking data in details; no invented availability guarantee |
| Invitation preview, editable draft, limit, Send/Cancel/Back | Invitation confirmation | Preserve query/filters/draft when navigating back; never auto-send |
| Inbox search, request/caregiver identities, timestamps, messages, Send | Existing Messages + contextual request shortcut | Keep separate conversations for each request and return context |
| Withdrawal and consequence confirmation | Care details / Request options | Remain accessible before hiring; preserve pending-invitation consequences |
| Care story, global navigation, legal links, support | Header history/menu and global shell | Remain reachable without dominating each task's primary content |

## Suggested validation before implementing

1. Ask a returning family to request the same person/help on a new one-time date and on a two-day recurring schedule with unequal times. Check copied data, ability to edit and end-date comprehension.
2. Give a family a private request with no invites. They should identify the required next action immediately.
3. With four applicants, filter to one and zero results, clear filters, inspect credentials, save/decline/chat and return to the same context. The single-applicant state must retain all controls.
4. Ask what **Review hire** will do, then what **Confirm recurring care** commits to. They should correctly describe schedule, weekly estimate and payment timing before confirming.
5. At 390px width with the software keyboard open, send no actual messages but inspect invitation and conversation draft entry, bottom actions, focus order and Support overlap. Check usable controls at larger text sizes.

These are proposed checks and hypotheses; no success rates or production customer impact have been measured in this audit.
