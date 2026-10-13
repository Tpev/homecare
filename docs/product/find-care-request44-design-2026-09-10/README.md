# Clearer recruitment stages

Local reference: http://127.0.0.1:8028/family/requests/44

The shared recruitment view now uses four connected arrows with a short explanation for each stage: care and schedule details, search and invite, compare and chat, and review and confirm care. The highlighted arrow identifies the current view; it does not imply invitations or earlier stages are mandatory or completed.

Get started now opens a preparation screen before a caregiver has been selected. It explains selection, reviewing schedule/rate/fees, and confirmation. Its next action opens applicants when there are active replies, or caregiver search otherwise. It never selects a caregiver or hires anyone. The existing named-applicant review and explicit confirmation remain unchanged. Booked requests continue to use their existing visit navigation.

The request overview replaces the tall right sidebar with a compact next-action banner, with applicant and invitation counts beside the hiring context. The complete recipient, schedule, address, visibility, tasks, notes, full-details link, and recurring requested-visits link remain available. Discovery now has a visible heading and purpose. Closed and expired requests keep their status and history context.

Frontend build passed. All 23 navigation/recruitment scenarios were verified, including new checks that the preparation screen cannot create bookings, change applications, bypass request ownership, or open for booked care. The only controller change is the conditional read-only start tab. Existing tests remain unchanged outside the two new navigation cases.

Browser verification covered all four stages at 1440px, 390px, and 320px, desktop hover states, keyboard focus, and existing requests with applicants and recurring care. The final focus indicator clears both the arrow tips and narrow-screen labels. Every original care-data row remains intact; an invitation independently added during review is recorded separately in the QA evidence.

- [Updated request overview](screenshots/request44-desktop-home-top.png)
- [Get started guidance](screenshots/request44-desktop-start-top.png)
- [Mobile caregiver search](screenshots/request44-mobile-invite-top.png)
- [Final keyboard-focus verification](focus-validation.json)

Original screenshots and visible text are preserved in [before](before/).
