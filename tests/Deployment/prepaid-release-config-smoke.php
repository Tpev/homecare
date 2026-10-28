<?php

// Standalone subprocess checks; no application bootstrap, database or provider.
$project = dirname(__DIR__, 2);
$helper = $project.'/scripts/deployment/check-prepaid-release-config.php';
$temporaryRoot = realpath(sys_get_temp_dir());
if ($temporaryRoot === false) {
    throw new RuntimeException('The temporary directory is unavailable.');
}
$sandbox = $temporaryRoot.DIRECTORY_SEPARATOR.'homecare-prepaid-config-'.bin2hex(random_bytes(8));
if (! mkdir($sandbox, 0700) || ($sandbox = realpath($sandbox)) === false) {
    throw new RuntimeException('Cannot create an isolated test directory.');
}
$sentinel = 'fictional-secret-never-print-847395';
$pilotDefaults = ['pilot_only' => true, 'pilot_emails' => ['peverelli.t@gmail.com']];
$checks = 0;

$run = static function (array $arguments, int $expectedStatus, string $expectedText, ?string $script = null) use ($helper, $sentinel, &$checks): void {
    $before = [];
    foreach ($arguments as $argument) {
        if (is_file($argument)) {
            $before[$argument] = hash_file('sha256', $argument);
        }
    }
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=1', $script ?? $helper, ...$arguments], [
        0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
    ], $pipes);
    if (! is_resource($process)) {
        throw new RuntimeException('Cannot start the isolated PHP checker.');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    $output = $stdout.$stderr;
    if ($status !== $expectedStatus || ! str_contains($output, $expectedText) || str_contains($output, $sentinel)) {
        throw new RuntimeException('Configuration check failed: expected status '.$expectedStatus.' and sanitized result "'.$expectedText.'"; got status '.$status.'.');
    }
    if (($expectedStatus === 0 && $stderr !== '') || ($expectedStatus !== 0 && $stdout !== '')) {
        throw new RuntimeException('Configuration checker used an unexpected output channel.');
    }
    foreach ($before as $path => $digest) {
        if (hash_file('sha256', $path) !== $digest) {
            throw new RuntimeException('The read-only checker modified a configuration file.');
        }
    }
    $checks++;
};
$fixture = static function (string $name, mixed $configuration) use ($sandbox): string {
    $path = $sandbox.DIRECTORY_SEPARATOR.$name.'.php';
    file_put_contents($path, '<?php return '.var_export($configuration, true).';');

    return $path;
};

try {
    $valid = $fixture('normal-cache', [
        'care_hours' => ['enabled' => false, 'gifts_enabled' => false, 'sales_enabled' => true] + $pilotDefaults,
        'app' => ['key' => $sentinel], 'mail' => ['default' => 'log'], 'services' => ['stripe' => ['secret' => $sentinel]],
    ]);
    $run([$valid], 0, 'cached flags are valid booleans');
    $run([$valid, '--require-disabled'], 0, 'first-adoption exposure is disabled');
    foreach ([[true, false], [false, true], [true, true]] as $index => [$enabled, $gifts]) {
        $path = $fixture('enabled-'.$index, ['care_hours' => ['enabled' => $enabled, 'gifts_enabled' => $gifts] + $pilotDefaults]);
        $run([$path], 0, 'cached flags are valid booleans');
        $run([$path, '--require-disabled'], 0, 'first-adoption exposure is the approved private pilot');
    }
    // The old deployed driver still supplies --require-disabled. Its candidate
    // checker may accept only the reviewed single-family pilot, never a public
    // or wider enabled introduction. Later forward deployments enforce the same cap.
    foreach ([
        ['pilot_only' => false, 'pilot_emails' => ['peverelli.t@gmail.com']],
        ['pilot_only' => true, 'pilot_emails' => []],
        ['pilot_only' => true, 'pilot_emails' => ['other@example.test']],
        ['pilot_only' => true, 'pilot_emails' => ['peverelli.t@gmail.com', 'other@example.test']],
    ] as $index => $access) {
        foreach ([[true, false], [false, true], [true, true]] as $flagsIndex => [$enabled, $gifts]) {
            $path = $fixture('unapproved-enabled-'.$index.'-'.$flagsIndex, [
                'care_hours' => ['enabled' => $enabled, 'gifts_enabled' => $gifts] + $access,
            ]);
            $run([$path], 1, 'Enabled prepaid exposure must be restricted to the approved private pilot');
            $run([$path, '--require-disabled'], 1, 'First prepaid adoption requires');
        }
    }
    $canonicalIntroduction = $fixture('canonical-approved-introduction', ['care_hours' => [
        'enabled' => true, 'gifts_enabled' => true, 'pilot_only' => true,
        'pilot_emails' => [' PEVERELLI.T@GMAIL.COM ', 'peverelli.t@gmail.com'],
    ]]);
    $run([$canonicalIntroduction, '--require-disabled'], 0, 'first-adoption exposure is the approved private pilot');
    foreach ([[], ['care_hours' => null], ['care_hours' => 'false'], false, null] as $index => $value) {
        $run([$fixture('missing-array-'.$index, $value)], 1, 'must contain a care_hours array');
    }
    foreach (['enabled', 'gifts_enabled', 'pilot_only'] as $flag) {
        $flags = ['enabled' => false, 'gifts_enabled' => false] + $pilotDefaults;
        unset($flags[$flag]);
        $run([$fixture('missing-'.$flag, ['care_hours' => $flags])], 1, $flag.' flag must be a boolean');
        foreach (['false', 'true', 0, 1, null, []] as $index => $invalid) {
            $flags = array_replace(['enabled' => false, 'gifts_enabled' => false] + $pilotDefaults, [$flag => $invalid]);
            $run([$fixture('invalid-'.$flag.'-'.$index, ['care_hours' => $flags]), '--require-disabled'], 1, $flag.' flag must be a boolean');
        }
    }
    $pilotBase = ['enabled' => false, 'gifts_enabled' => false] + $pilotDefaults;
    $missingEmails = $pilotBase;
    unset($missingEmails['pilot_emails']);
    foreach ([$missingEmails, ...array_map(static fn ($value) => array_replace($pilotBase, ['pilot_emails' => $value]), [
        null, false, 'peverelli.t@gmail.com', ['named' => 'peverelli.t@gmail.com'],
        [false], [null], [[]], [42], [''], ['not-an-email'], ['one@example.test,two@example.test'],
        [str_repeat('x', 245).'@example.test'],
    ])] as $index => $flags) {
        foreach ([false, true] as $pilotOnly) {
            $flags['pilot_only'] = $pilotOnly;
            $path = $fixture('invalid-pilot-list-'.$index.'-'.(int) $pilotOnly, ['care_hours' => $flags]);
            $run([$path], 1, 'pilot_emails setting must be a list of valid email addresses');
            $run([$path, '--require-disabled'], 1, 'pilot_emails setting must be a list of valid email addresses');
        }
    }
    $emptyPilot = $fixture('empty-pilot', ['care_hours' => array_replace($pilotBase, ['pilot_emails' => []])]);
    $run([$emptyPilot], 0, 'pilot access is valid');
    $run([$emptyPilot, '--require-disabled'], 0, 'first-adoption exposure is disabled');
    $run([$sandbox.DIRECTORY_SEPARATOR.'missing.php'], 1, 'readable cached configuration file');
    $run([$sandbox], 1, 'readable cached configuration file');
    $run([], 1, 'Usage:');
    $run([$valid, '--unknown'], 1, 'Usage:');
    $run([$valid, '--require-disabled', '--extra'], 1, 'Usage:');
    $run([$valid, '--match-sales-state'], 1, 'Usage:');
    $run([$valid, '--require-disabled', '--match-sales-state', $valid], 1, 'Usage:');
    $run([$valid, '--match-sales-state', $valid, '--require-disabled'], 1, 'Usage:');

    // Retained rollback caches must preserve every independent exposure switch,
    // including a sales/contribution pause while the master switch stays enabled.
    $exposureKeys = ['enabled', 'gifts_enabled', 'sales_enabled', 'enrollment_enabled', 'contributions_enabled', 'welcome_enabled', 'pilot_only'];
    foreach ([false, true] as $enabled) {
        $sameFlags = array_fill_keys($exposureKeys, $enabled) + ['pilot_emails' => $pilotDefaults['pilot_emails']];
        $same = $fixture('same-'.(int) $enabled, ['care_hours' => $sameFlags]);
        $run([$same, '--match-sales-state', $same], 0, 'rollback exposure flags match');
    }
    $baseFlags = array_fill_keys($exposureKeys, true) + ['pilot_emails' => $pilotDefaults['pilot_emails']];
    $base = $fixture('rollback-current', ['care_hours' => $baseFlags]);
    foreach ($exposureKeys as $key) {
        $changedFlags = $baseFlags;
        $changedFlags[$key] = false;
        $changed = $fixture('changed-'.$key, ['care_hours' => $changedFlags]);
        $run([$changed, '--match-sales-state', $base], 1, 'must preserve all cached prepaid exposure flags');
        $run([$base, '--match-sales-state', $changed], 1, 'must preserve all cached prepaid exposure flags');
        foreach (['missing', 'string'] as $invalidKind) {
            $invalidFlags = $baseFlags;
            if ($invalidKind === 'missing') {
                unset($invalidFlags[$key]);
            } else {
                $invalidFlags[$key] = 'false';
            }
            $invalid = $fixture('rollback-'.$invalidKind.'-'.$key, ['care_hours' => $invalidFlags]);
            $run([$invalid, '--match-sales-state', $base], 1, $key.' flag must be a boolean');
            $run([$base, '--match-sales-state', $invalid], 1, $key.' flag must be a boolean');
        }
    }
    $canonical = $fixture('canonical-pilot', ['care_hours' => array_replace($baseFlags, ['pilot_emails' => [' PEVERELLI.T@GMAIL.COM ', 'peverelli.t@gmail.com']])]);
    $equivalent = $fixture('equivalent-pilot', ['care_hours' => array_replace($baseFlags, ['pilot_emails' => ['peverelli.t@gmail.com']])]);
    $run([$canonical, '--match-sales-state', $equivalent], 0, 'rollback exposure flags match');
    $run([$equivalent, '--match-sales-state', $canonical], 0, 'rollback exposure flags match');
    $broadRollback = $fixture('broad-matching-rollback', ['care_hours' => array_replace($baseFlags, [
        'pilot_emails' => ['peverelli.t@gmail.com', 'other@example.test'],
    ])]);
    $run([$broadRollback, '--match-sales-state', $broadRollback], 1, 'Enabled prepaid exposure must be restricted to the approved private pilot');
    foreach ([[], ['other@example.test'], ['peverelli.t@gmail.com', 'other@example.test']] as $index => $emails) {
        $changed = $fixture('changed-pilot-emails-'.$index, ['care_hours' => array_replace($baseFlags, ['pilot_emails' => $emails])]);
        $run([$changed, '--match-sales-state', $base], 1, 'must preserve all cached prepaid exposure flags and pilot access');
        $run([$base, '--match-sales-state', $changed], 1, 'must preserve all cached prepaid exposure flags and pilot access');
    }
    foreach (['missing', 'malformed'] as $kind) {
        $flags = $baseFlags;
        if ($kind === 'missing') {
            unset($flags['pilot_emails']);
        } else {
            $flags['pilot_emails'] = ['not-an-email'];
        }
        $invalid = $fixture('rollback-pilot-emails-'.$kind, ['care_hours' => $flags]);
        $run([$invalid, '--match-sales-state', $base], 1, 'pilot_emails setting must be a list of valid email addresses');
        $run([$base, '--match-sales-state', $invalid], 1, 'pilot_emails setting must be a list of valid email addresses');
    }
    $run([$base, '--match-sales-state', $sandbox.DIRECTORY_SEPARATOR.'missing-reference.php'], 1, 'readable cached configuration file');
    $run([$base, '--match-sales-state', $fixture('bad-reference-array', false)], 1, 'must contain a care_hours array');

    $broken = $sandbox.DIRECTORY_SEPARATOR.'broken.php';
    file_put_contents($broken, '<?php return [ malformed '.$sentinel);
    $run([$broken], 1, 'could not be read');
    $run([$base, '--match-sales-state', $broken], 1, 'could not be read');
    file_put_contents($broken, '<?php throw new RuntimeException('.var_export($sentinel, true).');');
    $run([$broken], 1, 'could not be read');
    file_put_contents($broken, '<?php trigger_error('.var_export($sentinel, true).', E_USER_WARNING); return [];');
    $run([$broken], 1, 'could not be read');
    file_put_contents($broken, '<?php echo '.var_export($sentinel, true).'; return '.var_export(['care_hours' => ['enabled' => false, 'gifts_enabled' => false] + $pilotDefaults], true).';');
    $run([$broken, '--require-disabled'], 0, 'first-adoption exposure is disabled');

    // A permission-denying stream fixture exercises is_file=true/is_readable=false
    // on Windows too, where chmod(0000) does not model Unix file permissions.
    $unreadable = $sandbox.DIRECTORY_SEPARATOR.'unreadable-wrapper.php';
    file_put_contents($unreadable, '<?php
class UnreadableConfigFixture {
    public $context;
    public function url_stat($path, $flags) { return ["mode" => 0100000, "uid" => 0, "gid" => 0, "size" => 0]; }
}
stream_wrapper_register("unreadableconfig", UnreadableConfigFixture::class);
if (! is_file("unreadableconfig://cache.php") || is_readable("unreadableconfig://cache.php")) { exit(99); }
$argv = ['.var_export($helper, true).', "unreadableconfig://cache.php"];
$argc = 2;
require '.var_export($helper, true).';');
    $run([], 1, 'readable cached configuration file', $unreadable);
    $referenceWrapper = str_replace(
        '$argv = ['.var_export($helper, true).', "unreadableconfig://cache.php"];'."\n".'$argc = 2;',
        '$argv = ['.var_export($helper, true).', '.var_export($base, true).', "--match-sales-state", "unreadableconfig://cache.php"];'."\n".'$argc = 4;',
        str_replace("\r\n", "\n", file_get_contents($unreadable)),
        $replacements,
    );
    if ($replacements !== 1) {
        throw new RuntimeException('Unreadable reference fixture could not be constructed.');
    }
    file_put_contents($unreadable, $referenceWrapper);
    $run([], 1, 'readable cached configuration file', $unreadable);

    fwrite(STDOUT, 'Prepaid release configuration smoke passed ('.$checks.' checks; no app, database or provider access).'.PHP_EOL);
} finally {
    // Only flat files created inside this exact disposable directory are removed.
    $resolved = realpath($sandbox);
    if ($resolved === false || $resolved !== $sandbox || dirname($resolved) !== $temporaryRoot || ! str_starts_with(basename($resolved), 'homecare-prepaid-config-')) {
        throw new RuntimeException('Refusing unsafe test cleanup.');
    }
    foreach (new FilesystemIterator($resolved) as $file) {
        if (! $file->isFile() || $file->isLink() || dirname($file->getRealPath()) !== $resolved) {
            throw new RuntimeException('Unexpected file in disposable test directory.');
        }
        unlink($file->getPathname());
    }
    rmdir($resolved);
}
