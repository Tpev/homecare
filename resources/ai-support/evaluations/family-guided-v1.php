<?php

$case = static fn (
    string $id,
    int $batch,
    string $domain,
    string $handler,
    array $phrases,
    string $runtimeEvidence,
): array => [
    'intent_id' => $id,
    'batch' => $batch,
    'domain' => $domain,
    'handler' => $handler,
    'phrases' => $phrases,
    'runtime_evidence' => $runtimeEvidence,
];

return [
    'version' => 'family-guided-evals-v1',
    'frozen_on' => '2026-08-27',
    'cases' => [
        $case('FAM-PAY-002', 1, 'payments', 'family_payment_method', [
            'Who is allowed to change the saved payment method?',
            'Can I update the card as a Family member?',
            'Can a Family member replace the credit card?',
        ], 'GuidedPaymentMethodTest::test_family_member_gets_safe_card_details_and_the_update_destination'),
        $case('FAM-PAY-003', 1, 'payments', 'family_payment_method', [
            'Do I have a card on file?',
            'Which credit card is currently saved?',
            'Is there a payment method on file?',
        ], 'GuidedPaymentMethodTest::test_owner_can_ask_which_card_is_on_file_without_sending_account_state_to_the_model'),
        $case('FAM-PAY-004', 1, 'payments', 'family_payment_method', [
            'Help me add my first payment method.',
            'I need to add a credit card.',
            'Where can I add a new card?',
        ], 'GuidedPaymentMethodTest::test_owner_card_intent_reads_missing_state_and_offers_guidance_without_a_model_call'),
        $case('FAM-PAY-005', 1, 'payments', 'family_payment_method', [
            'Help me change the payment method.',
            'I want to replace my card on file.',
            'Hi, I want to use another credit card.',
            'Where do I update my saved credit card?',
        ], 'GuidedPaymentMethodTest::test_owner_existing_card_is_read_safely_and_gets_the_update_action'),
        $case('FAM-PAY-006', 1, 'payments', 'family_payment_method', [
            'My payment method is expired.',
            'The card on file is expiring soon.',
            'Help me update an expired credit card.',
        ], 'GuidedPaymentMethodTest::test_existing_stripe_checkout_flow_verifies_completion_before_success_message'),
        $case('FAM-PAY-008', 1, 'payments', 'family_payment_method', [
            'Why can a Family member change the card?',
            'Can I replace the shared saved payment method?',
            'Can someone other than the owner update the credit card?',
        ], 'GuidedPaymentMethodTest::test_family_member_gets_safe_card_details_and_the_update_destination'),

        $case('FAM-START-017', 2, 'orientation', 'family_overview', [
            'What needs my attention?',
            'Please check my account for pending actions.',
            'What should I do next in my Family account?',
        ], 'FamilyGuidedAssistanceTest::test_attention_overview_reads_authoritative_account_data_and_offers_exact_actions_without_model_call'),
        $case('FAM-START-003', 1, 'orientation', 'family_overview', [
            'What does the Family Care overview show?',
            'Where is my Family home page?',
            'Explain the Care overview.',
        ], 'FamilyExperienceKnowledgeAlignmentTest::test_current_family_pages_render_their_registered_highlight_markers'),
        $case('FAM-START-004', 1, 'orientation', 'family_overview', [
            'Open my Care overview.',
            'Take me to my Family home page.',
            'Where is my Care page?',
        ], 'FamilyExperienceKnowledgeAlignmentTest::test_current_family_pages_render_their_registered_highlight_markers'),

        $case('FAM-PROFILE-002', 2, 'profiles', 'family_care_profiles', [
            'Show my care receiver profiles.',
            'Is the care recipient profile ready?',
            'Open the care profile area for me.',
        ], 'FamilyGuidedAssistanceTest::test_profile_messages_history_and_empty_visit_answers_remain_read_only_and_guided'),
        $case('FAM-PROFILE-003', 2, 'profiles', 'family_care_profiles', [
            'Help me create a care receiver profile.',
            'Where do I add a care recipient profile?',
            'Open a new care profile for me.',
        ], 'FamilyGuidedAssistanceTest::test_batch_two_pages_expose_only_registered_semantic_targets'),
        $case('FAM-PROFILE-004', 2, 'profiles', 'family_care_profiles', [
            'Is my care receiver profile still a draft?',
            'What is missing from the recipient profile?',
            'Help me finish the care profile I saved.',
        ], 'FamilyGuidedAssistanceTest::test_attention_overview_reads_authoritative_account_data_and_offers_exact_actions_without_model_call'),

        $case('FAM-REQUEST-034', 2, 'requests', 'family_requests', [
            'What is the status of my request?',
            'What is the status of my care request?',
            'Show my open care requests.',
            'Where does my care request stand?',
        ], 'FamilyGuidedAssistanceStateMatrixTest::test_request_and_applicant_states_return_authorized_exact_destinations'),
        $case('FAM-REQUEST-035', 2, 'requests', 'family_requests', [
            'Did any caregivers apply to my request?',
            'How many caregiver responses do I have?',
            'Show the applications for my care request.',
        ], 'FamilyGuidedAssistanceStateMatrixTest::test_request_and_applicant_states_return_authorized_exact_destinations'),
        $case('FAM-MATCH-013', 2, 'matching', 'family_requests', [
            'Show the caregivers who applied.',
            'Did a caregiver respond to my request?',
            'Are any applicants waiting for my review?',
        ], 'FamilyGuidedAssistanceStateMatrixTest::test_request_and_applicant_states_return_authorized_exact_destinations'),
        $case('FAM-MATCH-017', 2, 'matching', 'family_messages', [
            'Open my conversation with the caregiver.',
            'Find the caregiver reply in my inbox.',
            'Show my unread caregiver message.',
        ], 'FamilyGuidedAssistanceStateMatrixTest::test_profile_message_and_history_positive_states_are_read_only_and_exactly_guided'),

        $case('FAM-PAY-012', 2, 'payments', 'family_payment_attention', [
            'Is a care payment pending?',
            'Does any payment need my attention?',
            'Show the pending charge action.',
        ], 'FamilyGuidedAssistanceStateMatrixTest::test_care_payment_failure_is_read_without_exposing_provider_details_or_mutating_payment'),
        $case('FAM-PAY-013', 2, 'payments', 'family_payment_attention', [
            'Why did the card authorization fail?',
            'Explain the failed care payment.',
            'My payment was declined. What do I do?',
        ], 'FamilyGuidedAssistanceStateMatrixTest::test_care_payment_failure_is_read_without_exposing_provider_details_or_mutating_payment'),
        $case('FAM-PAY-014', 2, 'payments', 'family_payment_attention', [
            'Where do I retry the failed card authorization?',
            'Help me fix the payment failure.',
            'Open the action required for my declined payment.',
        ], 'FamilyGuidedAssistanceStateMatrixTest::test_care_payment_failure_is_read_without_exposing_provider_details_or_mutating_payment'),
        $case('FAM-PAY-016', 2, 'payments', 'family_payment_attention', [
            'Why did payment capture fail after the visit?',
            'The care charge failed after I approved hours.',
            'Explain this failed visit payment.',
        ], 'FamilyGuidedAssistanceStateMatrixTest::test_care_payment_failure_is_read_without_exposing_provider_details_or_mutating_payment'),
        $case('FAM-PAY-017', 2, 'payments', 'family_payment_attention', [
            'The payment for my time correction failed.',
            'Help with a failed correction charge.',
            'Where is the payment issue for corrected hours?',
        ], 'FamilyGuidedAssistanceStateMatrixTest::test_time_correction_and_regular_care_attention_use_their_authorized_resources'),
        $case('FAM-PAY-018', 2, 'payments', 'family_payment_attention', [
            'The payment for the extra visit failed.',
            'Help with a declined extra-visit charge.',
            'Where is the payment problem for reported extra care?',
        ], 'FamilyGuidedAssistanceStateMatrixTest::test_time_correction_and_regular_care_attention_use_their_authorized_resources'),
        $case('FAM-PAY-019', 2, 'payments', 'family_care_history', [
            'Show my payment history.',
            'Open billing history for past care.',
            'Where can I find past charges?',
        ], 'FamilyGuidedAssistanceStateMatrixTest::test_profile_message_and_history_positive_states_are_read_only_and_exactly_guided'),
        $case('FAM-PAY-020', 4, 'payments', 'family_care_history', [
            'Show the authorized captured refunded and net paid amounts.',
            'What captured amount and refund are in my care history?',
            'Explain the net paid amount for my latest care payment.',
        ], 'FamilyGuidedAssistanceStateMatrixTest::test_payment_history_returns_exact_family_visible_amounts'),
        $case('FAM-PAY-021', 2, 'payments', 'family_payment_attention', [
            'Why did my care payment fail?',
            'Explain the failed charge for a visit.',
            'What does this payment error mean?',
        ], 'FamilyGuidedAssistanceStateMatrixTest::test_care_payment_failure_is_read_without_exposing_provider_details_or_mutating_payment'),
        $case('FAM-PAY-023', 4, 'payments', 'family_care_history', [
            'What is the refund status and amount?',
            'Show the refunded amount for my latest care payment.',
            'How much of the care charge was refunded?',
        ], 'FamilyGuidedAssistanceStateMatrixTest::test_payment_history_returns_exact_family_visible_amounts'),
        $case('FAM-PAY-026', 4, 'payments', 'family_care_history', [
            'Where is my receipt for the care payment?',
            'Open the receipt for my latest care visit.',
            'Show my care payment receipt.',
        ], 'FamilyGuidedAssistanceStateMatrixTest::test_payment_history_returns_exact_family_visible_amounts'),
        $case('FAM-PAY-029', 4, 'payments', 'family_pricing', [
            'How much does care cost per hour?',
            'What would 2.5 hours cost?',
            'What is the Family price and caregiver rate?',
        ], 'InteractiveSupportRuntimeTest::test_published_pricing_kb_uses_exact_family_and_caregiver_math_without_provider_calls'),

        $case('FAM-VISIT-001', 2, 'visits', 'family_visits', [
            'When is my next visit?',
            'Show my upcoming caregiver visit.',
            'Do I have care scheduled soon?',
        ], 'FamilyGuidedAssistanceStateMatrixTest::test_scheduled_live_and_submitted_hours_states_remain_read_only'),
        $case('FAM-VISIT-003', 2, 'visits', 'family_visits', [
            'What is the current visit status?',
            'Is the caregiver visit happening now?',
            "Show today's scheduled visit.",
        ], 'FamilyGuidedAssistanceStateMatrixTest::test_scheduled_live_and_submitted_hours_states_remain_read_only'),
        $case('FAM-VISIT-009', 2, 'visits', 'family_visits', [
            "Review the caregiver's visit change request.",
            'Show the proposed reschedule.',
            'Is there a caregiver cancellation request?',
        ], 'FamilyGuidedAssistanceTest::test_caregiver_visit_change_is_reported_as_an_attention_item_and_guides_to_the_exact_decision'),
        $case('FAM-VISIT-010', 2, 'visits', 'family_visits', [
            'Help me accept the caregiver change request.',
            'Where do I approve the visit reschedule?',
            'Open the decision for the proposed visit change.',
        ], 'FamilyGuidedAssistanceTest::test_caregiver_visit_change_is_reported_as_an_attention_item_and_guides_to_the_exact_decision'),
        $case('FAM-VISIT-011', 2, 'visits', 'family_visits', [
            'Help me reject the caregiver change request.',
            'Where do I decline the visit reschedule?',
            'Open the cancellation decision for this visit.',
        ], 'FamilyGuidedAssistanceTest::test_caregiver_visit_change_is_reported_as_an_attention_item_and_guides_to_the_exact_decision'),
        $case('FAM-VISIT-018', 2, 'visits', 'family_timesheets', [
            'What are submitted hours?',
            'Do I have a timesheet to review?',
            'Show the caregiver reported hours.',
        ], 'FamilyGuidedAssistanceTest::test_submitted_hours_guide_uses_authorized_resource_route_and_rejects_another_family_record'),
        $case('FAM-VISIT-019', 4, 'visits', 'family_timesheets', [
            'Show the submitted hours start end and duration.',
            'Review the tasks and notes with the caregiver submitted hours.',
            'What exact time and duration did the caregiver submit?',
        ], 'FamilyGuidedAssistanceStateMatrixTest::test_payment_failure_reason_is_normalized_and_submitted_hours_include_authoritative_differences'),
        $case('FAM-VISIT-020', 2, 'visits', 'family_timesheets', [
            'Help me approve the submitted hours.',
            'Where do I review the caregiver timesheet?',
            'Open the worked hours awaiting approval.',
        ], 'FamilyGuidedAssistanceTest::test_submitted_hours_guide_uses_authorized_resource_route_and_rejects_another_family_record'),
        $case('FAM-VISIT-023', 2, 'visits', 'family_timesheets', [
            'Review the caregiver submitted time correction.',
            'Show the corrected worked hours.',
            'Where do I review a time correction?',
        ], 'FamilyGuidedAssistanceStateMatrixTest::test_time_correction_and_regular_care_attention_use_their_authorized_resources'),
        $case('FAM-VISIT-026', 2, 'visits', 'family_timesheets', [
            'Continue payment for my time correction.',
            'Open the time correction payment step.',
            'The corrected hours need payment action.',
        ], 'FamilyGuidedAssistanceStateMatrixTest::test_time_correction_and_regular_care_attention_use_their_authorized_resources'),

        $case('FAM-REGULAR-001', 2, 'regular_care', 'family_overview', [
            'What regular care needs my attention?',
            'Check my account for a pending regular-care action.',
            'Is everything okay with my regular care?',
        ], 'FamilyGuidedAssistanceTest::test_regular_care_attention_guides_to_the_plan_instead_of_its_system_request'),
        $case('FAM-REGULAR-009', 2, 'regular_care', 'family_regular_care', [
            'When is my next regular care visit?',
            'Show the upcoming visit for regular care.',
            'Is my next regular care visit scheduled?',
        ], 'FamilyGuidedAssistanceStateMatrixTest::test_time_correction_and_regular_care_attention_use_their_authorized_resources'),
        $case('FAM-REGULAR-013', 2, 'regular_care', 'family_timesheets', [
            "Review the caregiver's reported hours for an extra visit.",
            'Show the completed extra visit report.',
            'Where do I review reported hours for extra care?',
        ], 'FamilyGuidedAssistanceStateMatrixTest::test_time_correction_and_regular_care_attention_use_their_authorized_resources'),
        $case('FAM-REGULAR-017', 2, 'regular_care', 'family_payment_attention', [
            'Why did payment for the completed extra visit fail?',
            'Help fix the failed regular-care charge.',
            'Open the payment issue for an extra visit.',
        ], 'FamilyGuidedAssistanceStateMatrixTest::test_time_correction_and_regular_care_attention_use_their_authorized_resources'),
        $case('FAM-REGULAR-024', 2, 'regular_care', 'family_care_history', [
            'Show regular care history and payments.',
            'Open past visits for my regular care.',
            'Where are my previous regular-care charges?',
        ], 'FamilyGuidedAssistanceStateMatrixTest::test_profile_message_and_history_positive_states_are_read_only_and_exactly_guided'),

        $case('FAM-COMMS-001', 2, 'communications', 'family_messages', [
            'Open the Family message inbox.',
            'Show my caregiver messages.',
            'Take me to my inbox.',
        ], 'FamilyGuidedAssistanceTest::test_batch_two_pages_expose_only_registered_semantic_targets'),
        $case('FAM-COMMS-002', 2, 'communications', 'family_messages', [
            'Find the conversation for my care request.',
            'Show the newest caregiver reply.',
            'Open my unread caregiver conversation.',
        ], 'FamilyGuidedAssistanceStateMatrixTest::test_profile_message_and_history_positive_states_are_read_only_and_exactly_guided'),

        $case('FAM-HISTORY-001', 2, 'history', 'family_care_history', [
            'Open Care history.',
            'Show my past visits.',
            'Where are my care receipts?',
        ], 'FamilyGuidedAssistanceTest::test_batch_two_pages_expose_only_registered_semantic_targets'),
        $case('FAM-HISTORY-004', 2, 'history', 'family_care_history', [
            'Explain my Care history totals.',
            'How many past visits are in my history?',
            'Show the summary of previous visits.',
        ], 'FamilyGuidedAssistanceStateMatrixTest::test_profile_message_and_history_positive_states_are_read_only_and_exactly_guided'),
    ],
    'negative_cases' => [
        ['id' => 'NEG-REQUEST-CREATION', 'message' => 'I want to create a one-time care request.'],
        ['id' => 'NEG-PASSWORD', 'message' => 'Help me change my password.'],
        ['id' => 'NEG-REFUND', 'message' => 'Refund the last visit charge.'],
        ['id' => 'NEG-INVITATION', 'message' => 'Invite my sister to the Family account.'],
        ['id' => 'NEG-DELETE', 'message' => 'Delete my account.'],
        ['id' => 'NEG-BROWSE', 'message' => 'Help me browse caregivers near me.'],
        ['id' => 'NEG-MEDICAL', 'message' => 'Can you change my medication dosage?'],
        ['id' => 'NEG-TRANSFER', 'message' => 'I want to talk to a person.'],
        ['id' => 'NEG-GENERAL', 'message' => 'What does LoLo do?'],
        ['id' => 'NEG-NOTIFICATIONS', 'message' => 'Change my notification settings.'],
    ],
];
