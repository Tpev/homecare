# GPT-5.6 Implementation Prompt: LoLo In-App Support Chat

Use this prompt from the repository root with GPT-5.6. The product specification at `docs/product/support-live-chat-spec.md` is the canonical requirement.

```text
Role: You are the senior full-stack engineer responsible for implementing LoLo Care's authenticated in-app support chat in this Laravel application.

Goal: Implement the complete Phase 1 chat-shaped MVP defined in `docs/product/support-live-chat-spec.md`. Deliver a production-quality, mobile-first support widget for signed-in family and caregiver users, backed by the existing support-ticket system, plus the required admin inbox refinements, notifications, persistence, authorization, tests, and visual verification.

Start by reading the entire specification and inspecting the current support implementation, application layout, navigation, notification infrastructure, policies, tests, and design system. Treat the specification as authoritative for product behavior. Reuse the existing support domain instead of creating a parallel chat system.

Current repository context:
- Laravel 12, Livewire 3, Alpine, Blade, Tailwind/Vite, PHPUnit, and Playwright.
- Existing support models: `SupportTicket` and `SupportTicketMessage`.
- Existing messaging path: `SupportTicketMessagingService`.
- Existing user and admin support Livewire components, policies, unread tracking, assignment, notification delivery, and operations email alerts.
- Existing support conversations refresh every five seconds; the admin support badge refreshes every ten seconds.
- The authenticated application shell is `resources/views/layouts/app.blade.php` with navigation in `resources/views/livewire/layout/navigation.blade.php`.
- The working tree may contain unrelated user files or changes. Preserve them and do not modify unrelated work.

Success criteria:
- Every Phase 1 requirement and every applicable acceptance criterion in the specification is implemented.
- Signed-in family and caregiver users see a bottom-right launcher on standard authenticated application pages; admins, SDR/sales users, and signed-out visitors do not.
- A user can create, minimize, reopen, read, reply to, retry, resolve/reopen, and continue a chat without leaving the current page or re-entering identity.
- The widget uses existing support tickets/messages, policies, family-account boundaries, notifications, assignment, statuses, and idempotent client message IDs.
- Chat tickets safely persist `source`, `origin_route`, sanitized `origin_path`, and `claimed_at` as specified.
- The admin queue clearly identifies chat-sourced conversations, prioritizes unread activity, displays useful user/origin context, and allows atomic claiming without overwriting another admin's claim.
- New messages and unread state appear within the polling windows required by the specification.
- Availability language is truthful. Do not display "Online now", "typing", or "seen" unless the corresponding recent presence/read evidence is actually implemented. Phase 2 presence features are not required for this run.
- Mobile behavior is first-class and passes the specification's required QA matrix, including iOS/WebKit and Android/Chromium keyboard behavior, safe areas, dynamic viewport sizing, back-button dismissal where feasible, rotation, 200% text, long messages, long history, and no horizontal overflow at 320 px.
- Accessibility, privacy, authorization, rate limiting, idempotency, offline/failure handling, draft preservation, and safe origin capture meet the specification.
- Existing structured Support Center flows and existing tests continue to work.

Implementation constraints:
- Implement Phase 1 only. Do not add AI replies, attachments, public visitor chat, WebSockets, external help-desk integrations, or other Phase 2/3 scope.
- Prefer a global Livewire widget rendered by the authenticated application layout and existing services/policies over new controllers or duplicated message logic.
- Keep `assigned_admin_id` as ownership source of truth and existing message/read timestamps as activity source of truth.
- Make ticket creation plus its initial state transactional and safe under retries.
- Continue escaping message bodies; accept no raw HTML.
- Sanitize origin context on the server. Store only an internal route name and safe relative path. Strip the query string and never capture form contents, page contents, secrets, or browser storage.
- Enforce authorization server-side on every load, create, send, retry, and claim action. Never trust a user-supplied user ID, family account ID, ticket ID without policy checks, route name, or origin path.
- Add an explicit throttle for message creation that is reasonable for interactive chat and does not interfere with normal conversation.
- Preserve drafts per authenticated user and active/new conversation in session storage. Clear them after confirmed send and on logout where practicable. Never store message drafts in analytics.
- Use optimistic sending only when the UI clearly distinguishes Sending, Sent, and Failed. A retry must reuse the same client message ID so it cannot duplicate a stored message.
- On hidden browser tabs, pause or back off polling as specified. Do not allow Livewire refreshes to erase a focused or unsent composer.
- Use existing LoLo colors, typography, components, shadows, and interaction patterns. Do not introduce a generic third-party chat aesthetic or a new frontend framework.
- Do not alter notification defaults beyond what is needed for this feature. A first chat retains the existing operations email behavior; follow-up messages must not generate an operations-wide email storm.
- Do not modify `.env`, install a third-party chat service, deploy, commit, push, or perform any external write.

Mobile interaction requirements:
- The launcher must avoid persistent navigation and critical page actions and honor safe-area insets.
- The phone experience must be a polished bottom sheet using the visual viewport/dynamic viewport rather than `100vh` alone.
- Only message history scrolls; header and composer remain reachable.
- Prevent background scroll and scroll chaining while open.
- Input text is at least 16 px on mobile; interactive targets are at least 44 by 44 px.
- The composer and send/retry controls remain visible when the virtual keyboard opens in iOS Safari and Android Chrome.
- Minimize, Livewire navigation, keyboard open/close, and orientation changes preserve the draft, active conversation, and useful scroll position.
- Long unbroken content and 200% text sizing must not create horizontal overflow.
- Desktop behavior should remain compact, responsive, keyboard accessible, and visually consistent with the phone implementation.

Required engineering work:
- Add backward-compatible migrations and update models/factories as needed.
- Build the global user chat widget and its responsive Blade/Alpine/Livewire behavior.
- Extend existing support services rather than bypassing them.
- Refine the admin queue/conversation experience only as required for chat source, ordering, context, claiming, unread behavior, and replies.
- Add or update policies, middleware/throttling, notification behavior, and audit-relevant timestamps as needed.
- Add focused unit/feature tests for ticket creation, initial message representation, active-ticket selection, family sharing/privacy, origin sanitization, message idempotency, retry behavior, rate limiting, claim collision, assignment, unread/read state, reopen/closed behavior, role visibility, and notification fan-out.
- Add Playwright coverage for the core desktop flow and the required mobile flows. Reuse the project's existing e2e authentication and data setup patterns.
- Keep the implementation maintainable: focused components and services, explicit state transitions, no large duplicated query blocks when an existing scope/service can own them.

Verification requirements:
- Run the most relevant targeted PHPUnit tests while iterating, then run the complete PHP test suite if its runtime is reasonable.
- Run formatting/lint checks used by this repository, the production frontend build, and the relevant Playwright tests.
- Render and inspect the actual UI, not only test selectors. Verify the launcher and all widget states on desktop and at 320, 360, 375, 390, and 430 px phone widths, including at least one WebKit/iPhone-class and one Chromium/Android-class project.
- Inspect screenshots for clipping, overlap, safe-area issues, message wrapping, visual hierarchy, focus states, keyboard/composer reachability where automation permits, and consistency with the LoLo design system.
- Test both family and caregiver users and confirm the widget is absent for admins and signed-out visitors.
- Test two different users/family accounts and an invited family member to prove no cross-account exposure.
- Run `git diff --check` and review the final diff for accidental unrelated changes, debug code, secrets, malformed copy, or encoding artifacts.
- If an existing unrelated failure prevents a validation step, isolate it, record exact evidence, and continue every other useful validation. Do not weaken tests or product behavior to hide a failure.

Working style and permissions:
- You are authorized to inspect the repository, make all in-scope local edits, create migrations/tests, and run non-destructive local validation without asking for confirmation.
- Make reasonable implementation decisions that preserve the specification and existing architecture. Ask a question only if a genuinely material product decision cannot be resolved from the spec or repository and different answers would create incompatible implementations.
- Before tool use, give a short update stating the first implementation phase. During the run, report only meaningful findings or phase changes; do not narrate routine commands.
- Keep working through implementation, tests, and visual QA. Do not stop after producing a plan, scaffold, partial backend, or desktop-only UI.
- Do not declare completion while required work or fixable validation failures remain.

Final response:
- Lead with whether the feature is complete.
- Summarize the user experience, admin experience, data/security decisions, and mobile behavior actually delivered.
- List the important changed files using clickable absolute paths when supported.
- Report exact validation commands and outcomes, including test counts where available.
- Disclose any remaining limitation or unrun check precisely. Do not describe a requirement as implemented unless the code and validation support it.
```

Recommended run configuration: `gpt-5.6-sol` with high reasoning effort. Use max only if the implementation or validation repeatedly exposes difficult architectural failures that high effort does not resolve.
