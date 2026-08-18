<?php

$case = static fn (string $intentId, array $phrases, string $evidence): array => [
    'intent_id' => $intentId,
    'batch' => 5,
    'domain' => str_contains($intentId, 'PROFILE') ? 'profiles' : 'requests',
    'phrases' => $phrases,
    'runtime_evidence' => $evidence,
];

return [
    'version' => 'family-lifecycle-evals-v1',
    'frozen_on' => '2026-08-18',
    'cases' => [
        $case('FAM-PROFILE-003', ['Create a care receiver profile for Maria.', 'Make a new care recipient profile for John.', 'Help me create a profile for Rose.'], 'Batch5FamilyLifecycleTest::test_profile_is_collected_recapped_confirmed_saved_and_verified_without_provider'),
        $case('FAM-PROFILE-005', ['Mark the care profile ready.', 'Make Maria’s profile ready for requests.', 'The recipient profile is complete and ready.'], 'Batch5FamilyLifecycleTest::test_profile_can_be_made_ready_only_after_exact_readiness_and_is_idempotent'),
        $case('FAM-PROFILE-007', ['Change the preferred name on the profile.', 'Update her date of birth in the profile.', 'Correct the pronouns in this profile.'], 'Batch5FamilyLifecycleTest::test_profile_is_collected_recapped_confirmed_saved_and_verified_without_provider'),
        $case('FAM-PROFILE-008', ['Update the profile description.', 'Change her interests and comfort notes.', 'Edit the good visit notes in the profile.'], 'Batch5FamilyLifecycleTest::test_profile_is_collected_recapped_confirmed_saved_and_verified_without_provider'),
        $case('FAM-PROFILE-009', ['Update the communication notes in the profile.', 'Change how caregivers should communicate with her.', 'Edit the profile communication preferences.'], 'Batch5FamilyLifecycleTest::test_profile_is_collected_recapped_confirmed_saved_and_verified_without_provider'),
        $case('FAM-PROFILE-010', ['Update the non-medical health context in the profile.', 'Change the everyday memory context.', 'Edit her everyday health notes for caregivers.'], 'Batch5FamilyLifecycleTest::test_profile_is_collected_recapped_confirmed_saved_and_verified_without_provider'),
        $case('FAM-PROFILE-011', ['Update her mobility information.', 'Change the profile to say she uses a walker.', 'Correct the mobility notes.'], 'Batch5FamilyLifecycleTest::test_profile_is_collected_recapped_confirmed_saved_and_verified_without_provider'),
        $case('FAM-PROFILE-012', ['Update her routine in the profile.', 'Change the food and allergy notes.', 'Edit the overnight preferences.'], 'Batch5FamilyLifecycleTest::test_profile_is_collected_recapped_confirmed_saved_and_verified_without_provider'),
        $case('FAM-PROFILE-013', ['Update the profile safety notes.', 'Change the caregiver quality preferences.', 'Edit what the caregiver should avoid.'], 'Batch5FamilyLifecycleTest::test_profile_is_collected_recapped_confirmed_saved_and_verified_without_provider'),
        $case('FAM-PROFILE-014', ['Add an additional contact to the profile.', 'Change the extra contact phone number.', 'Update the profile emergency contact.'], 'Batch5FamilyLifecycleTest::test_profile_is_collected_recapped_confirmed_saved_and_verified_without_provider'),
        $case('FAM-PROFILE-019', ['Make Maria the default care profile.', 'Change the default care receiver profile.', 'Use this profile by default.'], 'Batch5FamilyLifecycleTest::test_stale_profile_confirmation_is_denied_and_does_not_archive'),
        $case('FAM-PROFILE-020', ['Archive Maria’s profile.', 'Please archive this care receiver profile.', 'Hide this profile from new requests.'], 'Batch5FamilyLifecycleTest::test_stale_profile_confirmation_is_denied_and_does_not_archive'),
        $case('FAM-PROFILE-021', ['Restore Maria’s archived profile.', 'Bring back the archived care profile.', 'Restore this care receiver profile.'], 'Batch5FamilyLifecycleTest::test_stale_profile_confirmation_is_denied_and_does_not_archive'),
        $case('FAM-REQUEST-020', ['Reuse my last care request.', 'Start with the same request as last time.', 'Reuse the previous request details.'], 'Batch5FamilyLifecycleTest::test_expired_request_creates_fresh_private_copy_clears_schedule_and_keeps_original'),
        $case('FAM-REQUEST-034', ['What is the status of my care request?', 'Where does request #12 stand?', 'Check my current request status.'], 'Batch5FamilyLifecycleTest::test_open_request_withdrawal_has_recap_confirmation_receipt_and_authoritative_status'),
        $case('FAM-REQUEST-035', ['Did any caregivers apply to my request?', 'How many caregiver responses are on my request?', 'Show the applicants for my care request.'], 'Batch5FamilyLifecycleTest::test_open_request_withdrawal_has_recap_confirmation_receipt_and_authoritative_status'),
        $case('FAM-REQUEST-036', ['Edit my live care request.', 'Change the date on the open request.', 'Update the tasks on my published request.'], 'Batch5FamilyLifecycleTest::test_expired_request_creates_fresh_private_copy_clears_schedule_and_keeps_original'),
        $case('FAM-REQUEST-037', ['Change my live one-time request to regular care.', 'Turn this regular request into one-time care.', 'Change the type of my published request.'], 'Batch5FamilyLifecycleTest::test_expired_request_creates_fresh_private_copy_clears_schedule_and_keeps_original'),
        $case('FAM-REQUEST-038', ['Withdraw my open care request.', 'Cancel request #12.', 'Take down my published request.'], 'Batch5FamilyLifecycleTest::test_open_request_withdrawal_has_recap_confirmation_receipt_and_authoritative_status'),
        $case('FAM-REQUEST-039', ['Make a fresh copy of my expired request.', 'Restore my withdrawn request as a new one.', 'Reopen the cancelled request by copying it.'], 'Batch5FamilyLifecycleTest::test_expired_request_creates_fresh_private_copy_clears_schedule_and_keeps_original'),
        $case('FAM-REQUEST-040', ['Duplicate my care request.', 'Copy request #12 into a new draft.', 'Make another request from this one.'], 'Batch5FamilyLifecycleTest::test_expired_request_creates_fresh_private_copy_clears_schedule_and_keeps_original'),
    ],
];
