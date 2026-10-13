# Final visual review notes

Reviewed September 8, 2026 against screenshots of the local implemented application. These are representative human visual checks plus a separate automated browser contract; they do not certify every pixel of all 319 states or a complete accessibility audit.

## Representative final screens

| Screen | View checked | Observation |
|---|---|---|
| [One-time creation, ready to publish](screens/OT-create-ready.md) | Desktop viewport | Centered form/review columns, clear selected care type, recipient reuse and estimated care/processing amounts. Inter and Source Serif 4 render locally. Inline support replaces the floating bubble. |
| [Recurring creation, ready to publish](screens/RC-create-ready.md) | Mobile viewport | The same four section anchors fit as a two-column step list. Recurring care is clearly selected. The form stacks without clipping or horizontal overflow. |
| [Recurring submitted hours](screens/RC-timesheet_review-shift.md) | Mobile viewport | Exact visit reference, recipient, caregiver, dated context and parent-care return are readable. This visit / Care instructions / Help & changes remain distinct. The fixed hours-review action is not covered by the support launcher. |
| [Live one-time visit, expanded](screens/OT-in_progress-shift-expanded.md) | Desktop viewport | The current task leads, followed by safety, home access and contact essentials from the attached care information. The live record remains below. No clipped content or overlapping controls observed. |
| [Active recurring home](screens/RC-plan-active-overview.md) | Mobile viewport | Current visit now appears immediately after the stable four-tab navigation. Its reference, date, assigned caregiver and status are visible before the follow-up queue. |
| [Active recurring Visits](screens/RC-plan-active-visits.md) | Desktop viewport | The selected tab, recurring pattern, outstanding-action count and schedule-management scope are clear. Booked visits continue below these sections; the first viewport is not claimed to contain every date. |
| [Existing caregiver replies](screens/OT-applicants-applicants.md) | Refreshed desktop viewport; mobile viewport and full page | Desktop now shows the first caregiver's identity, reply, Hire and View profile in the first view. Mobile reaches the first card around the bottom edge (~818 px); details need a short scroll. Both cards and their profile/chat/hire/more-options controls remain, followed by invitation search/history and recommendations. No clipping or action overlap observed. |
| Scheduled recurring visit and pending recurring home | Desktop/mobile samples inspected by independent reviewers | Full identity and truthful visit/offer statuses remain readable; the parent tabs fit on mobile. These checks complement the final state validator rather than replacing it. |
| Countered recurring offer | Final desktop/mobile samples inspected independently | The proposed Tuesday/Thursday slots and date list match, and the page clearly distinguishes the proposal from booked visits. See [plan visual evidence](validation/plan-visual-review.md). |

## Findings fixed during review

1. The floating support launcher covered the mobile primary-action area. Redesigned workspaces now provide an inline Chat with support action that opens the existing dialog. The browser check verifies actual opening and an unobscured confirmation button.
2. Brand fonts could not reliably load from the external provider in the capture environment. Original Inter and Source Serif 4 font files are now served locally, with their licenses. Both loaded font faces are checked for every family capture.
3. Existing replies were below a large invitation panel and empty invitation history. Replies and filters now precede that area when applications exist; invitation tools still lead when there are no replies. A compact Invite someone anchor retains easy access.
4. The recurring home placed optional reviews before operational decisions, with a live visit further down. Current care now leads, and required follow-up comes before optional reviews, preserving date order within each group.
5. Switching from an open decision review to Help left the confirmation panel visible. Changing tabs now closes only the preview flags and retains draft fields.
6. Moving the full profile into Care details also moved important safety information away from the live/scheduled landing page. Compact attached-snapshot safety, home-access and contact essentials now remain on that landing page.

The final two display-order changes were made after the initial screenshot pass began. The affected applicant and parent-home captures are refreshed separately, with source/capture timestamps recorded in the delivery evidence. No business service or CSS build changes were introduced by those final reorderings.

## Automated interaction evidence

[workspace-ui-validation.json](workspace-ui-validation.json) records **120 passing checks** at widths 1440, 390 and 320. Checks cover representative-page rendering, horizontal overflow, primary-button normal/hover/focus contrast, visible keyboard focus, draft retention when closing/reopening approval, an unobscured final confirmation control, inline chat opening and loaded brand fonts.

For the measured primary action, cream text against evergreen returned approximately **9.54:1** normal contrast and **11.84:1** on hover. These are measured control samples, not an assertion that every color pair in the application has been audited.

[The full capture validator](validation.json) separately checks the completed matrix for file integrity, viewport dimensions, overflow, loaded fonts, current frontend build and linked inventories. [Screen semantics](screen-semantics.json) checks the named record/status/navigation against fixture data; an HTTP 200 alone is not considered proof of the intended lifecycle state.

## Remaining scope limits

The sample matrix does not establish real external payment authentication, delivered messages, map loading, device-location permissions or external AI responses. The caregiver/admin applications have local login smoke coverage, not a full redesign audit. Some dense care details intentionally remain in expanded sections or below the first viewport; their full-page images and inventories are the supporting evidence.

Future product testing can assess whether families prefer an even shorter mobile header or a more compact schedule-management section. These are optional usability refinements, not unresolved data-loss or broken-navigation findings from this review.
