<?php

return [
    'version' => 'family-goal-journeys-v1',
    'journeys' => [
        'care_request' => [
            'label' => 'Choose care and create a request',
            'default_step' => 'choose_care_type',
            'progress_total' => 4,
            'domains' => ['care_requests'],
        ],
        'care_profile' => [
            'label' => 'Complete a care profile',
            'default_step' => 'review_profile',
            'progress_total' => 3,
            'domains' => ['care_profiles'],
        ],
        'payment_method' => [
            'label' => 'Add or change a payment method',
            'default_step' => 'review_payment_method',
            'progress_total' => 3,
            'domains' => [],
        ],
        'payment_failure' => [
            'label' => 'Fix a payment problem',
            'default_step' => 'read_payment_problem',
            'progress_total' => 3,
            'domains' => ['payments'],
        ],
        'applicant_hiring' => [
            'label' => 'Review caregivers and hire',
            'default_step' => 'review_caregivers',
            'progress_total' => 3,
            'domains' => ['matching_hiring'],
        ],
        'visit_hours' => [
            'label' => 'Manage a visit or hours',
            'default_step' => 'review_visit',
            'progress_total' => 3,
            'domains' => ['visits_timesheets'],
        ],
        'regular_care' => [
            'label' => 'Manage regular care',
            'default_step' => 'review_regular_care',
            'progress_total' => 3,
            'domains' => ['regular_care'],
        ],
        'history_rebooking' => [
            'label' => 'Find past care or book again',
            'default_step' => 'find_past_care',
            'progress_total' => 3,
            'domains' => ['care_history'],
        ],
        'messages_notifications' => [
            'label' => 'Manage messages and notifications',
            'default_step' => 'open_messages',
            'progress_total' => 2,
            'domains' => ['communications'],
        ],
        'human_help' => [
            'label' => 'Talk to a person',
            'default_step' => 'transfer_to_person',
            'progress_total' => 1,
            'domains' => [],
        ],
    ],
];
