<?php

// Read-only inventory of the existing synthetic care data. No fixture reset.
$database = realpath(getcwd().'/.tmp/find-care-screen-audit-2026-09-08/find-care-audit.sqlite');
if (! $database || ! str_ends_with(str_replace('\\', '/', $database), '/find-care-screen-audit-2026-09-08/find-care-audit.sqlite')) {
    throw new RuntimeException('The isolated September 8 local database is required.');
}
$db = new PDO('sqlite:'.$database);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA query_only=ON');
$baseline = isset($argv[1]) ? new PDO('sqlite:'.realpath($argv[1])) : null;
$manifest = json_decode(file_get_contents(getcwd().'/docs/product/find-care-screen-audit-2026-09-08/fixture-manifest.json'), true, flags: JSON_THROW_ON_ERROR);
$result = ['at' => gmdate('c'), 'database' => $database, 'requests' => [], 'tables' => []];
$result['addedTables'] = [];
if ($baseline) {
    $oldTables = $baseline->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    $currentTables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    foreach (array_diff($currentTables, $oldTables) as $table) {
        $result['addedTables'][$table] = ['rows' => (int) $db->query('SELECT count(*) FROM "'.$table.'"')->fetchColumn()];
    }
}
foreach ($manifest['requests'] as $variation => $requests) {
    foreach ($requests as $name => $id) {
        $query = $db->prepare('SELECT * FROM care_requests WHERE id = ?');
        $query->execute([$id]);
        $request = $query->fetch(PDO::FETCH_ASSOC);
        $query = $db->prepare('SELECT * FROM care_bookings WHERE care_request_id = ?');
        $query->execute([$id]);
        $bookings = $query->fetchAll(PDO::FETCH_ASSOC);
        $records = [];
        foreach (['care_request_applications', 'care_request_invitations'] as $table) {
            $query = $db->prepare('SELECT * FROM '.$table.' WHERE care_request_id = ? ORDER BY id');
            $query->execute([$id]);
            $records[$table] = $query->fetchAll(PDO::FETCH_ASSOC);
        }
        $result['requests'][$variation][$name] = ['request' => $request, 'bookings' => $bookings, 'records' => $records];
    }
}
$tables = ['care_requests', 'care_request_applications', 'care_request_invitations', 'care_bookings',
    'care_booking_payments', 'care_booking_payment_attempts', 'care_booking_payment_operations',
    'care_reviews', 'care_booking_change_requests', 'care_booking_time_corrections',
    'care_booking_corrections', 'care_booking_incidents', 'care_plans', 'care_plan_events',
    'care_plan_schedule_changes', 'completed_extra_visit_requests', 'care_booking_events',
    'care_booking_task_checks', 'care_recipient_profile_versions', 'care_recipient_profiles',
    'care_request_conversations', 'care_request_messages', 'care_request_recipients',
    'care_request_task', 'care_request_third_party_contacts', 'family_caregiver_favorites'];
foreach ($tables as $table) {
    $columns = ($baseline ?? $db)->query('PRAGMA table_info("'.$table.'")')->fetchAll(PDO::FETCH_ASSOC);
    $names = array_column($columns, 'name');
    $selection = implode(', ', array_map(fn ($name) => '"'.$name.'"', $names));
    $rows = $db->query('SELECT '.$selection.' FROM "'.$table.'" ORDER BY rowid')->fetchAll(PDO::FETCH_ASSOC);
    $currentNames = array_column($db->query('PRAGMA table_info("'.$table.'")')->fetchAll(PDO::FETCH_ASSOC), 'name');
    $result['tables'][$table] = ['rows' => count($rows), 'sha256' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)), 'newColumns' => array_values(array_diff($currentNames, $names))];
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
