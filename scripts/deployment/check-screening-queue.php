<?php

// Read only the named release's cached configuration. Do not boot Laravel or connect to its database.
$fail = static function (string $message): never {
    fwrite(STDERR, 'Screening queue check: '.$message.PHP_EOL);
    exit(1);
};

if ($argc < 2 || $argc > 3) {
    $fail('Usage: php check-screening-queue.php /absolute/release/bootstrap/cache/config.php [worker-timeout-seconds]');
}

$timeoutArgument = $argv[2] ?? '120';
$workerTimeout = filter_var($timeoutArgument, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (! preg_match('/^[1-9][0-9]*$/D', $timeoutArgument) || $workerTimeout === false) {
    $fail('Worker timeout must be a positive integer number of seconds.');
}

$cachePath = $argv[1];
if (! is_file($cachePath) || ! is_readable($cachePath)) {
    $fail('The release must have a readable cached configuration file.');
}

// Cached configuration is executable, trusted deployment material; discard incidental output.
ob_start();
try {
    $configuration = require $cachePath;
} catch (Throwable) {
    ob_end_clean();
    $fail('The cached configuration could not be read.');
}
ob_end_clean();

if (! is_array($configuration) || ! is_array($configuration['queue'] ?? null)) {
    $fail('The cached configuration has no valid queue configuration.');
}
$queue = $configuration['queue'];
if (($queue['default'] ?? null) !== 'database') {
    $fail('The default queue connection must be database for the configured worker.');
}
$database = $queue['connections']['database'] ?? null;
if (! is_array($database) || ($database['driver'] ?? null) !== 'database') {
    $fail('The database queue connection must use the database driver.');
}
if (($database['queue'] ?? null) !== 'default') {
    $fail('The database queue name must be default for the configured worker.');
}
$retryAfter = $database['retry_after'] ?? null;
if (! is_int($retryAfter) || $retryAfter <= $workerTimeout) {
    $fail('Database retry_after must be an integer greater than the worker timeout ('.$workerTimeout.' seconds). Set DB_QUEUE_RETRY_AFTER=180 for a 120-second worker and rebuild the release configuration cache.');
}

fwrite(STDOUT, 'Screening queue check passed: default=database driver=database queue=default retry_after='.$retryAfter.' worker_timeout='.$workerTimeout.PHP_EOL);
