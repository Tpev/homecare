<?php

declare(strict_types=1);

/**
 * Compile the human-readable 324-row Family registry into the executable catalog.
 *
 * Run from the repository root:
 * php tools/generate_family_intent_catalog.php
 */
$root = dirname(__DIR__);
$registryPath = $root.'/docs/product/support-agent/38-family-intent-action-coverage-registry.md';
$outputPath = $root.'/resources/ai-support/intents/family-v1.php';
$guidedPath = $root.'/resources/ai-support/evaluations/family-guided-v1.php';
$operationsPath = $root.'/resources/ai-support/knowledge-base/family-operations-v1.php';
$paymentTimePath = $root.'/resources/ai-support/knowledge-base/payment-time-v1.php';
$profileRequestPath = $root.'/resources/ai-support/knowledge-base/profile-request-v1.php';
$marketplaceCarePath = $root.'/resources/ai-support/knowledge-base/marketplace-care-v1.php';

$markdown = file_get_contents($registryPath);
if ($markdown === false) {
    throw new RuntimeException('Unable to read the Family intent registry.');
}

$guided = require $guidedPath;
$operations = require $operationsPath;
$paymentTime = require $paymentTimePath;
$profileRequest = require $profileRequestPath;
$marketplaceCare = require $marketplaceCarePath;

$guidedByIntent = [];
foreach ((array) ($guided['cases'] ?? []) as $case) {
    $guidedByIntent[(string) $case['intent_id']] = (string) $case['handler'];
}

$knowledgeByIntent = [];
$targetsByIntent = [];
foreach (array_merge(
    (array) ($operations['entries'] ?? []),
    (array) ($operations['revisions'] ?? []),
    (array) ($paymentTime['entries'] ?? []),
    (array) ($profileRequest['entries'] ?? []),
    (array) ($marketplaceCare['entries'] ?? []),
) as $definition) {
    foreach ((array) ($definition['intent_ids'] ?? []) as $intentId) {
        $knowledgeByIntent[$intentId][] = (string) $definition['stable_id'];
        $targetsByIntent[$intentId] = array_values(array_unique(array_merge(
            $targetsByIntent[$intentId] ?? [],
            (array) ($definition['route_target_ids'] ?? []),
        )));
    }
}

$domainNames = [
    'START' => 'orientation',
    'ACCOUNT' => 'account_security',
    'ACCESS' => 'family_access',
    'PROFILE' => 'care_profiles',
    'REQUEST' => 'care_requests',
    'MATCH' => 'matching_hiring',
    'PAY' => 'payments',
    'VISIT' => 'visits_timesheets',
    'REGULAR' => 'regular_care',
    'COVERAGE' => 'continuous_coverage',
    'COMMS' => 'communications',
    'HISTORY' => 'care_history',
    'SUPPORT' => 'support_privacy',
];

$handlerContracts = [
    'family_payment_method' => ['family_payment_method_status_v1', 'family.billing.payment_method', 'family_payment_method_v1', 'family_payment_method_v1'],
    'family_pricing' => [null, null, null, null],
    'family_overview' => ['family_attention_v1', 'family.dashboard', 'family_attention_v1', 'authoritative_family_state_v1'],
    'family_requests' => ['family_requests_v1', 'family.care_requests', 'family_request_v1', 'authoritative_family_state_v1'],
    'family_visits' => ['family_visits_v1', 'family.care_requests', 'family_visit_v1', 'authoritative_family_state_v1'],
    'family_timesheets' => ['family_payment_time_v1', 'family.care_requests', 'family_timesheet_v1', 'authoritative_family_state_v1'],
    'family_payment_attention' => ['family_payment_time_v1', 'family.care_requests', 'family_payment_attention_v1', 'authoritative_family_state_v1'],
    'family_care_profiles' => ['family_care_profiles_v1', 'family.care_profiles', 'family_care_profile_v1', 'authoritative_family_state_v1'],
    'family_messages' => ['family_messages_v1', 'family.messages', 'family_message_v1', 'authoritative_family_state_v1'],
    'family_care_history' => ['family_care_history_v1', 'family.care_history', 'family_history_v1', 'authoritative_family_state_v1'],
    'family_regular_care' => ['family_regular_care_v1', 'family.regular_care', 'family_regular_care_v1', 'authoritative_family_state_v1'],
];

$preparationByIntent = [
    'FAM-PROFILE-003' => 'care_profile_v1', 'FAM-PROFILE-004' => 'care_profile_v1',
    'FAM-PROFILE-005' => 'care_profile_v1', 'FAM-PROFILE-007' => 'care_profile_v1',
    'FAM-PROFILE-008' => 'care_profile_v1', 'FAM-PROFILE-009' => 'care_profile_v1',
    'FAM-PROFILE-010' => 'care_profile_v1', 'FAM-PROFILE-011' => 'care_profile_v1',
    'FAM-PROFILE-012' => 'care_profile_v1', 'FAM-PROFILE-013' => 'care_profile_v1',
    'FAM-PROFILE-014' => 'care_profile_v1', 'FAM-PROFILE-018' => 'care_profile_v1',
    'FAM-REQUEST-006' => 'care_profile_v1',
    'FAM-REQUEST-020' => 'care_request_reuse_v1', 'FAM-REQUEST-036' => 'care_request_reuse_v1',
    'FAM-REQUEST-037' => 'care_request_reuse_v1', 'FAM-REQUEST-039' => 'care_request_reuse_v1',
    'FAM-REQUEST-040' => 'care_request_reuse_v1', 'FAM-REGULAR-004' => 'care_request_reuse_v1',
    'FAM-HISTORY-013' => 'care_request_reuse_v1',
    'FAM-MATCH-008' => 'caregiver_message_v1', 'FAM-MATCH-018' => 'caregiver_message_v1',
    'FAM-VISIT-004' => 'caregiver_message_v1', 'FAM-REGULAR-005' => 'caregiver_message_v1',
    'FAM-REGULAR-025' => 'caregiver_message_v1', 'FAM-COMMS-003' => 'caregiver_message_v1',
    'FAM-VISIT-021' => 'submitted_hours_correction_v1', 'FAM-VISIT-022' => 'submitted_hours_correction_v1',
    'FAM-VISIT-025' => 'submitted_hours_correction_v1', 'FAM-VISIT-028' => 'submitted_hours_correction_v1',
    'FAM-PAY-024' => 'submitted_hours_correction_v1', 'FAM-REGULAR-016' => 'submitted_hours_correction_v1',
    'FAM-PAY-022' => 'support_intake_v1', 'FAM-PAY-025' => 'support_intake_v1',
    'FAM-PAY-027' => 'support_intake_v1', 'FAM-PAY-031' => 'support_intake_v1',
    'FAM-SUPPORT-004' => 'support_intake_v1', 'FAM-SUPPORT-008' => 'support_intake_v1',
    'FAM-SUPPORT-009' => 'support_intake_v1', 'FAM-SUPPORT-010' => 'support_intake_v1',
    'FAM-SUPPORT-011' => 'support_intake_v1', 'FAM-SUPPORT-012' => 'support_intake_v1',
    'FAM-SUPPORT-015' => 'support_intake_v1',
];

$rows = [];
preg_match_all('/^\| (FAM-([A-Z]+)-\d{3}) \| (.*?) \| (.*?) \| (.*?) \| (.*?) \| (.*?) \|$/m', $markdown, $matches, PREG_SET_ORDER);
foreach ($matches as $match) {
    [$all, $intentId, $domainCode, $intent, $product, $explain, $action, $target] = $match;
    $intent = trim(str_replace(['**', '`'], '', $intent));
    $domain = $domainNames[$domainCode] ?? strtolower($domainCode);
    $normalIntent = rtrim($intent, '.');
    $lowerIntent = lcfirst($normalIntent);
    $imperfect = str_ireplace(
        ['understand', 'payment', 'caregiver', 'request', 'profile', 'message', 'visit', 'support', 'account'],
        ['undrstand', 'paymnt', 'caregivr', 'requst', 'profl', 'mesage', 'visti', 'suport', 'acount'],
        $lowerIntent,
    );

    $currentStages = ['Understand'];
    if (! in_array(trim($explain), ['No', 'Human'], true)) {
        $currentStages[] = 'Explain';
    }
    if (stripos($action, 'Read') !== false) {
        $currentStages[] = 'Read';
    }
    if (stripos($action, 'Navigate') !== false || stripos($action, 'Guide') !== false) {
        $currentStages[] = 'Navigate';
    }
    if (stripos($action, 'Guide') !== false) {
        $currentStages[] = 'Guide';
    }
    if (stripos($action, 'Draft') !== false || isset($preparationByIntent[$intentId])) {
        $currentStages[] = 'Prepare';
    }
    if (trim($action) === 'Yes') {
        $currentStages[] = 'Execute';
        $currentStages[] = 'Verify';
    }
    if (stripos($action, 'Transfer') !== false || trim($explain) === 'Human' || stripos($product, 'Human') !== false) {
        $currentStages[] = 'Human';
    }

    $targetStages = $currentStages;
    foreach ([
        'explain' => 'Explain', 'read' => 'Read', 'navigate' => 'Navigate', 'guide' => 'Guide',
        'prepare' => 'Prepare', 'confirm' => 'Execute', 'execute' => 'Execute', 'verify' => 'Verify',
        'recover' => 'Recover', 'human' => 'Human', 'transfer' => 'Human',
    ] as $needle => $stage) {
        if (stripos($target, $needle) !== false) {
            $targetStages[] = $stage;
        }
    }

    $handler = $guidedByIntent[$intentId] ?? null;
    [$reader, $handlerTarget, $guidedTask, $verifier] = $handler ? $handlerContracts[$handler] : [null, null, null, null];
    $destinations = array_values(array_unique(array_filter([
        $handlerTarget,
        ...($targetsByIntent[$intentId] ?? []),
    ])));

    if (str_starts_with($intentId, 'FAM-REQUEST-') && (stripos($action, 'Draft') !== false || trim($action) === 'Yes')) {
        $reader ??= 'family_request_context_v1';
        $verifier ??= in_array($intentId, ['FAM-REQUEST-028', 'FAM-REQUEST-029', 'FAM-REQUEST-030', 'FAM-REQUEST-043'], true)
            ? 'care_request_receipt_v1'
            : 'care_request_draft_state_v1';
    }
    if (str_starts_with($intentId, 'FAM-MATCH-')) {
        $reader ??= 'family_matching_state_v1';
        $verifier ??= 'authoritative_family_state_v1';
    }
    if (str_starts_with($intentId, 'FAM-VISIT-')) {
        $reader ??= 'family_visit_state_v1';
        $verifier ??= 'authoritative_family_state_v1';
    }
    if (str_starts_with($intentId, 'FAM-REGULAR-')) {
        $reader ??= 'family_regular_care_v1';
        $verifier ??= 'authoritative_family_state_v1';
    }

    $tool = match ($intentId) {
        'FAM-PROFILE-003', 'FAM-PROFILE-004', 'FAM-PROFILE-007', 'FAM-PROFILE-008',
        'FAM-PROFILE-009', 'FAM-PROFILE-010', 'FAM-PROFILE-011', 'FAM-PROFILE-012',
        'FAM-PROFILE-013', 'FAM-PROFILE-014', 'FAM-PROFILE-018', 'FAM-REQUEST-006' => 'family-profile.save-draft:v1',
        'FAM-PROFILE-005' => 'family-profile.make-ready:v1',
        'FAM-PROFILE-019' => 'family-profile.make-default:v1',
        'FAM-PROFILE-020' => 'family-profile.archive:v1',
        'FAM-PROFILE-021' => 'family-profile.restore:v1',
        'FAM-REQUEST-024' => 'care_request_draft_discard_v1',
        'FAM-REQUEST-028' => 'care-request.publish.one-time:v1',
        'FAM-REQUEST-029' => 'care-request.publish.recurring:v1',
        'FAM-REQUEST-030' => 'care_request_recap_renew_v1',
        'FAM-REQUEST-038' => 'care-request.withdraw:v1',
        'FAM-MATCH-008', 'FAM-MATCH-009', 'FAM-MATCH-010' => 'caregiver.invite:v1',
        'FAM-MATCH-012' => 'caregiver.invitation.cancel:v1',
        'FAM-MATCH-015' => 'applicant.shortlist:v1',
        'FAM-MATCH-016' => 'applicant.reject:v1',
        'FAM-MATCH-017' => 'applicant.conversation:v1',
        'FAM-MATCH-018', 'FAM-VISIT-004', 'FAM-REGULAR-025' => 'caregiver.message:v1',
        'FAM-MATCH-020' => 'caregiver.hire:v1',
        'FAM-VISIT-005', 'FAM-VISIT-006' => 'visit.change-request:v1',
        'FAM-VISIT-007' => 'visit.cancel:v1',
        'FAM-VISIT-010', 'FAM-VISIT-011' => 'visit.change-request.resolve:v1',
        'FAM-VISIT-014' => 'visit.no-show:v1',
        'FAM-VISIT-017' => 'visit.complete:v1',
        'FAM-VISIT-020' => 'visit.hours.approve:v1',
        'FAM-VISIT-024' => 'visit.time-correction.approve:v1',
        'FAM-VISIT-022', 'FAM-VISIT-025' => 'visit.time-correction.request-changes:v1',
        'FAM-VISIT-030' => 'visit.review:v1',
        'FAM-VISIT-032' => 'visit.rebook:v1',
        'FAM-VISIT-033', 'FAM-REGULAR-002', 'FAM-REGULAR-003', 'FAM-REGULAR-004', 'FAM-REGULAR-005' => 'regular-care.offer:v1',
        'FAM-REGULAR-008' => 'regular-care.accept-counter:v1',
        'FAM-REGULAR-011' => 'regular-care.skip-visit:v1',
        'FAM-REGULAR-012' => 'regular-care.extra-visit:v1',
        'FAM-REGULAR-014' => 'regular-care.extra-visit.approve:v1',
        'FAM-REGULAR-015' => 'regular-care.extra-visit.request-changes:v1',
        'FAM-REGULAR-019' => 'regular-care.schedule-change:v1',
        'FAM-REGULAR-020' => 'regular-care.pause:v1',
        'FAM-REGULAR-021' => 'regular-care.resume:v1',
        'FAM-REGULAR-022', 'FAM-REGULAR-023' => 'regular-care.end:v1',
        default => null,
    };
    $prefill = $preparationByIntent[$intentId] ?? (stripos($action, 'Draft') !== false ? 'care_request_chat_draft_v1' : null);
    if ($prefill !== null) {
        $currentStages[] = 'Prepare';
        $targetStages[] = 'Prepare';
    }

    $human = in_array('Human', $currentStages, true) ? 'SUP-HANDOFF-001' : null;
    $unsupported = match (true) {
        str_contains($product, 'Restricted') => 'Deny the private or security-sensitive request without revealing protected data.',
        str_contains($product, 'Gap') => 'Say that LoLo does not currently support this action; do not invent behavior. Offer the valid UI alternative or a person.',
        $human !== null => 'Transfer the same conversation to a person and stop automation without a queue or timing promise.',
        trim($explain) === 'No' => 'Say that this exact help is not available in chat. Offer the registered application page or a person without claiming completion.',
        default => 'Use only the declared stages and current authorized state. Never infer or claim a write.',
    };

    $priority = match (true) {
        preg_match('/FAM-(START-(010|011|012|013|015)|SUPPORT-(010|015|016|017)|PAY-(024|025|028|029))$/', $intentId) === 1 => 'critical',
        $handler !== null || $prefill !== null || isset($knowledgeByIntent[$intentId]) => 'high',
        default => 'standard',
    };

    $rollout = match (true) {
        $handler !== null || $tool !== null || $prefill !== null || isset($knowledgeByIntent[$intentId]) => 'pilot',
        default => 'backlog',
    };

    $rows[] = [
        'intent_id' => $intentId,
        'domain' => $domain,
        'priority' => $priority,
        'roles' => ['family'],
        'membership_states' => ['active'],
        'intent' => $intent,
        'phrases' => [
            'ordinary' => [$normalIntent, 'I need help to '.$lowerIntent.'.', 'Can you help me '.$lowerIntent.'?'],
            'imperfect' => ['pls help me '.$imperfect],
            'follow_ups' => array_values(array_unique(array_filter([
                in_array('Navigate', $currentStages, true) ? 'take me there' : null,
                in_array('Guide', $currentStages, true) ? 'I cannot find it' : null,
                $verifier !== null ? 'I did it' : null,
                $human !== null ? 'talk to a person' : null,
            ]))),
        ],
        'capability_stages' => [
            'current' => array_values(array_unique($currentStages)),
            'target' => array_values(array_unique($targetStages)),
        ],
        'kb_stable_ids' => array_values(array_unique($knowledgeByIntent[$intentId] ?? [])),
        'contracts' => [
            'reader' => $reader,
            'destinations' => $destinations,
            'guided_task' => $guidedTask,
            'prefill' => $prefill,
            'tool' => $tool,
            'verifier' => $verifier,
            'human_transfer' => $human,
        ],
        'disposition' => [
            'product' => trim($product),
            'explain' => trim($explain),
            'action' => trim($action),
            'target_behavior' => trim($target),
            'unsupported_behavior' => $unsupported,
        ],
        'never_in_chat' => [
            'passwords', 'payment_card_data', 'cvc', 'bank_credentials', 'tokens',
            'verification_codes', 'identity_documents', 'provider_secrets', 'cross_account_data',
        ],
        'evaluation_ids' => [
            'EVAL-'.$intentId.'-ROUTING', 'EVAL-'.$intentId.'-COLLISION',
            'EVAL-'.$intentId.'-DENIED', 'EVAL-'.$intentId.'-UNAVAILABLE',
        ],
        'rollout_state' => $rollout,
    ];
}

if (count($rows) !== 324 || count(array_unique(array_column($rows, 'intent_id'))) !== 324) {
    throw new RuntimeException('Expected exactly 324 unique Family intent rows; found '.count($rows).'.');
}

$mapped = array_filter($rows, static fn (array $row): bool => $row['kb_stable_ids'] !== []);
if (count($mapped) !== 237) {
    throw new RuntimeException('Expected exactly 237 Batch 7 KB-mapped intents; found '.count($mapped).'.');
}

$manifest = [
    'version' => 'family-intents-v1',
    'generated_on' => '2026-08-18',
    'source' => 'docs/product/support-agent/38-family-intent-action-coverage-registry.md',
    'source_sha256' => hash('sha256', $markdown),
    'records' => $rows,
];

@mkdir(dirname($outputPath), 0777, true);
$php = "<?php\n\n// Generated by tools/generate_family_intent_catalog.php. Do not edit by hand.\n\nreturn ".var_export($manifest, true).";\n";
if (file_put_contents($outputPath, $php) === false) {
    throw new RuntimeException('Unable to write the executable Family intent catalog.');
}

fwrite(STDOUT, sprintf("Generated %d intents (%d explicitly KB-mapped).\n", count($rows), count($mapped)));
