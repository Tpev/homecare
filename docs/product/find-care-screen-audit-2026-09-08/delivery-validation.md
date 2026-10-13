# Final capture and data validation

Checked 2026-09-08T16:04:21.426Z. **All required final checks passed.**

The gallery contains **319 tracked screen states with desktop and mobile evidence (638 device views, 1914 lifecycle PNGs)**. All 175 original September 7 IDs are preserved. The additional 144 IDs cover redesigned destinations, controls and plan conditions. Six role/device login checks have a separate 12 screenshots and do not inflate lifecycle coverage.

| Check | Final result | Evidence |
|---|---|---|
| Baseline comparison coverage | 175/175 IDs, both viewports | [Coverage](baseline-coverage.json) |
| PNG dimensions, full information inventories, final build, fonts and overflow | Passed; no missing fonts or horizontal overflow | [Artifact validation](validation.json) |
| Rendered state, record identity, selected tabs and creation variation | 2640 assertions across 638 device records | [Semantic checks](screen-semantics.json) |
| Original care data | 685 original rows across 61 tables retained | [Field comparison](data-preservation.json) |
| Family, caregiver and administrator access | 6/6 login/route checks passed | [Role screenshots](role-smoke.md) |
| Gallery search, combined filters, image/inventory links and mobile width | 11 checks passed | [Gallery smoke](gallery-smoke.json) |

## Evidence after the final presentation changes

The last two changes affected Blade/PHP presentation without changing the frontend asset manifest. The following check therefore compares capture timestamps directly with the final affected source-file timestamps, in addition to recording all final source hashes.

| Change | Latest source change (UTC) | Captured states checked | Result |
|---|---|---|
| Applicant replies before invitation area | 2026-09-08T15:37:29.844Z | 50 | Every capture follows the source change |
| Current visit before follow-up; operational decisions before optional reviews | 2026-09-08T15:39:07.274Z | 25 | Every capture follows the source change |

[Exact source paths, SHA256 hashes and all affected capture IDs/times](source-refresh-validation.json). [Final implementation/build provenance](provenance.json).

The final refresh/retry request was created at **2026-09-08T15:54:27.635Z**. All **57 requested IDs** were completed after that request: 50 request Caregivers-tab states, four original parent Overview states, and three preparation retries. The support retries minimize the remembered dialog through its visible control before proving that the current page's inline entry reopens it. The private-create preparation now waits for each blurred field to settle.

| Refreshed tracking ID | Final capture time (UTC) |
|---|---|
| OT-hire-review | 2026-09-08T15:59:17.216Z |
| OT-waiting-applicants | 2026-09-08T15:54:53.675Z |
| OT-invitations-applicants | 2026-09-08T15:54:57.394Z |
| OT-applicants-applicants | 2026-09-08T15:55:00.536Z |
| OT-scheduled-applicants | 2026-09-08T15:55:03.956Z |
| OT-in_progress-applicants | 2026-09-08T15:55:07.341Z |
| OT-after_shift-applicants | 2026-09-08T15:55:10.795Z |
| OT-timesheet_review-applicants | 2026-09-08T15:55:13.742Z |
| OT-leave_review-applicants | 2026-09-08T15:55:16.988Z |
| OT-closed-applicants | 2026-09-08T15:55:19.949Z |
| OT-cancelled-applicants | 2026-09-08T15:55:22.853Z |
| OT-cancelled_booking-applicants | 2026-09-08T15:55:25.594Z |
| RC-waiting-applicants | 2026-09-08T15:55:28.491Z |
| RC-invitations-applicants | 2026-09-08T15:55:31.709Z |
| RC-applicants-applicants | 2026-09-08T15:55:34.650Z |
| RC-scheduled-applicants | 2026-09-08T15:55:37.751Z |
| RC-in_progress-applicants | 2026-09-08T15:55:40.759Z |
| RC-after_shift-applicants | 2026-09-08T15:55:43.968Z |
| RC-timesheet_review-applicants | 2026-09-08T15:55:47.676Z |
| RC-leave_review-applicants | 2026-09-08T15:55:53.553Z |
| RC-closed-applicants | 2026-09-08T15:55:58.055Z |
| RC-cancelled-applicants | 2026-09-08T15:56:02.340Z |
| RC-cancelled_booking-applicants | 2026-09-08T15:56:07.594Z |
| OT-invitations-applicants-expanded | 2026-09-08T15:56:11.999Z |
| OT-applicants-applicants-expanded | 2026-09-08T15:56:16.788Z |
| OT-invite-drawer | 2026-09-08T15:56:24.018Z |
| RC-invitations-applicants-expanded | 2026-09-08T15:56:29.820Z |
| RC-applicants-applicants-expanded | 2026-09-08T15:56:34.259Z |
| RC-invite-drawer | 2026-09-08T15:56:41.881Z |
| RC-plan-active | 2026-09-08T15:56:47.038Z |
| RC-plan-pending | 2026-09-08T15:56:50.537Z |
| RC-plan-paused | 2026-09-08T15:56:56.274Z |
| RC-plan-ended | 2026-09-08T15:57:00.391Z |
| OT-create-self-private | 2026-09-08T15:57:04.185Z |
| OT-invite-confirmation | 2026-09-08T15:57:21.806Z |
| OT-invite-search-certifications | 2026-09-08T15:57:31.109Z |
| OT-invite-drawer-bottom | 2026-09-08T15:57:38.958Z |
| OT-single_applicant-applicants | 2026-09-08T15:57:45.183Z |
| OT-many_applicants-applicants | 2026-09-08T15:57:51.500Z |
| OT-private_waiting-applicants | 2026-09-08T15:57:55.722Z |
| OT-paused-applicants | 2026-09-08T15:58:00.486Z |
| OT-payment_attention-applicants | 2026-09-08T15:58:04.229Z |
| OT-time_correction-applicants | 2026-09-08T15:58:09.521Z |
| OT-many_applicants-applicants-expanded | 2026-09-08T15:58:14.259Z |
| RC-invite-confirmation | 2026-09-08T15:58:19.192Z |
| RC-invite-search-certifications | 2026-09-08T15:58:25.872Z |
| RC-invite-drawer-bottom | 2026-09-08T15:58:34.700Z |
| RC-single_applicant-applicants | 2026-09-08T15:58:40.677Z |
| RC-many_applicants-applicants | 2026-09-08T15:58:45.541Z |
| RC-private_waiting-applicants | 2026-09-08T15:58:50.129Z |
| RC-paused-applicants | 2026-09-08T15:58:55.300Z |
| RC-payment_attention-applicants | 2026-09-08T15:59:00.303Z |
| RC-time_correction-applicants | 2026-09-08T15:59:06.504Z |
| RC-many_applicants-applicants-expanded | 2026-09-08T15:59:10.808Z |
| RC-hire-review | 2026-09-08T15:59:22.917Z |
| OT-inline-support-visit | 2026-09-08T15:59:27.468Z |
| RC-inline-support-plan | 2026-09-08T15:59:31.078Z |

[Original and final capture timestamps for every requested refresh](refresh-completion.json).

## Exact data-preservation scope

The checker opens both isolated SQLite databases in query-only mode. It compares original rows by primary/composite key and every stored field, with the explicitly listed authentication exceptions below. Additive synthetic rows are allowed. The original September 7 database file also retains its initialization SHA256.

| Compared table | Original rows retained |
|---|---|
| care_requests | 38 |
| care_request_applications | 37 |
| care_request_invitations | 4 |
| care_bookings | 23 |
| care_booking_payments | 23 |
| care_booking_payment_attempts | 0 |
| care_booking_payment_operations | 0 |
| care_reviews | 10 |
| care_booking_change_requests | 0 |
| care_booking_time_corrections | 2 |
| care_booking_corrections | 0 |
| care_booking_incidents | 0 |
| care_plans | 7 |
| care_plan_events | 4 |
| care_plan_schedule_changes | 0 |
| completed_extra_visit_requests | 0 |
| care_booking_events | 73 |
| care_booking_task_checks | 69 |
| care_recipient_profile_versions | 2 |
| care_recipient_profiles | 2 |
| care_relationships | 4 |
| care_request_conversations | 2 |
| care_request_messages | 4 |
| care_request_recipients | 38 |
| care_request_task | 111 |
| care_request_third_party_contacts | 37 |
| care_tasks | 7 |
| caregiver_availabilities | 63 |
| caregiver_certification_types | 9 |
| caregiver_certifications | 5 |
| caregiver_experience_types | 10 |
| caregiver_identity_verifications | 0 |
| caregiver_language | 9 |
| caregiver_payout_items | 0 |
| caregiver_payouts | 0 |
| caregiver_profile_experience | 8 |
| caregiver_profile_versions | 0 |
| caregiver_profiles | 9 |
| caregiver_skill | 22 |
| family_account_invitations | 2 |
| family_account_members | 9 |
| family_accounts | 6 |
| family_caregiver_favorites | 0 |
| family_household_profiles | 0 |
| family_recipient_profiles | 2 |
| languages | 6 |
| skills | 7 |
| support_ticket_activities | 0 |
| support_ticket_messages | 0 |
| support_tickets | 1 |
| user_notification_preferences | 0 |
| continuous_coverage_events | 0 |
| continuous_coverage_handoffs | 0 |
| continuous_coverage_lane_requests | 0 |
| continuous_coverage_plans | 0 |
| continuous_coverage_replacement_cases | 0 |
| continuous_coverage_roster_members | 0 |
| continuous_coverage_shift_offers | 0 |
| continuous_coverage_shift_templates | 0 |
| continuous_coverage_shifts | 0 |
| users | 20 |

Excluded user columns: users: password, remember_token, last_login_at, last_seen_at, updated_at.

Operational/out-of-scope exclusions:

- sessions, password_reset_tokens, cache, cache_locks
- family_conversation_reads, family_support_ticket_reads, care_recipient_profile_views (read/view receipts)
- family_account_activity_logs, caregiver_moderation_logs, page_view_events, funnel_events, marketplace_notification_deliveries, notifications (activity/delivery telemetry)
- jobs, job_batches, failed_jobs, migrations, sqlite_sequence
- ai_support_* (assistant runtime telemetry/drafts)
- content/blog/marketing/lead/knowledge-base/infrastructure tables outside care records

## Interpretation and limits

- These are actual local application captures using synthetic data. They are not production activity or completed live payment/provider checks.
- The original example dates and independent stored statuses are retained: an ongoing September 7 visit is a deliberately staged example. After-shift and timesheet-review examples both contain submitted, unapproved hours.
- New countered/declined/cancelled offers have their own historical source care but no generated recurring bookings. Proposal dates are labelled as unbooked.
- Tracking identities can share a layout after redesign; default/expanded IDs and empty filter states remain for comparison. Role and gallery checks are separate.
- Inter and Source Serif 4 load from local font files. External map/payment embeds are blocked for reproducibility; their fallback states do not prove provider failures.
- Full plan event payloads remain in the database; the UI care record shows its actual dates and instructions, without claiming a new event timeline.
- Per-screen rationale explains the implementation. Representative visual reviews and automated checks are recorded separately; this report does not claim a human visual review of all 638 device views.

[Implementation guide](implementation-guide.md) · [Preservation map](implementation-preservation-map.md) · [Representative visual findings](validation-notes.md) · [Application test evidence](validation/application-tests-summary.md)
