<?php

// Read-only comparison of the September 7 synthetic baseline business rows.
// New September 8 fixtures are allowed; existing care, agreement and money rows
// must retain every stored field. Session/login/telemetry tables are excluded.
$root = getcwd();
$source = realpath($root.'/.tmp/find-care-screen-audit/find-care-audit.sqlite');
$target = realpath($root.'/.tmp/find-care-screen-audit-2026-09-08/find-care-audit.sqlite');
if (! $source || ! $target || $source === $target) {
    throw new RuntimeException('Both distinct synthetic audit databases must exist.');
}
$baseline = new PDO('sqlite:'.$source);
$current = new PDO('sqlite:'.$target);
foreach ([$baseline, $current] as $connection) {
    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $connection->exec('PRAGMA query_only=ON');
}
if (in_array('--list-tables', $argv, true)) {
    echo json_encode($baseline->query("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}
$initial = json_decode(file_get_contents(dirname($target).'/initialization.json'), true, flags: JSON_THROW_ON_ERROR);
$tables = ['care_requests', 'care_request_applications', 'care_request_invitations', 'care_bookings',
    'care_booking_payments', 'care_booking_payment_attempts', 'care_booking_payment_operations',
    'care_reviews', 'care_booking_change_requests', 'care_booking_time_corrections',
    'care_booking_corrections', 'care_booking_incidents', 'care_plans', 'care_plan_events',
    'care_plan_schedule_changes', 'completed_extra_visit_requests'];
$tables = array_merge($tables, ['care_booking_events', 'care_booking_task_checks',
    'care_recipient_profile_versions', 'care_recipient_profiles', 'care_relationships',
    'care_request_conversations', 'care_request_messages', 'care_request_recipients',
    'care_request_task', 'care_request_third_party_contacts', 'care_tasks',
    'caregiver_availabilities', 'caregiver_certification_types', 'caregiver_certifications',
    'caregiver_experience_types', 'caregiver_identity_verifications', 'caregiver_language',
    'caregiver_payout_items', 'caregiver_payouts', 'caregiver_profile_experience',
    'caregiver_profile_versions', 'caregiver_profiles', 'caregiver_skill',
    'family_account_invitations', 'family_account_members', 'family_accounts',
    'family_caregiver_favorites', 'family_household_profiles', 'family_recipient_profiles',
    'languages', 'skills', 'support_ticket_activities', 'support_ticket_messages',
    'support_tickets', 'user_notification_preferences',
    'continuous_coverage_events', 'continuous_coverage_handoffs', 'continuous_coverage_lane_requests',
    'continuous_coverage_plans', 'continuous_coverage_replacement_cases', 'continuous_coverage_roster_members',
    'continuous_coverage_shift_offers', 'continuous_coverage_shift_templates', 'continuous_coverage_shifts', 'users']);
$result = ['checkedAt' => gmdate('c'), 'baselineDatabaseUnchanged' => hash_file('sha256', $source) === $initial['sourceSha256'], 'tables' => [], 'failures' => []];
$excludedFields = ['users' => ['password', 'remember_token', 'last_login_at', 'last_seen_at', 'updated_at']];
$result['excludedFields'] = $excludedFields;
$result['excludedOperationalTables'] = ['sessions, password_reset_tokens, cache, cache_locks', 'family_conversation_reads, family_support_ticket_reads, care_recipient_profile_views (read/view receipts)',
    'family_account_activity_logs, caregiver_moderation_logs, page_view_events, funnel_events, marketplace_notification_deliveries, notifications (activity/delivery telemetry)',
    'jobs, job_batches, failed_jobs, migrations, sqlite_sequence', 'ai_support_* (assistant runtime telemetry/drafts)',
    'content/blog/marketing/lead/knowledge-base/infrastructure tables outside care records'];
foreach ($tables as $table) {
    $exists = $baseline->prepare("SELECT count(*) FROM sqlite_master WHERE type = 'table' AND name = ?");
    $exists->execute([$table]);
    if (! $exists->fetchColumn()) continue;
    $columns = $baseline->query('PRAGMA table_info("'.$table.'")')->fetchAll(PDO::FETCH_ASSOC);
    $primary = array_values(array_filter($columns, fn ($column) => (int) $column['pk'] > 0));
    usort($primary, fn ($a, $b) => $a['pk'] <=> $b['pk']);
    $keys = array_column($primary, 'name');
    if (! $keys) $keys = array_column($columns, 'name');
    $rows = $baseline->query('SELECT * FROM "'.$table.'"')->fetchAll(PDO::FETCH_ASSOC);
    $find = $current->prepare('SELECT * FROM "'.$table.'" WHERE '.implode(' AND ', array_map(fn ($key) => '"'.$key.'" IS ?', $keys)));
    $different = [];
    foreach ($rows as $row) {
        $identity = array_intersect_key($row, array_flip($keys));
        $find->execute(array_map(fn ($key) => $row[$key], $keys));
        $now = $find->fetch(PDO::FETCH_ASSOC);
        if (! $now) {
            $different[] = ['identity' => $identity, 'missingOrChanged' => true];
        } else {
            $fields = [];
            foreach ($row as $key => $value) {
                if (in_array($key, $excludedFields[$table] ?? [], true)) continue;
                if (! array_key_exists($key, $now) || $now[$key] !== $value) $fields[] = $key;
            }
            if ($fields) $different[] = ['identity' => $identity, 'changedFields' => $fields];
        }
    }
    $result['tables'][$table] = ['baselineRows' => count($rows), 'different' => $different];
    if ($different) $result['failures'][] = $table.' has changed baseline rows.';
}
if (! $result['baselineDatabaseUnchanged']) $result['failures'][] = 'Original baseline database SHA256 changed.';
$result['passed'] = ! $result['failures'];
$json = json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
file_put_contents($root.'/docs/product/find-care-screen-audit-2026-09-08/data-preservation.json', $json);
echo $json.PHP_EOL;
exit($result['passed'] ? 0 : 1);
