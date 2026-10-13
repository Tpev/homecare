<?php

// Read only: the runner supplies a disposable copy, never the live local database.
$file = realpath($argv[1] ?? '');
$expectedDirectory = realpath(getcwd().'/.tmp/preprod-snapshot');
if (! $file || ! $expectedDirectory || dirname($file) !== $expectedDirectory) {
    throw new RuntimeException('Expected a database inside the isolated snapshot workspace.');
}
$db = new PDO('sqlite:'.$file, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('PRAGMA query_only=ON');
$familyId = $db->query("SELECT id FROM users WHERE email = 'maria.find-care-audit@example.com'")->fetchColumn();
$tables = ['care_requests', 'care_request_applications', 'care_request_invitations', 'care_request_recipients',
    'care_request_task', 'care_request_third_party_contacts', 'care_bookings', 'care_booking_payments',
    'care_booking_payment_attempts', 'care_booking_payment_operations', 'care_reviews', 'care_booking_change_requests',
    'care_booking_time_corrections', 'care_booking_corrections', 'care_booking_incidents', 'care_plans',
    'care_plan_events', 'care_plan_schedule_changes', 'completed_extra_visit_requests', 'care_booking_events',
    'care_booking_task_checks', 'care_recipient_profiles', 'care_recipient_profile_versions', 'care_relationships'];
$fingerprints = [];
foreach ($tables as $table) {
    $exists = $db->prepare("SELECT count(*) FROM sqlite_master WHERE type='table' AND name=?");
    $exists->execute([$table]);
    if (! $exists->fetchColumn()) {
        continue;
    }
    $rows = $db->query('SELECT * FROM "'.$table.'"')->fetchAll(PDO::FETCH_ASSOC);
    $serialized = array_map(fn ($row) => json_encode($row, JSON_THROW_ON_ERROR), $rows);
    sort($serialized);
    $fingerprints[$table] = ['rows' => count($rows), 'sha256' => hash('sha256', implode("\n", $serialized))];
}
$requests = $db->prepare('SELECT id, request_type, status, is_system_generated FROM care_requests WHERE family_user_id=? ORDER BY id');
$requests->execute([$familyId]);
$plans = $db->prepare('SELECT id, status FROM care_plans WHERE family_user_id=? ORDER BY id');
$plans->execute([$familyId]);
echo json_encode(['requests' => $requests->fetchAll(PDO::FETCH_ASSOC), 'plans' => $plans->fetchAll(PDO::FETCH_ASSOC), 'businessTables' => $fingerprints], JSON_THROW_ON_ERROR);
