<?php

// Explicit, paid provider evaluation using fictional evidence only.
// Run: php scripts/qa/evaluate-care-roster.php
// Reuses the existing OpenAI connection; never connects to the application DB.
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__, 2);
foreach ([
    'APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => sys_get_temp_dir().'/lolo-roster-eval-'.getmypid().'.php',
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array', 'BROADCAST_CONNECTION' => 'null',
] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:' || config('database.connections.sqlite.url')) {
    throw new RuntimeException('Evaluation requires an isolated in-memory database.');
}
\Illuminate\Support\Facades\Notification::fake();
\Illuminate\Support\Facades\Mail::fake();
\Illuminate\Support\Facades\Queue::fake();
\Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
$actor = \App\Models\User::factory()->create(['role' => 'admin']);
$customer = [
    'request' => ['id' => 900001, 'title' => 'Synthetic bathing assistance', 'scope_of_work' => 'Bathing and dressing; no lifting required.',
        'city' => 'Durham', 'state' => 'NC', 'zip' => '27704', 'request_type' => 'one_time',
        'requested_start_at' => '2026-12-04 15:00:00', 'requested_end_at' => '2026-12-04 17:00:00'],
    'customer_name' => 'Synthetic Family', 'records' => [], 'coverage' => [], 'timezone' => 'America/New_York',
];
$candidates = [];
foreach ([101, 102, 103, 104] as $id) {
    $candidates[$id] = ['caregiver_user_id' => $id, 'name' => 'Synthetic Caregiver '.$id, 'city' => 'Durham',
        'distance_miles_estimate' => 8, 'skills' => ['Personal care'], 'languages' => ['English'], 'experience_tags' => ['Bathing'],
        'operational' => ['action' => 'invite', 'blocked_reason' => null, 'invitation_status' => null,
            'application_status' => null, 'conflicts' => [], 'sms_allowed' => false],
        'records' => [['ref' => 'caregiver_profiles:'.$id, 'bio' => 'Self-reported experience assisting with bathing and dressing.',
            'years_experience' => 4, 'status' => 'active', 'identity_verification_status' => 'approved', 'updated_at' => '2026-11-28 10:00:00'],
            ['ref' => 'caregiver_availabilities:'.$id, 'day_of_week' => 5, 'start_time' => '14:00', 'end_time' => '18:00', 'updated_at' => '2026-10-01 10:00:00']],
        'coverage' => []];
}
$sms = static fn ($id, $body, $date, $direction = 'incoming') => ['ref' => 'sms_messages:'.$id,
    'direction' => $direction, 'body' => $body, 'created_at' => $date, 'status' => $direction === 'incoming' ? 'received' : 'delivered'];
$candidates[101]['records'][] = $sms(1001, 'Yes, I can do request 900001 in Durham on Friday Dec 4, 3-5pm. Bathing and dressing without lifting are fine; I have done this for years. Eight miles is fine for a two-hour visit. I have no other commitment then. Please send the invite.', '2026-11-28 10:30:00');
$candidates[102]['records'][] = $sms(2001, 'Yes, I can do Dec 4 afternoon in Durham.', '2026-11-26 10:00:00');
$candidates[102]['records'][] = ['ref' => 'caregiver_crm_activities:2002', 'type' => 'note', 'body' => 'Very promising for Friday. Expect acceptance.', 'occurred_at' => '2026-11-26 11:00:00'];
$candidates[102]['records'][] = $sms(2003, 'Correction: I cannot work Fridays anymore, including Dec 4. Only Mondays now. Please do not invite me to this Friday visit.', '2026-11-28 11:00:00');
$candidates[103]['records'][] = $sms(3001, 'Can you cover request 999999, Tuesday Dec 1 from 9am-noon in Chapel Hill?', '2026-11-27 10:00:00', 'outgoing');
$candidates[103]['records'][] = $sms(3002, 'Yes, that works, send it over.', '2026-11-27 10:05:00');
$candidates[103]['records'][] = ['ref' => 'caregiver_crm_activities:3003', 'type' => 'note', 'body' => 'Has not been contacted about Friday request 900001 yet. The yes was for Tuesday in Chapel Hill.', 'occurred_at' => '2026-11-28 09:00:00'];
$candidates[104]['records'][] = $sms(4001, 'For Durham I only accept visits of at least four hours. I cannot take request 900001 for two hours on Dec 4.', '2026-11-28 11:30:00');
$context = 'The family confirmed Friday Dec 4, 3-5pm in Durham, bathing and dressing without lifting. The day and two-hour duration are not flexible. Rank realistic outreach and respect the latest corrections.';

try {
    $ai = app(\App\Services\CustomerCare\CareRosterAi::class);
    $started = hrtime(true);
    $assessed = $ai->assess($actor, $customer, array_values($candidates), $context, 'soon');
    $rows = collect($assessed['assessments'])->keyBy('caregiver_user_id');
    $ranked = $ai->rank($actor, $customer, $assessed['assessments'], $context, 'soon', array_values($candidates));
    $ids = $ranked['result']['caregiver_user_ids'];
    $checks = [
        'current_confirmation_first' => ($ids[0] ?? null) === 101,
        'later_refusal_supersedes_yes_and_staff_optimism' => $rows[102]['fit'] === 'unsuitable' && ! in_array(102, $ids, true),
        'refusal_cited' => in_array('sms_messages:2003', $rows[102]['evidence_refs'], true),
        'other_job_yes_not_treated_as_current_confirmation' => $rows[103]['fit'] === 'possible' && count($rows[103]['confirm']) > 0,
        'incompatible_minimum_visit_excluded' => $rows[104]['fit'] === 'unsuitable' && ! in_array(104, $ids, true),
    ];
    echo json_encode(['passed' => ! in_array(false, $checks, true), 'settings' => $ai::settings(),
        'prompt_version' => $ai::VERSION, 'checks' => $checks, 'ranked_ids' => $ids,
        'elapsed_seconds' => round((hrtime(true) - $started) / 1e9, 1),
        'assessment_usage' => $assessed['usage'], 'ranking_usage' => $ranked['usage']], JSON_PRETTY_PRINT).PHP_EOL;
    exit(in_array(false, $checks, true) ? 1 : 0);
} catch (Throwable $exception) {
    // Never print provider request bodies, credentials or exception chains.
    fwrite(STDERR, 'Roster evaluation failed: '.(get_class($exception) === RuntimeException::class ? $exception->getMessage() : get_class($exception)).PHP_EOL);
    exit(1);
}
