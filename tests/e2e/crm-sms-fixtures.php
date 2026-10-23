<?php

use App\Models\Lead;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Process\Process;

$root = dirname(__DIR__, 2);
$database = str_replace('\\', '/', $root.'/storage/app/crm-sms-qa.sqlite');
$cache = str_replace('\\', '/', $root.'/storage/app/crm-sms-qa-config.php');

if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'sqlite'
    || str_replace('\\', '/', (string) getenv('DB_DATABASE')) !== $database
    || str_replace('\\', '/', (string) getenv('APP_CONFIG_CACHE')) !== $cache
    || getenv('DB_URL') !== '' || getenv('TWILIO_BYPASS') !== 'true'
    || getenv('MAIL_MAILER') !== 'array' || is_file($cache)) {
    throw new RuntimeException('CRM SMS browser tests require their isolated database, simulated SMS, and captured mail.');
}

if (PHP_SAPI === 'cli-server') {
    $requestPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $public = realpath($root.'/public');
    $file = realpath($root.'/public'.$requestPath);
    if ($file && $public && str_starts_with(str_replace('\\', '/', $file), str_replace('\\', '/', $public).'/')
        && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
        return false;
    }
}

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config([
    'database.connections.sqlite.url' => null,
    'database.connections.sqlite.database' => $database,
    'mail.default' => 'array', 'services.twilio.bypass' => true,
    'services.twilio.sms_from' => '+19195550000', 'services.stripe.bypass' => true,
    'services.slack.ops.enabled' => false, 'marketplace.ops_alert_recipients' => [],
]);
Mail::fake();
Notification::fake();
Http::preventStrayRequests();

if (PHP_SAPI === 'cli-server') {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['PHP_SELF'] = '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $root.'/public/index.php';
    $app->handleRequest(Request::capture());

    return;
}

if (($argv[1] ?? '') !== 'serve') {
    throw new RuntimeException('Run through playwright.crm-sms.config.ts.');
}
if (! is_file($database)) {
    touch($database);
}
Artisan::call('migrate:fresh', ['--force' => true]);
fwrite(STDOUT, Artisan::output());
User::withoutEvents(fn () => User::factory()->create([
    'name' => 'Johnny QA', 'email' => 'crm.sms.sdr@example.test', 'password' => 'CrmSmsQA!2026',
    'role' => 'sdr', 'email_verified_at' => now(),
]));
foreach (['desktop', 'mobile'] as $index => $variant) {
    Lead::withoutEvents(fn () => Lead::create([
        'name' => 'SMS QA '.$variant, 'lead_type' => 'family', 'status' => 'new', 'source' => 'manual_crm',
        'phone' => '+1919555100'.($index + 1), 'email' => 'sms.'.$variant.'@example.test',
    ]));
}
$server = new Process([PHP_BINARY, '-S', '127.0.0.1:8033', '-t', $root.'/public', __FILE__], $root);
$server->setTimeout(null);
exit($server->run(fn ($type, $buffer) => fwrite($type === Process::ERR ? STDERR : STDOUT, $buffer)));
