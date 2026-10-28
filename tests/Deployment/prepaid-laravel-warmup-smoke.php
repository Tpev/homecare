<?php

declare(strict_types=1);

// Local-only deployment rehearsal. It never loads the application's .env, runs
// scheduled jobs, or contacts providers. Every generated file lives under tmp/.
$root = str_replace('\\', '/', dirname(__DIR__, 2));
$run = $root.'/tmp/prepaid-laravel-warmup-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3));
$cacheRoot = substr($run, strlen($root) + 1).'/bootstrap/cache';
foreach (['bootstrap/cache', 'storage/app/private', 'storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs'] as $directory) {
    if (! mkdir($run.'/'.$directory, 0770, true) && ! is_dir($run.'/'.$directory)) {
        throw new RuntimeException('Could not create isolated rehearsal directory.');
    }
}
file_put_contents($run.'/.env', "# Deliberately empty: the child receives an explicit isolated environment.\n");
touch($run.'/database.sqlite');
copy($root.'/bootstrap/providers.php', $run.'/bootstrap/providers.php');

$env = [];
foreach (['SystemRoot', 'WINDIR', 'PATH', 'TEMP', 'TMP', 'COMSPEC', 'PATHEXT'] as $key) {
    if (($value = getenv($key)) !== false) {
        $env[$key] = $value;
    }
}
$env += [
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_URL' => 'http://127.0.0.1',
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('x', 32)),
    'APP_CONFIG_CACHE' => $cacheRoot.'/config.php',
    'APP_ROUTES_CACHE' => $cacheRoot.'/routes-v7.php',
    'APP_PACKAGES_CACHE' => $cacheRoot.'/packages.php',
    'APP_SERVICES_CACHE' => $cacheRoot.'/services.php',
    'APP_EVENTS_CACHE' => $cacheRoot.'/events.php',
    'LARAVEL_STORAGE_PATH' => $run.'/storage',
    'VIEW_COMPILED_PATH' => $run.'/storage/framework/views',
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $run.'/database.sqlite', 'DB_URL' => '',
    'CACHE_STORE' => 'database', 'SESSION_DRIVER' => 'database', 'QUEUE_CONNECTION' => 'database',
    'LOG_CHANNEL' => 'single', 'MAIL_MAILER' => 'array', 'FILESYSTEM_DISK' => 'local',
    'STRIPE_SECRET' => '', 'STRIPE_KEY' => '', 'STRIPE_WEBHOOK_SECRET' => '', 'STRIPE_BYPASS' => 'true',
    'DIDIT_BYPASS' => 'true', 'TWILIO_BYPASS' => 'true', 'SLACK_OPS_NOTIFICATIONS_ENABLED' => 'false',
    'AWS_EC2_METADATA_DISABLED' => 'true',
];
// Intentionally omit prepaid variables first: normal deployment must activate
// the reviewed single-family pilot without any shared .env edit.
$pilotDefaults = [
    'enabled' => true, 'gifts_enabled' => true, 'pilot_only' => true,
    'pilot_emails' => ['peverelli.t@gmail.com'], 'enrollment_enabled' => true,
    'sales_enabled' => true, 'contributions_enabled' => true, 'welcome_enabled' => true,
];
file_put_contents($run.'/expected-prepaid.json', json_encode($pilotDefaults, JSON_THROW_ON_ERROR));
$bootstrap = <<<'PHP'
<?php
$app = require __ROOT__.'/bootstrap/app.php';
$app->useBootstrapPath(__DIR__);
$app->useEnvironmentPath(dirname(__DIR__));
$app->loadEnvironmentFrom('.env');
$app->useStoragePath(dirname(__DIR__).'/storage');
$app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app) {
    $fixture = str_replace('\\', '/', dirname(__DIR__));
    $assertions = [
        'isolated database' => $app['config']->get('database.default') === 'sqlite'
            && str_replace('\\', '/', $app['config']->get('database.connections.sqlite.database')) === $fixture.'/database.sqlite',
        'isolated storage' => str_replace('\\', '/', $app->storagePath()) === $fixture.'/storage',
        'isolated config cache' => str_replace('\\', '/', $app->getCachedConfigPath()) === $fixture.'/bootstrap/cache/config.php',
        'isolated route cache' => str_replace('\\', '/', $app->getCachedRoutesPath()) === $fixture.'/bootstrap/cache/routes-v7.php',
        'isolated views' => str_replace('\\', '/', $app['config']->get('view.compiled')) === $fixture.'/storage/framework/views',
        'mail capture' => $app['config']->get('mail.default') === 'array',
        'no Stripe credential' => ! $app['config']->get('services.stripe.secret') && $app['config']->get('services.stripe.bypass') === true,
    ];
    $expected = json_decode(file_get_contents($fixture.'/expected-prepaid.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($expected as $key => $value) {
        $assertions['effective prepaid '.$key] = $app['config']->get('care_hours.'.$key) === $value;
    }
    $access = new \App\Services\CareHours\CareHourAccess;
    $pilot = new \App\Models\User(['role' => 'family', 'email' => 'peverelli.t@gmail.com']);
    $assertions['pilot discovers iff enabled'] = $access->canDiscover($pilot) === $expected['enabled'];
    $assertions['pilot buys iff enabled and sales open'] = $access->canBuy($pilot) === ($expected['enabled'] && $expected['sales_enabled']);
    $assertions['pilot gifts respect sales pause'] = $access->canBuyGift($pilot) === ($expected['enabled'] && $expected['sales_enabled'] && $expected['gifts_enabled']);
    $assertions['pilot enrollment respects pause'] = $access->canEnroll($pilot) === ($expected['enabled'] && $expected['enrollment_enabled']);
    $assertions['pilot contributions respect pause'] = $access->canContribute($pilot) === ($expected['enabled'] && $expected['sales_enabled'] && $expected['contributions_enabled']);
    foreach ([null, new \App\Models\User(['role' => 'family', 'email' => 'outsider@example.test']),
        new \App\Models\User(['role' => 'family', 'email' => 'peverelli.t+pilot@gmail.com']),
        new \App\Models\User(['role' => 'caregiver', 'email' => 'peverelli.t@gmail.com'])] as $index => $other) {
        $assertions['nonpilot '.$index.' cannot discover or buy'] = ! $access->canDiscover($other)
            && ! $access->canBuy($other) && ! $access->canBuyGift($other) && ! $access->canContribute($other);
    }
    foreach ($assertions as $label => $passed) {
        if (! $passed) {
            throw new \RuntimeException('Rehearsal isolation assertion failed: '.$label);
        }
    }
});
return $app;
PHP;
file_put_contents($run.'/bootstrap/app.php', str_replace('__ROOT__', var_export($root, true), $bootstrap));
$artisan = "<?php\nrequire ".var_export($root.'/vendor/autoload.php', true).";\n\$app = require __DIR__.'/bootstrap/app.php';\nexit(\$app->handleCommand(new \\Symfony\\Component\\Console\\Input\\ArgvInput));\n";
file_put_contents($run.'/artisan', $artisan);

$protected = [$root.'/.env', $root.'/database/database.sqlite'];
foreach (glob($root.'/bootstrap/cache/*.php') ?: [] as $file) {
    $protected[] = $file;
}
$snapshot = static function () use ($protected): array {
    $hashes = [];
    foreach ($protected as $file) {
        $hashes[$file] = is_file($file) ? hash_file('sha256', $file) : null;
    }

    return $hashes;
};
$before = $snapshot();
$commands = [
    ['package:discover', '--ansi'],
    ['migrate', '--force', '--no-interaction'],
    ['config:cache'],
    ['route:cache'],
    ['view:cache'],
    ['route:list', '--json'],
    ['list', '--format=json'],
    ['schedule:list', '--no-ansi'],
    ['migrate:status', '--no-ansi'],
    ['care-hours:health', '--json'],
];
$evidence = ['run_path' => $run, 'network_disabled' => true, 'production_access' => false, 'commands' => []];
$failed = false;
foreach ($commands as $arguments) {
    $label = implode(' ', $arguments);
    $path = $run.'/'.str_replace(':', '-', $arguments[0]).'.log';
    $command = [PHP_BINARY, '-d', 'allow_url_fopen=0', '-d', 'disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client', $run.'/artisan', ...$arguments];
    $started = microtime(true);
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $path, 'w'], 2 => ['file', $path, 'a']], $pipes, $root, $env, ['bypass_shell' => true]);
    if (! is_resource($process)) {
        throw new RuntimeException('Could not launch isolated PHP child.');
    }
    fclose($pipes[0]);
    $exit = proc_close($process);
    $evidence['commands'][] = ['command' => $label, 'exit_code' => $exit, 'seconds' => round(microtime(true) - $started, 2), 'log' => $path];
    echo ($exit === 0 ? 'PASS ' : 'FAIL ').$label.PHP_EOL;
    if ($exit !== 0) {
        $failed = true;
        break;
    }
}
if (! $failed) {
    $config = require $run.'/bootstrap/cache/config.php';
    $routes = json_decode(file_get_contents($run.'/route-list.log'), true, 512, JSON_THROW_ON_ERROR);
    $commandsList = json_decode(file_get_contents($run.'/list.log'), true, 512, JSON_THROW_ON_ERROR);
    $routeNames = array_column($routes, 'name');
    $commandNames = array_column($commandsList['commands'], 'name');
    $requiredRoutes = ['care-hours.claim', 'care-hours.claim.start', 'care-hours.gifts.index', 'care-hours.gifts.checkout', 'care-hours.contribute', 'family.care-hours.claim', 'family.care-hours.claim.confirm', 'family.care-hours.claim.ready', 'family.care-hours.index', 'admin.care-hours.index', 'admin.care-hours.gifts.index'];
    $requiredCommands = ['care-hours:health', 'homecare:reconcile-care-hour-purchases', 'care-hours:reconcile-bookings', 'homecare:dispatch-notifications'];
    $schedule = file_get_contents($run.'/schedule-list.log');
    $evidence['assertions'] = [
        'care_hours_default_enabled_after_config_cache' => ($config['care_hours']['enabled'] ?? null) === true,
        'gift_sales_default_enabled_after_config_cache' => ($config['care_hours']['gifts_enabled'] ?? null) === true,
        'restricted_pilot_after_config_cache' => ($config['care_hours']['pilot_only'] ?? null) === true,
        'single_pilot_family_after_config_cache' => ($config['care_hours']['pilot_emails'] ?? null) === ['peverelli.t@gmail.com'],
        'required_routes_registered_after_route_cache' => array_diff($requiredRoutes, $routeNames) === [],
        'required_commands_discovered' => array_diff($requiredCommands, $commandNames) === [],
        'purchase_reconciliation_scheduled' => str_contains($schedule, 'homecare:reconcile-care-hour-purchases'),
        'booking_reconciliation_scheduled' => str_contains($schedule, 'care-hours:reconcile-bookings'),
        'notifications_scheduled' => str_contains($schedule, 'homecare:dispatch-notifications'),
    ];
    $evidence['care_hours_route_count'] = count(array_filter($routeNames, static fn ($name) => is_string($name) && str_contains($name, 'care-hours')));
    $evidence['compiled_view_count'] = count(glob($run.'/storage/framework/views/*.php') ?: []);
    foreach ($evidence['assertions'] as $label => $passed) {
        echo ($passed ? 'PASS ' : 'FAIL ').$label.PHP_EOL;
        $failed = $failed || ! $passed;
    }
}
if (! $failed) {
    // Rebuild only isolated caches with representative pre-existing operator
    // settings. No process loads the repository's .env or changes its cache.
    $scenarios = [
        'legacy-disabled-flags' => [
            'env' => ['CARE_HOURS_ENABLED' => 'false', 'CARE_HOURS_GIFTS_ENABLED' => 'false'],
            'expected' => $pilotDefaults,
        ],
        'legacy-broad-access' => [
            'env' => ['CARE_HOURS_ENABLED' => 'true', 'CARE_HOURS_GIFTS_ENABLED' => 'true',
                'CARE_HOURS_PILOT_ONLY' => 'false', 'CARE_HOURS_PILOT_EMAILS' => 'outsider@example.test,peverelli.t@gmail.com'],
            'expected' => $pilotDefaults,
        ],
        'explicit-private-pilot-pause' => [
            'env' => ['CARE_HOURS_PRIVATE_PILOT_ENABLED' => 'false', 'CARE_HOURS_ENABLED' => 'true',
                'CARE_HOURS_GIFTS_ENABLED' => 'true', 'CARE_HOURS_PILOT_ONLY' => 'false'],
            'expected' => array_replace($pilotDefaults, ['enabled' => false, 'gifts_enabled' => false]),
            'repeat_cache' => true,
        ],
        'malformed-private-pilot-switch' => [
            'env' => ['CARE_HOURS_PRIVATE_PILOT_ENABLED' => 'not-a-boolean'],
            'expected' => array_replace($pilotDefaults, ['enabled' => false, 'gifts_enabled' => false]),
        ],
        'empty-private-pilot-switch' => [
            'env' => ['CARE_HOURS_PRIVATE_PILOT_ENABLED' => ''],
            'expected' => array_replace($pilotDefaults, ['enabled' => false, 'gifts_enabled' => false]),
        ],
        'existing-action-pauses' => [
            'env' => ['CARE_HOURS_ENROLLMENT_ENABLED' => 'false', 'CARE_HOURS_SALES_ENABLED' => 'false',
                'CARE_HOURS_CONTRIBUTIONS_ENABLED' => 'false', 'CARE_HOURS_WELCOME_BONUS_ENABLED' => 'false'],
            'expected' => array_replace($pilotDefaults, ['enrollment_enabled' => false, 'sales_enabled' => false,
                'contributions_enabled' => false, 'welcome_enabled' => false]),
        ],
    ];
    foreach ($scenarios as $name => $scenario) {
        // This exact owned path is the cache produced above, never a shared one.
        if (is_file($run.'/bootstrap/cache/config.php')) {
            unlink($run.'/bootstrap/cache/config.php');
        }
        file_put_contents($run.'/expected-prepaid.json', json_encode($scenario['expected'], JSON_THROW_ON_ERROR));
        $dotenv = "# Isolated operator settings fixture; no secrets.\n";
        foreach ($scenario['env'] as $key => $value) {
            $dotenv .= $key.'='.$value."\n";
        }
        file_put_contents($run.'/.env', $dotenv);
        $environmentHash = hash_file('sha256', $run.'/.env');
        $scenarioCommands = [['config:cache'], ['route:list', '--json']];
        if ($scenario['repeat_cache'] ?? false) {
            $scenarioCommands[] = ['config:cache'];
            $scenarioCommands[] = ['route:list', '--json'];
        }
        foreach ($scenarioCommands as $index => $arguments) {
            $path = $run.'/'.$name.'-'.$index.'.log';
            $command = [PHP_BINARY, '-d', 'allow_url_fopen=0', '-d', 'disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client', $run.'/artisan', ...$arguments];
            $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $path, 'w'], 2 => ['file', $path, 'a']], $pipes, $root, $env, ['bypass_shell' => true]);
            if (! is_resource($process)) {
                throw new RuntimeException('Could not launch isolated configuration child.');
            }
            fclose($pipes[0]);
            $exit = proc_close($process);
            $label = $name.' '.implode(' ', $arguments);
            $evidence['commands'][] = ['command' => $label, 'exit_code' => $exit, 'log' => $path];
            echo ($exit === 0 ? 'PASS ' : 'FAIL ').$label.PHP_EOL;
            if ($exit !== 0) {
                $failed = true;
                break 2;
            }
        }
        $cache = require $run.'/bootstrap/cache/config.php';
        // Compare by key instead of insertion order, as config source may reorder.
        $passed = count(array_filter($scenario['expected'], static fn ($value, $key) => ($cache['care_hours'][$key] ?? null) !== $value, ARRAY_FILTER_USE_BOTH)) === 0;
        $passed = $passed && $environmentHash === hash_file('sha256', $run.'/.env');
        $evidence['assertions'][$name.'_cached_effective_configuration'] = $passed;
        $failed = $failed || ! $passed;
    }
}
$evidence['protected_files_unchanged'] = $before === $snapshot();
$failed = $failed || ! $evidence['protected_files_unchanged'];
$evidence['passed'] = ! $failed;
file_put_contents($run.'/evidence.json', json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
echo 'Evidence: '.$run.'/evidence.json'.PHP_EOL;
exit($failed ? 1 : 0);
