# Creation and existing-flow compatibility validation

Validated locally on 2026-09-08. The final compatibility selection passes **112 tests / 698 assertions**. Additional family invitation coverage passes **18 tests / 159 assertions**, and family care navigation/cards pass **10 tests / 93 assertions**: **140 tests / 950 assertions** across these three disjoint selections.

## Executed checks

| Selection | Result | Evidence |
| --- | --- | --- |
| `Family/CareRequestFlowTest`, `Marketplace/PrivateCareRequestTest`, `Booking/BookingTrustOpsTest`, `Booking/BookingCorrectionServiceTest`, `Booking/AutoApproveTimesheetsCommandTest`, `Payments/StripeMarketplacePaymentTest`, `Messaging/CareRequestMessagingTest`, `Caregiver/CareRequestInvitationTest` | 112 passed, 698 assertions, 71.04 seconds | [Final compatibility log](compatibility-tests.log) |
| `Family/CaregiverInvitationExperienceTest` | 18 passed, 159 assertions, 11.04 seconds | [Family invitation log](family-invitation-tests.log) |
| `Family/FamilyCareExperienceTest` | 10 passed, 93 assertions, 9.26 seconds | [Family care experience log](family-care-experience-tests.log) |
| Creation input/action/error parity | 30 logical `wire:model` fields, 6 `wire:click` actions and 34 field-error bindings retained; none removed | Original creation template compared with the new template plus review partial |
| Owned-file whitespace check | Passed | `git diff --check` on creation, review partial, family card wrappers and migrated tests |

The main selection includes one-time, recurring and unequal weekday schedule publishing; first matching day alignment; same-day scheduling; self care; saved household/recipient reuse; private visibility and eligibility; hire/payment failure recovery; messaging; submitted timesheets; reviews and booking correction services. Payment checks use the existing test environment and mocked payment boundaries where specified by those tests. The dedicated time-correction and recurring-plan suites, new workspace contracts and browser interaction/capture results are tracked separately by their owners.

## Intentional migration of existing UI assertions

The first run passed 106 tests and failed six old UI assertions before some downstream assertions could execute. Those failures were retained in [the original run log](compatibility-before-test-migration.log). All six tests now execute to completion using the current navigation and confirmation controls; their database and profile-data assertions remain in place.

| Existing test in `Family/CareRequestFlowTest` | Updated UI expectation and retained coverage |
| --- | --- |
| `test_request_detail_lands_on_the_right_lifecycle_screen` | Open requests land on Overview (`home`); Caregivers is opened explicitly. Empty, single and multiple candidate states retain invitation, hire and chat access. Scheduled, timesheet and completed states retain details/help/reviews and reject rescheduling after completion. Timesheet cost is checked inside its review step. |
| `test_failed_hire_payment_keeps_request_open_on_current_screen` | Opens Caregivers, reviews the hire and confirms it. The payment exception still leaves the request open, preserves the shortlisted applicant and creates no booking. |
| `test_family_request_page_repairs_open_status_when_booking_already_exists` | Checks the current Scheduled status text. Existing booking still repairs request/application status to filled/hired. |
| `test_family_can_save_and_chat_with_applicant_before_hiring` | Opens Caregivers and uses the current Save & chat action. Conversation redirect and applicant shortlist assertions are unchanged. |
| `test_closed_request_still_links_to_caregiver_profile_from_applicants` | Uses Selected caregiver plus the person's name instead of the retired sentence. Bio, care skill, language and profile URL assertions remain unchanged. |
| `test_family_can_confirm_timesheet_when_booking_status_is_reviewed` | Opens Review hours & payment, verifies hours and all price amounts, then confirms through the new wrapper. The payment capture mock must be called once and family confirmation must persist. |

The original file was saved at `.tmp/find-care-screen-audit-2026-09-08/CareRequestFlowTest.before-ui-update.php` before migration. SHA256: `5113e279952e8cf153e86e667ce05d68cffd6f5f0da71580629c499dc906585b`. [The preservation check](test-migration-preservation.txt) confirms that **every byte outside the six migrated method blocks matches that backup**, including the user's Carbon/DataProvider imports and same-day scheduling tests/provider.

Two other presentation-only expectations were migrated: private request visibility now reads **By invitation**, and recurring cards now use stable **Recurring care for [recipient]** titles with a separate **Caregiver: [name]** line. Their privacy, filtering, route and data assertions remain covered.

## Source and visual review

The creation redesign changes the Blade view and adds its dedicated review partial. It does not change `CreateCareRequestWizard.php`, submission validation or publish business paths. Person/help, schedule, address/notes and optional contact inputs remain mounted; saved-data reuse actions remain available; recurring end boundaries and the first planned visit are now explicit in review. Fields that feed the review update on blur/change without replacing their bound properties.

Read-only inspection of the `ManageCareRequest.php` diff found presentation lifecycle/tab changes, URL return context, caregiver eager loading, decision-review state/wrappers and the intended recurring-hire redirect. Existing one-time hiring transaction, payment authorization/capture, shortlist/chat, cancellation, invitation and support handlers were not replaced. The new confirmation methods delegate to the existing `hire`/`completeBooking` methods. The successful compatibility run exercises authorization failure and timesheet capture through those wrappers.

Representative desktop/mobile one-time and recurring creation captures were inspected. The desktop review column and mobile stack retain price, schedule, recipient and visibility details; the capture owner reported zero horizontal overflow at 1440 and 390 pixels. The observed floating support overlap was addressed with an inline support action and shared workspace handling; the final full capture pass verifies the completed styling and support behavior separately.

## Additional guided-assistance compatibility

After the request overview and pending-timesheet semantic targets were restored, the bounded selection `AiSupport/FamilyGuidedAssistanceTest.php` plus `AiSupport/FamilyGuidedAssistanceStateMatrixTest.php` passed **19 tests / 195 assertions** in 28.09 seconds. [Execution log](guided-assistance-compatibility-tests.log). No app or test changes were needed for this validation run; the wider AI suite was not run.

These checks cover current-layout navigation, authoritative account attention, authorized resource-specific timesheet routes and cross-family rejection, caregiver visit changes, empty and populated recurring care guidance, and registered page targets. Source inspection also confirms `family.request.overview` wraps Care details, `family.request.timesheet` exists before opening the decision review, and applicant/visit/payment targets remain available. The inline support actions dispatch `open-care-support-chat`; the support widget listens for that event. Browser interaction and visual focus behavior remain part of the separate browser validation.

Across the four disjoint selections documented here, the result is **159 tests / 1,145 assertions passed**.
