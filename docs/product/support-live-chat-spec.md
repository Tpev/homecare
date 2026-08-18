# LoLo In-App Support Chat Product and Engineering Specification

Status: Released; mobile interaction polish updated August 18, 2026

Date: August 12, 2026; updated August 18, 2026

Audience: Product, design, engineering, operations, support

Primary users: Signed-in families and caregivers who need help; LoLo administrators responding to them

## 1. Product decision

LoLo will add a small support-chat launcher to the bottom-right corner of every signed-in, non-admin page.

The launcher opens a persistent conversation panel where a user can ask a question without leaving the page they are using. LoLo administrators receive an unread notification, can see exactly which signed-in user is asking for help, can claim the conversation, and can reply from the existing admin support area.

This is a new chat-shaped experience over LoLo's existing support-ticket system. It is not a second messaging system and it does not replace the full Support Center.

The first release will use short polling while the chat is open. The current application already refreshes support conversations every five seconds and the admin navigation every ten seconds, so the initial release does not require WebSockets or a third-party support vendor.

The experience must be truthful about availability:

- Show **Online now** only when an assigned support person is actively viewing the conversation.
- Otherwise show **Leave us a message** and a realistic response expectation.
- Never imply 24/7 coverage unless LoLo is actually staffed 24/7.

## 2. Why this is the right shape for LoLo

The current product already has:

- Authenticated support tickets connected to a user, family account, care request, or booking
- Public support messages and private admin notes
- Admin assignment, status, priority, and resolution controls
- Unread tracking for users and administrators
- Admin in-app notifications and operations email for new support requests
- In-app and email notifications when an administrator replies
- Five-second conversation refresh and ten-second admin notification refresh

The missing layer is immediacy and convenience. Today, support is a destination and a form. The new widget makes it a conversation that is available in context from anywhere in the signed-in product.

Reusing the existing support records preserves one history, one permissions model, one admin queue, and one operational process.

## 3. Goals

1. A signed-in user can ask for help from their current page in under 20 seconds.
2. The user never has to re-enter their name, email address, or account role.
3. An administrator can immediately identify the user and understand where in the product they asked for help.
4. New user messages become visibly unread for the support team within ten seconds.
5. An open conversation updates on both sides within approximately five seconds.
6. The user can minimize the chat, move through the product, and reopen the same conversation.
7. The user gets an honest sense of whether support is actively present.
8. Existing Support Center conversations remain accessible and compatible.
9. The complete chat flow works comfortably with one hand on common iPhone and Android screen sizes.

## 4. Non-goals for the first release

- Chat for signed-out marketing-site visitors
- A bot, AI answer generation, or automated troubleshooting
- Attachments, screenshots, voice notes, or video calls
- Group chat with multiple support agents visibly participating at once
- User-to-caregiver messaging inside the support widget
- Guaranteed 24/7 live support
- Browser push notifications when the app is closed
- SMS, WhatsApp, Slack, or external help-desk integrations
- WebSocket infrastructure
- Full support analytics, service-level agreements, or workforce scheduling
- Replacing the Support Center's structured dispute, incident, billing, or time-correction forms

The Support Center remains the better path for issues that require a category, priority, booking reference, detailed evidence, or an operational correction.

Future knowledge-grounded answers, semantic navigation, guided drafts, confirmed actions, evaluation, and human-handoff controls are specified separately in the [LoLo Intelligent Support Agent documentation](support-agent/README.md). That program does not change the Phase 1 human-chat non-goals or authorize AI behavior in this release.

## 5. Surfaces

The feature has two connected surfaces.

### 5.1 User chat widget

Available to signed-in family and caregiver users on application pages. It is not shown to administrators, sales users, SDR users, signed-out users, or on print-oriented pages.

The widget has three states:

1. **Closed launcher**: a bottom-right circular or rounded-square button labeled for screen readers as **Chat with LoLo Support**.
2. **Open panel**: a compact conversation window on desktop.
3. **Mobile chat**: a full available-visual-viewport conversation on small screens so the keyboard, newest message, and composer remain usable.

The closed launcher shows an unread badge when support has replied. It should not obscure primary page actions, mobile navigation, cookie controls, or booking controls.

### 5.2 Admin support inbox

The existing Admin Support queue remains the canonical inbox. The chat release should make it more conversation-oriented:

- Unread chats rise to the top.
- The row shows the user's name, role, latest message, elapsed time, assignment, and source **Chat**.
- Opening a chat marks it read for that administrator view.
- **Claim conversation** assigns the current administrator and changes the ticket to **In progress**.
- The conversation header shows user context and current presence.
- Public reply and internal note remain separate actions.
- Resolve and close continue to use the existing ticket lifecycle.

A later design can turn the queue into a three-pane inbox. That redesign is not required to ship the first widget.

## 6. User experience

### 6.1 Launcher

Default desktop placement:

- Fixed to the bottom-right corner
- 20 to 24 px from the viewport edges
- Above safe-area insets on mobile
- High enough to avoid any persistent mobile bottom navigation
- Branded deep green with a simple chat icon

When there is no unread reply, the launcher is quiet. It must not pulse continuously or open itself.

When a reply arrives while the panel is closed:

- Show a numeric unread badge
- Briefly animate once
- Optionally play a subtle sound only if the user has previously interacted with the page and has not disabled chat sounds

### 6.2 First open with no active conversation

Header:

> LoLo Support

Availability line when no agent is present:

> Leave us a message

Introductory copy:

> Hi {{ first name }}. How can we help?

Composer placeholder:

> Ask us a question…

Primary action:

> Send

Secondary link:

> Open Support Center

The user does not enter a subject, priority, name, email address, or phone number. Their authenticated account supplies their identity.

The first message creates a normal support ticket with:

- Source: `chat_widget`
- Category: `general`
- Priority: `normal`
- Subject: a safe, shortened version of the first message, prefixed with **Chat:**
- Description: the complete first message
- Opener: the authenticated user
- Family account and visibility: the existing family-account rules
- Origin route and URL: the application location from which the chat was started

Do not capture form values, page content, query-string secrets, or browser storage. Store only the normalized internal route name and a safe relative URL after removing sensitive query parameters.

After send, immediately render the message optimistically with **Sending…**, then **Sent** after the server confirms it.

### 6.3 Returning with an active conversation

Opening the widget returns to the most recently active chat ticket that is **Open**, **In progress**, or **Resolved**.

The panel shows:

- Support availability or assigned agent name
- Recent message history
- Timestamps grouped naturally rather than repeated on every short message
- A composer
- A link to open the full conversation in the Support Center
- **Start a new conversation** only after the current conversation is resolved or closed

A reply to a resolved conversation automatically reopens it, matching current support behavior.

A closed conversation is read-only. The panel offers **Start a new conversation**.

### 6.4 Live-support signals

The experience uses only truthful signals:

| Situation | User sees |
| --- | --- |
| No administrator is actively viewing the conversation | **Leave us a message** |
| An administrator has the conversation open and has a recent presence heartbeat | **Online now** |
| The conversation is assigned, but the administrator is not currently present | **{{ first name }} from LoLo Support will reply here** |
| Administrator is actively composing | **{{ first name }} is typing…** |
| User message is stored | **Sent** |
| Administrator has opened the conversation after the message arrived | **Seen** |

Presence is ephemeral. It must not be inferred merely because an administrator is signed in somewhere in the application.

For the MVP, typing and seen indicators may be omitted if they threaten delivery. Truthful **Online now**, assigned-agent identity, fast refresh, and visible message arrival are the minimum live-support signals.

### 6.5 Mobile behavior

On viewports below the application breakpoint:

- Use a launcher at least 52 by 52 px with a 44 by 44 px minimum interactive area.
- Keep the launcher 16 px from the viewport edge, plus `env(safe-area-inset-right)` and `env(safe-area-inset-bottom)` where applicable.
- Position it above persistent mobile navigation, cookie controls, and page actions instead of covering them.
- Open as a full available-visual-viewport chat. The persistent branded header, clear minimize control, and browser-back dismissal keep it understandable and dismissible.
- Use dynamic viewport units such as `dvh` with a safe fallback; do not rely on `100vh` alone on mobile browsers.
- Respect top and bottom safe-area insets on devices with a notch, Dynamic Island, or home indicator.
- Keep the header and composer fixed while only the message history scrolls.
- Lock background-page scrolling while the sheet is open and prevent scroll chaining at the top and bottom of the conversation.
- Keep the composer fully above the on-screen keyboard on iOS Safari and Android Chrome.
- Use at least 16 px input text so iOS Safari does not zoom the page when the composer receives focus.
- Expand a multiline draft up to a sensible maximum, then scroll inside the composer without pushing the send button off-screen.
- Keep the send button reachable with one hand and at least 44 by 44 px.
- Provide an obvious minimize control in the header and support the browser back action as a dismissal action when feasible.
- Preserve the user's unsent draft while minimized, rotating the phone, or navigating with Livewire.
- Reopen at the newest message on mobile. Desktop may restore the user's previous reading position.
- When a refresh adds a message while the user is reading earlier history, preserve that reading position and show a clear **New message** control.
- Preserve textarea focus and the current cursor position across the five-second conversation refresh.
- Send with Enter and insert a new line with Shift+Enter; composition/IME input must not be submitted prematurely.
- Avoid horizontal scrolling at 320 px and wider, including with long unbroken text and large accessibility font settings.
- Recalculate the layout after orientation changes and keyboard open/close events without jumping the message history.

Pressing the browser back button should close the sheet before leaving the current application page when feasible.

Mobile is not allowed to hide capabilities available on desktop. The user can start a chat, read history, send and retry messages, see availability, minimize, reopen, and follow the full Support Center link from a phone.

### 6.6 Emergency and sensitive-issue language

The chat footer should include a quiet but visible safety line:

> LoLo Support is not an emergency service. If someone is in immediate danger, call 911.

The first release should not attempt keyword-based crisis classification. Structured disputes, incidents, billing requests, and visit corrections should link to the full Support Center when an administrator determines that the issue needs the existing workflow.

## 7. Admin experience

### 7.1 Notification behavior

When a first chat message creates a ticket:

- All administrators receive the existing in-app support notification.
- The existing operations email alert is sent once.
- The admin support badge updates within ten seconds.
- The new chat appears at the top of the open queue as unread and unassigned.

When the user sends another message:

- If assigned, notify the assigned administrator in-app.
- If unassigned, notify all administrators in-app.
- Do not send an operations-wide email for every message.
- The global unread support badge remains the backstop even if a per-message notification is deduplicated.

When an administrator replies:

- The user receives the existing in-app notification.
- The user receives the existing email notification unless their notification preferences disable it.
- If the widget is currently open, the reply appears through polling and is marked read without requiring navigation.

### 7.2 Conversation header

The admin sees:

- User name
- Role: Family or Caregiver
- Verified email and phone, when present
- Family-account relationship when applicable
- Conversation status and priority
- Assigned administrator
- Whether the user currently has the widget open
- Origin route, for example **Booking #184** or **Family dashboard**
- Related care request or booking when one is attached
- Link to the existing admin user profile

The administrator should not need to ask “Which account is this?” or “Where were you when this happened?” before replying.

### 7.3 Claiming and collision handling

An unassigned chat shows **Claim conversation**.

Claiming performs one atomic update. If two administrators claim at nearly the same time, only the first succeeds; the second sees **Already claimed by {{ name }}**.

Another administrator may still open and read the conversation, but the interface clearly shows who owns the reply. Internal notes remain available for coordination.

### 7.4 Suggested operational response states

| Status | Meaning |
| --- | --- |
| Open | Waiting for support triage or reopened by the user |
| In progress | Claimed or answered by an administrator |
| Resolved | Support believes the question is handled; user may reopen by replying |
| Closed | Archived and read-only; a new issue requires a new conversation |

## 8. Interaction flow

```mermaid
sequenceDiagram
    participant U as "Signed-in user"
    participant W as "Support widget"
    participant S as "Existing support service"
    participant A as "Admin support inbox"

    U->>W: Opens widget and asks a question
    W->>S: Create chat-sourced support ticket
    S-->>W: Ticket and first-message confirmation
    S-->>A: Unread badge and in-app notification
    A->>S: Claim conversation
    S-->>W: Show assigned support person as present
    A->>S: Send public reply
    S-->>W: Reply appears on next refresh
    U->>S: Send follow-up
    S-->>A: Mark conversation unread
    A->>S: Resolve conversation
    S-->>W: Show resolved state; reply can reopen
```

## 9. Technical design

### 9.1 Reuse

Continue to use:

- `support_tickets`
- `support_ticket_messages`
- `SupportTicketMessagingService`
- `SupportTicketPolicy` and `SupportTicketMessagePolicy`
- `SupportTicketsQueue` and `SupportTicketShow`
- Existing support unread fields and family read tracking
- Existing marketplace notification infrastructure

Create one global Livewire support-widget component and render it from the authenticated application layout.

### 9.2 Required persisted fields

Add to `support_tickets`:

| Field | Purpose |
| --- | --- |
| `source` string, default `support_center` | Distinguishes widget conversations from structured tickets |
| `origin_route` nullable string | Safe Laravel route name where the chat began |
| `origin_path` nullable string | Safe relative path with sensitive query values removed |
| `claimed_at` nullable timestamp | Records when assignment began |

`assigned_admin_id` remains the source of truth for ownership. Existing message and read timestamps remain the source of truth for conversation activity and unread state.

Do not persist typing state or presence heartbeats in relational tables.

### 9.3 Ephemeral presence

Use the application cache with short time-to-live keys:

- `support:ticket:{ticketId}:user-present:{userId}`: refreshed while the user's panel is open
- `support:ticket:{ticketId}:admin-present:{adminId}`: refreshed while the assigned administrator has the conversation open
- Optional typing keys with a five-to-eight-second lifetime

A participant is present only if their heartbeat is recent, suggested threshold 30 seconds.

Do not store a permanent log of every presence or typing heartbeat.

### 9.4 Refresh behavior

For the initial release:

- Open, visible chat panel: refresh conversation and presence every 3 to 5 seconds
- Minimized widget: refresh unread count every 10 seconds
- Browser tab hidden: pause or back off to at least 30 seconds
- Admin conversation open: refresh every 3 to 5 seconds
- Admin global navigation: retain the current 10-second visible refresh
- Pause message refresh while a composer is focused if Livewire would otherwise overwrite a draft

Use the current `client_message_id` idempotency mechanism so retries cannot create duplicate messages.

WebSockets can replace polling later without changing the ticket or message model.

### 9.5 Conversation selection

The widget loads only tickets visible to the authenticated user under the existing policy and family-account boundary.

Preferred active ticket order:

1. Most recently active `chat_widget` ticket in **Open** or **In progress**
2. Most recently active `chat_widget` ticket in **Resolved**
3. No active chat

Structured Support Center tickets are visible through the history link but should not automatically take over the compact widget unless they were created by the widget.

### 9.6 First-message creation

Ticket creation and initial-message state must be transactional. The implementation may continue treating `support_tickets.description` as the initial public message for compatibility, but the widget must render it as the first chat bubble.

Subject generation requirements:

- Normalize whitespace
- Remove control characters
- Limit to the existing 160-character field
- Use a human-readable excerpt, not an AI summary
- Fall back to **Chat support request** if no safe excerpt remains

### 9.7 Draft persistence

Preserve the unsent draft when the widget is minimized or the user navigates through Livewire. Browser session storage is sufficient. Scope it to the authenticated user and active ticket, and clear it after a confirmed send or logout.

## 10. Authorization, privacy, and safety

1. Every widget request requires an authenticated session.
2. Every ticket load and send action must pass the existing support policies.
3. A user may never choose or submit another user's identifier.
4. Family-account visibility continues to distinguish shared-care and owner-only support issues.
5. Administrators may see the authenticated user's operational context, but the widget must not collect unrelated page contents.
6. Message bodies are rendered as escaped text; no raw HTML is accepted.
7. Apply send rate limits and the existing 3,000-character maximum.
8. Reject empty or whitespace-only messages.
9. Maintain idempotency for repeated sends and network retries.
10. Do not include sensitive message content in notification email subject lines, lock-screen text, or analytics events.
11. Log assignment, status, resolution, and administrative actions using existing audit patterns.
12. Chat messages use the same retention and deletion rules as support tickets. Under support-agent `DEC-026`, the unified human-and-AI conversation content is retained while open and for 12 calendar months after its most recent final resolution. Reopening resets the clock; expiry automatically deletes conversation-bearing content and identifiable derivatives unless a narrow authorized legal/security hold applies. Linked care, booking, payment, account, and other authoritative domain records retain their own rules.

## 11. Accessibility

- Launcher has an accessible name and visible focus treatment.
- Unread state is conveyed in text, not color alone.
- New messages are announced through an appropriately scoped `aria-live="polite"` region.
- The panel traps focus only when operating as a mobile modal sheet.
- Escape minimizes the desktop panel.
- Touch targets are at least 44 by 44 px.
- Color contrast meets WCAG AA.
- Motion respects `prefers-reduced-motion`.
- Sending and failure states are announced to assistive technology.

## 12. Empty, failure, and edge states

| Condition | Behavior |
| --- | --- |
| Network unavailable | Keep the draft and show **You're offline. We'll send when you reconnect.** Do not claim the message was sent. |
| Send times out | Keep the optimistic bubble in a failed state with **Try again** using the same client message ID. |
| Session expires | Preserve the draft locally and ask the user to sign in again. |
| Ticket resolved while open | Show the resolved banner; allow a reply that reopens it. |
| Ticket closed while open | Make the conversation read-only and offer a new conversation. |
| Assignment changes | Update the support-person name on the next refresh. |
| Two browser tabs send at once | Idempotency and server ordering prevent duplicates. |
| User account loses access | Stop returning messages immediately under the existing policy. |
| Long history | Load the newest messages first and offer **Load earlier messages**. |

## 13. Success measures

Measure without capturing message content:

- Widget opens
- First messages sent
- Chat tickets created
- Median time from user message to first admin view
- Median time from user message to first admin reply
- Percentage of chats resolved without a second structured support ticket
- Reopened conversation rate
- Send failure rate
- Number of concurrent open/unassigned chats

Suggested initial product targets:

- At least 95% of valid sends confirm without retry
- New chat visible in the admin experience within ten seconds
- New reply visible in an open user chat within five seconds under normal conditions
- No cross-user or cross-family ticket exposure

## 14. Release plan

### Phase 1: Chat-shaped MVP

- Global authenticated widget
- Start or reopen one chat conversation
- Existing message history and sending service
- Polling, unread badge, minimize, and draft preservation
- Source and origin context
- Admin unread notification, assignment, reply, resolve, and close
- Honest availability copy
- Desktop and mobile responsive behavior

### Phase 2: Stronger live presence

- Admin and user presence heartbeat
- Assigned-agent identity in the widget
- **Online now**, **Seen**, and optional typing indicators
- Optional sound controls
- Admin queue refinements for rapid chat triage

### Phase 3: Scale only when needed

- WebSockets or server-sent events
- Attachments with malware scanning and strict access controls
- Saved replies and support macros
- Service-level reporting and business-hours routing
- Public pre-login chat, if operationally justified
- External help-desk or team notification integrations

## 15. Acceptance criteria for the MVP

1. A signed-in family or caregiver sees the support launcher on every standard authenticated page.
2. An administrator or signed-out visitor does not see it.
3. The user can send a first message without entering identity or a subject.
4. The resulting record is a normal support ticket linked to the correct user and family boundary.
5. The starting route/path is visible to an administrator and contains no sensitive query values.
6. The administrator receives an in-app unread notification and the existing new-ticket operations email is sent.
7. The administrator can see the user's name, role, contact details, origin, and relevant linked care context.
8. The administrator can claim and publicly reply to the chat.
9. Both open conversation views show new messages within five seconds under normal conditions.
10. The user receives an unread launcher badge while the widget is minimized.
11. Minimizing or navigating does not lose the active conversation or unsent draft.
12. A reply to a resolved conversation reopens it.
13. A closed conversation is read-only and offers a new conversation.
14. Duplicate requests with the same client message ID create only one message.
15. Authorization tests prove that another user and another family account cannot access the conversation.
16. The widget works at 375 px width, with the on-screen keyboard, and with keyboard-only navigation.
17. The interface never shows **Online now** without a recent support-presence signal.
18. Opening the mobile keyboard leaves the latest message, composer, and send action visible without manual page zooming.
19. The launcher and sheet do not cover persistent navigation or critical page actions.
20. Minimizing, reopening, rotating the phone, and Livewire navigation preserve the active conversation and unsent draft.
21. Long messages, 200% text sizing, and error messages do not introduce horizontal scrolling or inaccessible controls.
22. Opening or reopening on mobile positions the conversation at the newest message.
23. The five-second refresh does not remove focus or move the cursor while the user is typing.
24. Enter sends, Shift+Enter creates a new line, and the send control shows a visible pending state.
25. A user reading older messages gets a **New message** control instead of being pulled away from their reading position.

### 15.1 Required mobile QA matrix

The MVP is not ready for release until the end-to-end flow passes on:

| Viewport/device class | Browser | Required checks |
| --- | --- | --- |
| 320 px compact phone | Responsive browser test | No horizontal overflow; launcher and all controls remain reachable |
| 375 x 667 iPhone class | Safari/WebKit | Open, type, send, receive, minimize, reopen, keyboard open/close |
| 390 x 844 iPhone class | Safari/WebKit | Safe areas, bottom sheet height, draft persistence, long history |
| 360 x 800 Android class | Chrome/Chromium | Keyboard resizing, back-button dismissal, retry after network failure |
| 430 x 932 large phone | Safari/WebKit and Chrome/Chromium | Sheet proportions, one-handed controls, orientation change |

For each mobile class, verify:

- A first-time chat and a returning conversation
- Empty, sending, sent, failed, unread, resolved, and closed states
- A message long enough to wrap across several lines
- At least 40 messages in history
- Browser text zoom or operating-system font scaling up to 200%
- Slow network and temporary offline recovery
- Portrait and landscape orientation
- Screen-reader names for launcher, minimize, composer, send, retry, and unread state

## 16. Recommended initial scope decision

Ship the chat-shaped MVP first, with one deliberate simplification: do not block launch on typing indicators or WebSockets.

The meaningful value is that help is always one click away, the user is already identified, support is notified, the conversation persists, and replies appear quickly. Presence and typing can then be added against real support staffing behavior instead of simulating live support before the operational process exists.
