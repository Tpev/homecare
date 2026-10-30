<?php

// Inspect only the named release's trusted PHP configuration cache. Do not load
// Laravel, resolve services, contact providers, or read/write application data.
$fail = static function (string $message): never {
    fwrite(STDERR, 'Prepaid release configuration check: '.$message.PHP_EOL);
    exit(1);
};

$usage = 'Usage: php check-prepaid-release-config.php CONFIG_PATH [--require-disabled | --match-sales-state OTHER_CONFIG_PATH] [--contract-version 1|2]';
if ($argc < 2) {
    $fail($usage);
}
$requireDisabled = false;
$referencePath = null;
$contractVersion = '1';
$hasMode = false;
$hasContract = false;
for ($index = 2; $index < $argc; $index++) {
    $argument = $argv[$index];
    if ($argument === '--require-disabled' && ! $hasMode) {
        $requireDisabled = $hasMode = true;
    } elseif ($argument === '--match-sales-state' && ! $hasMode && isset($argv[$index + 1]) && ! str_starts_with($argv[$index + 1], '--')) {
        $referencePath = $argv[++$index];
        $hasMode = true;
    } elseif ($argument === '--contract-version' && ! $hasContract && in_array($argv[$index + 1] ?? null, ['1', '2'], true)) {
        $contractVersion = $argv[++$index];
        $hasContract = true;
    } else {
        $fail($usage);
    }
}
$requiredKeys = $referencePath === null ? ['enabled', 'gifts_enabled', 'pilot_only'] : [
    'enabled', 'gifts_enabled', 'sales_enabled', 'enrollment_enabled', 'contributions_enabled', 'welcome_enabled', 'pilot_only',
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

    if (! array_key_exists('pilot_emails', $flags) || ! is_array($flags['pilot_emails']) || ! array_is_list($flags['pilot_emails'])) {
        $fail('The cached care_hours.pilot_emails setting must be a list of valid email addresses.');
    }
    $emails = [];
    foreach ($flags['pilot_emails'] as $email) {
        if (! is_string($email) || strlen(trim($email)) > 254
            || filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false) {
            $fail('The cached care_hours.pilot_emails setting must be a list of valid email addresses.');
        }
        $emails[] = strtolower(trim($email));
    }
    // An empty list is a deliberate deny-all pilot, never a public launch.
    // Compare effective membership without exposing customer emails in logs.
    $emails = array_values(array_unique($emails));
    sort($emails, SORT_STRING);
    $result['pilot_emails'] = $emails;

    // Older v1 caches predate this setting and remain recipient-scoped. The
    // wallet release must explicitly declare family ownership in its cache.
    $scope = array_key_exists('wallet_scope', $flags) ? $flags['wallet_scope'] : 'recipient';
    if (! in_array($scope, ['recipient', 'family'], true)) {
        $fail('The cached care_hours.wallet_scope setting must be recipient or family.');
    }
    $result['wallet_scope'] = $scope;

    return $result;
};

$flags = $readFlags($argv[1]);
$expectedScope = $contractVersion === '2' ? 'family' : 'recipient';
if ($flags['wallet_scope'] !== $expectedScope) {
    $fail('The cached wallet ownership scope does not match the verified prepaid contract.');
}
if ($referencePath !== null) {
    if ($flags !== $readFlags($referencePath)) {
        $fail('Rollback must preserve all cached prepaid exposure flags and pilot access, including wallet ownership scope. Use a forward deployment for an intentional change.');
    }
}

$hasExposure = $flags['enabled'] || $flags['gifts_enabled'];
$approvedPrivatePilot = $flags['pilot_only'] === true
    && $flags['pilot_emails'] === ['peverelli.t@gmail.com'];
// Keep the original CLI option compatible with an already-installed deploy.sh.
// The authorized introduction is now either disabled or this one-family pilot;
// no enabled public or broader pilot cache can pass any forward deployment.
if ($hasExposure && ! $approvedPrivatePilot) {
    $fail($requireDisabled
        ? 'First prepaid adoption requires disabled exposure or the approved private pilot.'
        : 'Enabled prepaid exposure must be restricted to the approved private pilot.');
}
if ($referencePath !== null) {
    fwrite(STDOUT, 'Prepaid release configuration check passed: rollback exposure flags match.'.PHP_EOL);
    exit(0);
}

fwrite(STDOUT, $requireDisabled
    ? ($hasExposure
        ? 'Prepaid release configuration check passed: first-adoption exposure is the approved private pilot.'.PHP_EOL
        : 'Prepaid release configuration check passed: first-adoption exposure is disabled.'.PHP_EOL)
    : 'Prepaid release configuration check passed: cached flags are valid booleans and pilot access is valid.'.PHP_EOL);
