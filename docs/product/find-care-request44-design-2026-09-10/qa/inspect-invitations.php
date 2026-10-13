<?php
$db = new PDO('sqlite:'.getcwd().'/.tmp/find-care-screen-audit-2026-09-08/find-care-audit.sqlite');
$db->exec('PRAGMA query_only=ON');
$result = ['invitations' => $db->query('SELECT id, care_request_id, caregiver_user_id, status, expires_at, created_at, updated_at FROM care_request_invitations ORDER BY updated_at DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC)];
$rows = $db->query('SELECT * FROM care_request_invitations WHERE id < 7 ORDER BY rowid')->fetchAll(PDO::FETCH_ASSOC);
$result['originalRows'] = ['rows' => count($rows), 'sha256' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR))];
echo json_encode($result, JSON_PRETTY_PRINT);
