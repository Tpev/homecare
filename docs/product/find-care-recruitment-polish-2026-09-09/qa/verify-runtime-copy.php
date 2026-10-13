<?php
$file = realpath($argv[1] ?? '');
if (! $file || ! str_contains(str_replace('\\', '/', $file), '/.tmp/find-care-recruitment-polish-2026-09-09/')) {
    throw new RuntimeException('A backup in the new polish runtime directory is required.');
}
$db = new PDO('sqlite:'.$file);
$db->exec('PRAGMA query_only=ON');
$check = $db->query('PRAGMA integrity_check')->fetchColumn();
echo json_encode(['database' => $file, 'integrity' => $check], JSON_THROW_ON_ERROR).PHP_EOL;
exit($check === 'ok' ? 0 : 1);
