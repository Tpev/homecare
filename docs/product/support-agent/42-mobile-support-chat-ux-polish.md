# Mobile Support-Chat UX Polish

Status: Deployed mobile foundation; August 19 desktop/bottom-anchor correction implemented and regression-tested locally, awaiting the normal deployment

Date: August 18, 2026; desktop parity correction August 19, 2026

Scope: Shared Family and Caregiver support-chat interface; no AI capability, pilot-access, KB, payment, care-request, or production-control change

## Outcome

The support chat behaves like a focused conversation on desktop and mobile. It uses the full available visual viewport on a phone, always opens at the latest message, keeps the composer stable above the mobile keyboard, and gives older users persistent access to human help and large touch targets.

The five-second background refresh no longer leaves a person unable to continue typing. The client records the current textarea selection and restores focus and cursor position only when a refresh caused focus to fall back to the document. A deliberate tap on another control is never overridden.

## Delivered behavior

| Area | Implemented behavior |
| --- | --- |
| Mobile opening | Full available-visual-viewport chat at 639px and below, including safe-area-aware header and composer |
| Latest message | Every desktop and mobile open or reopen scrolls to the newest message, including conversations that offer **Load earlier messages** |
| Background refresh | Preserves composer focus, draft, and live cursor selection while the five-second poll updates the conversation |
| Reading older history | Loading earlier messages preserves the currently viewed message; when new content arrives while the person intentionally reads upward, a 44px **New message** control appears |
| Keyboard | Enter sends; Shift+Enter inserts a line break; IME composition is not interrupted |
| Human help | **Human help** remains visible in the header throughout an automated conversation instead of scrolling away with history |
| Composer feedback | On click or Enter, text immediately leaves the composer and appears once as an optimistic **Sending…** bubble; failures retain that bubble with **Try again** |
| Mobile actions | Navigation, guided-help, and request-receipt actions expand to an easy-to-tap full width |
| Conversation density | Message attribution uses short names and actual times; the Support Center/emergency footer is one compact row |
| Navigation | Chat action links remain mounted until browser/Livewire navigation consumes the click, preventing intermittent no-op actions |
| Existing resilience | Draft persistence, offline retry, browser-back dismissal, rotation handling, large text, scroll lock, and 44px targets remain intact |

## Interaction rules

1. Opening the launcher on desktop or mobile must show the newest available message without a second swipe, regardless of whether older history can be loaded.
2. Sending a message always returns the conversation to the bottom.
3. A user who intentionally scrolls upward stays there during refresh; the interface announces and exposes the new-message shortcut.
4. A background refresh may update history but may not steal typing focus or reset the cursor.
5. A deliberate focus change made while a refresh is in flight is respected.
6. Mobile action targets remain at least 44px high and text inputs remain at least 16px.
7. The mobile browser Back action dismisses chat before leaving the current page when the chat created the history entry.
8. Clicking a chat navigation or guided-action link records the chat as minimized for the destination but does not synchronously hide the clicked link.
9. A normal Enter submits; Shift+Enter inserts a newline; IME composition remains uninterrupted.
10. Submission clears the visible composer synchronously and retains the exact message in the pending bubble until the server confirms or exposes retry.

## Implementation map

- `resources/js/support-chat.js`: desktop/mobile bottom anchoring, earlier-history anchor preservation, immediate optimistic send, Visual Viewport sizing, focus/selection preservation, Enter behavior, new-message detection, navigation sequencing
- `resources/views/livewire/support/chat-widget.blade.php`: persistent human-help control, message metadata, new-message shortcut, pending-send feedback, compact footer, mobile-friendly actions
- `resources/css/app.css`: full-height phone layout, safe areas, new-message control, full-width mobile actions
- `tests/e2e/specs/support-chat-responsive.spec.ts`: desktop long-history opening, phone matrix, latest-message reopen, focus/cursor regression, Enter/Shift+Enter, rotation, offline retry, and large text
- `tests/e2e/specs/support-chat-flow.spec.ts`: delayed-server optimistic bubble and synchronous composer-clear regression
- `tests/e2e/specs/ai-support-interactive.spec.ts`: automated-conversation header control and interactive request regression

## Verification completed

| Check | Result |
| --- | --- |
| Production frontend build | Pass; Vite transformed 110 modules |
| Support chat feature tests | 15 passed, 99 assertions |
| Desktop and responsive Chromium suites | 9 scenarios passed in 2.7 minutes; includes desktop long history plus 320, 360, 375, 390, and 430px phone widths |
| Continuous typing across background refresh | Pass; focus and cursor remained at the expected position |
| Long-history minimize/reopen | Pass; mobile returned to the newest message |
| Enter send and Shift+Enter newline | Pass |
| Composer clears before delayed server response | Pass; message remains visible once in the optimistic sending bubble |
| AI recap/confirmation/action navigation | Pass |
| Existing user/admin human support flow | 3 scenarios passed |
| Diff whitespace validation | Pass |

The first AI-specific browser run exposed an intermittent link-navigation race after a chat action hid its own panel. The navigation sequencing was corrected and the exact scenario passed on rerun. This correction applies to Support Center, semantic navigation, guided-help, and request-receipt links.

## Deployment and production check

Deploy with the normal `deploy.sh` workflow. This batch has no migration, environment-variable change, provider change, seed, or data mutation.

After deployment, use one of the two existing Family pilot accounts on a real phone or a narrow browser viewport and confirm:

1. Chat fills the available screen and opens on the latest message.
2. Typing continuously for more than five seconds never loses focus.
3. Shift+Enter creates a line break and Enter sends.
4. Minimize, reopen, and browser Back preserve the draft and behave naturally.
5. **Human help** is visible for an AI conversation.
6. A navigation action opens its destination and the destination starts with chat minimized.

The pilot remains limited to its current users until an administrator deliberately changes the existing Pilot/Everyone availability control.
