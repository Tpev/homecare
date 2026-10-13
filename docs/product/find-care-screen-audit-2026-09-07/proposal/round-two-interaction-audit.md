# Round two: interaction and contrast audit

Reviewed the initial `find-care-proposal.html` concept on September 7, 2026. These are findings about the illustrative concept, not new claims about the production application. This review inspected the CSS cascade, markup, event handling and sample state logic. No application or concept file was changed by this audit.

## Fix first

| Priority | Concrete mechanism | Recommended correction |
|---|---|---|
| P1 | `#lolo-care-proposal button:hover` has specificity `(1,1,1)`, exceeding `.lolo-primary` at `(1,1,0)`. Hover replaces the primary button's dark background with `--lolo-soft`, while its near-white foreground remains. `.lolo-primary:hover` only supplies a brightness filter, so it does not restore the background. | Define background, foreground and border for each button variant's enabled hover/active/focus state. Scope the shared hover treatment to secondary buttons, or remove that shared background rule. Check the same variants in light and dark modes. |
| P1 | `render()` assigns `part('content').innerHTML` each time the view/filter/layout changes. Review and message textareas are recreated empty; no input handler writes their values into `state`. Rating alone is stored. | Keep review/message/issue drafts in state, keyed to the relevant example visit/caregiver. Bind input events and restore values after rendering. Preserve entered text when navigating Back or changing the comparison layout. |
| P1 | `help()` always shows “Review the recorded times before payment,” Question hours, and Sofia as the caregiver, regardless of hiring/scheduled/live/closed state. The live template has no End visit action, and no actual safety incident/general support entry is demonstrated. | Render eligible issue choices from the selected stage. Before hire, do not imply a chosen caregiver or submitted timesheet. Keep End visit as a deliberate secondary live action. During care, make safety help easy to locate. Preserve distinct hours/dispute, change/cancel, support and incident routes in the prototype. |
| P2 | `#lolo-care-proposal .lolo-nav button` is later than the generic hover rule at the same specificity and forces `background:transparent`. Nav hover therefore produces no surface feedback. Selected state only changes a narrow underline and font weight. | Give tabs an explicit unselected hover treatment and a stronger selected state. Retain the active marker during hover; hover must not look identical to selection. Keep all four tabs in the same order. |
| P2 | `data-preview` shows one status notice after the entire content area, without bringing it into view or opening the named review. A long page can make a click appear unresponsive. “Review hire” and final approval currently do not let the reviewer inspect a decision surface. | Open an actual local preview panel/dialog for the representative hire/approval/change decisions, with visible close/back controls and explicit simulation labels. Put local validation/feedback next to its trigger. A prototype must not imply that real messages/payments were submitted. |
| P2 | No deliberate `:focus-visible` or `:active` treatment exists. The prototype relies on browser defaults for keyboard focus. Generic hover also applies to disabled buttons; opacity is the only disabled treatment, although no real disabled form state is demonstrated. | Define a visible focus ring with offset, pressed state, disabled colors and `:not(:disabled)` hover gating. Preserve focus through rerenders and return it to the launching control after closing a panel. Show a disabled/validation example for rating-required submission. |
| P2 | Tab buttons use `aria-pressed` as a view selector without an associated tab panel. Dynamic rerenders can remove the focused filter/control. The rating selector has five independent toggle buttons even though exactly one rating is allowed. | Implement one consistent semantic pattern: a proper tablist/tab/tabpanel with arrow-key navigation and selected state, or clearly labeled navigation with current state. Use a single-select radio pattern for rating. Restore focus after filtering and explain result counts without moving focus unnecessarily. |
| P2 | Credential, hiring-history, task-evidence and help sections contain promises that the data will remain available, instead of representative actual values and controls. The care brief omits the baseline phone/email links and complete ZIP. The recurring breadcrumb is plain text and cannot open the parent plan. | Replace the most important placeholders with actual synthetic baseline data so density and discovery can be evaluated. Make the recurring-parent destination and contacts interactive within the concept. Retain full data inventories as the implementation acceptance checklist; do not treat the sparse concept as preservation proof. |

## Contrast facts from the defined colors

Ratios below are computed from the concept's hex tokens; they are not a full rendered accessibility audit. Hover figures are before the whole-button brightness filter, which does not resolve the near-identical foreground/background colors.

| Pair | Approximate ratio | Implication |
|---|---:|---|
| Light primary text `#fffdfa` on normal action `#214d3e` | 9.42:1 | The normal primary palette is strong; keep that character. |
| Light primary text on generic hover `#edf2e9` | 1.12:1 | Primary text nearly disappears on hover. |
| Dark primary text `#193b2c` on normal action `#b9d7bf` | 7.95:1 | Normal dark-mode primary also has strong separation. |
| Dark primary text on generic hover `#303e32` | 1.09:1 | Dark-mode primary also loses contrast on hover. |
| Light muted text `#586a61` on paper `#fffdfa` | 5.67:1 | General muted text is not the main problem. |
| Default border `#deded3` against paper `#fffdfa` | 1.33:1 | Same-background fields/buttons can have faint boundaries. Use stronger interactive borders than decorative dividers. |
| Dark muted text `#acbdb2` on paper `#262d29` | 7.17:1 | Preserve rather than unnecessarily brighten every secondary label. |

## Interaction rules for the next concept

Primary actions should remain deep branded green with a readable foreground in every state. Secondary controls should have a perceptible boundary and hover fill; text actions should gain a clear underline/color treatment. Do not apply a single background hover to all buttons. Selected tabs, selected rating and selected candidate filter must remain distinguishable from mere mouse hover. A keyboard user should receive at least as much feedback as a mouse user.

Use a consistent focus treatment for buttons, links, fields and disclosures. Native `summary` currently has a 36 px minimum height, less than other controls' 44 px target; increase its comfortable clickable area without making records excessively tall. For rating, demonstrate an unselected state, selected value, validation message and disabled/busy preview. For text input, demonstrate draft retention and a field error instead of relying on placeholders alone.

The global navigation and recurring breadcrumb currently look navigational but are noninteractive spans/text. Either give those destinations meaningful local examples or mark them as outside the current preview. Make the actual journey-stage and care-type controls visible in the artifact even if the external `Tweak` runtime is unavailable; otherwise it silently opens only the default recurring hours view.

## Focused acceptance pass

1. Hover, keyboard-focus, press and disable each primary/secondary/link/tab/filter/rating control in both themes; inspect the rendered colors after the full cascade, not just the token definitions.
2. Type a review, choose stars, switch tabs and return. Repeat for a message and any issue/change draft. Change desktop/stacked comparison without losing the draft.
3. Open each significant CTA. It must reveal a specific decision/preview or immediate visible feedback, preserve context and give a working Back/Close action.
4. Walk hiring, scheduled, live, submitted hours, paid-review and closed states. Help must be appropriate; no future/unperformed action should be described as done. Live care retains deliberate completion and safety access.
5. Check every disclosure and input at 390 px, narrower width and larger text, including keyboard open. Ensure no horizontal clipping, inadequate action width, covered feedback or lost focus.
6. Verify selected tab/filter/rating semantics with keyboard and assistive technology. Screen-reader status announcements supplement visible feedback rather than replacing it.
7. Demonstrate the complete synthetic contact, credentials and hour/payment comparison values in at least one expanded state; confirm the preservation map still has a destination for every omitted field or conditional action.
