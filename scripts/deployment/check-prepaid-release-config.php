<?php

// Inspect only the named release's trusted PHP configuration cache. Do not load
// Laravel, resolve services, contact providers, or read/write application data.
$fail = static function (string $message): never {
    fwrite(STDERR, 'Prepaid release configuration check: '.$message.PHP_EOL);
    exit(1);
};

if (! ($argc === 2 || ($argc === 3 && $argv[2] === '--require-disabled')
    || ($argc === 4 && $argv[2] === '--match-sales-state'))) {
    $fail('Usage: php check-prepaid-release-config.php CONFIG_PATH [--require-disabled | --match-sales-state OTHER_CONFIG_PATH]');
}

$requireDisabled = ($argv[2] ?? null) === '--require-disabled';
$referencePath = ($argv[2] ?? null) === '--match-sales-state' ? $argv[3] : null;
$requiredKeys = $referencePath === null ? ['enabled', 'gifts_enabled'] : [
    'enabled', 'gifts_enabled', 'sales_enabled', 'enrollment_enabled', 'contributions_enabled', 'welcome_enabled',
];
$readFlags = static function (string $cachePath) use ($fail, $requiredKeys): array {
    if (! is_file($cachePath) || ! is_readable($cachePath)) {
        $fail('The release must have a readable cached configuration file.');
    }

    // Cached configuration is trusted deployment code, but incidental output and
    // exception/warning details can contain secrets and must never reach deploy logs.
    $readFailed = false;
    $configuration = null;
    ob_start();
    set_error_handler(static function (): never {
        throw new ErrorException('Cached configuration could not be evaluated.');
    });
    try {
        $configuration = (static fn (string $path): mixed => require $path)($cachePath);
    } catch (Throwable) {
        $readFailed = true;
    } finally {
        restore_error_handler();
        ob_end_clean();
    }
    if ($readFailed) {
        $fail('The cached configuration could not be read.');
    }

    if (! is_array($configuration) || ! is_array($configuration['care_hours'] ?? null)) {
        $fail('The cached configuration must contain a care_hours array.');
    }
    $flags = $configuration['care_hours'];
    $result = [];
    foreach ($requiredKeys as $key) {
        if (! array_key_exists($key, $flags) || ! is_bool($flags[$key])) {
            $fail('The cached care_hours.'.$key.' flag must be a boolean.');
        }
        $result[$key] = $flags[$key];
    }

    return $result;
};

$flags = $readFlags($argv[1]);
if ($requireDisabled && ($flags['enabled'] || $flags['gifts_enabled'])) {
    $fail('First prepaid adoption requires care_hours.enabled=false and care_hours.gifts_enabled=false in the cached configuration.');
}
if ($referencePath !== null) {
    if ($flags !== $readFlags($referencePath)) {
        $fail('Rollback must preserve all cached prepaid exposure flags. Use a forward deployment for an intentional change.');
    }
    fwrite(STDOUT, 'Prepaid release configuration check passed: rollback exposure flags match.'.PHP_EOL);
    exit(0);
}

fwrite(STDOUT, $requireDisabled
    ? 'Prepaid release configuration check passed: first-adoption exposure is disabled.'.PHP_EOL
    : 'Prepaid release configuration check passed: cached flags are valid booleans.'.PHP_EOL);
