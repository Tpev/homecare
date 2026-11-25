<?php

use App\Models\CareAssistanceApplication;
use App\Models\CareTask;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Process\Process;

$root = dirname(__DIR__, 2);
$database = str_replace('\\', '/', $root.'/storage/app/care-assistance-qa.sqlite');
$cache = str_replace('\\', '/', $root.'/storage/app/care-assistance-qa-config.php');
if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'sqlite'
    || str_replace('\\', '/', (string) getenv('DB_DATABASE')) !== $database
    || str_replace('\\', '/', (string) getenv('APP_CONFIG_CACHE')) !== $cache
    || getenv('DB_URL') !== '' || getenv('STRIPE_BYPASS') !== 'true'
    || getenv('MAIL_MAILER') !== 'array' || is_file($cache)) {
    throw new RuntimeException('Care Assistance QA requires its isolated database and uncached test configuration.');
}
if (PHP_SAPI === 'cli-server') {
    $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $public = realpath($root.'/public');
    $file = realpath($root.'/public'.$path);
    if ($file && $public && str_starts_with(str_replace('\\', '/', $file), str_replace('\\', '/', $public).'/')
        && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
        return false;
    }
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'sqlite') {
    throw new RuntimeException('Unexpected Care Assistance QA environment.');
}
config(['database.connections.sqlite.url' => null, 'database.connections.sqlite.database' => $database,
    'services.stripe.secret' => null, 'services.stripe.publishable_key' => null, 'services.stripe.bypass' => true,
    'services.twilio.bypass' => true, 'services.slack.ops.enabled' => false, 'marketplace.ops_alert_recipients' => []]);
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
    throw new RuntimeException('Use playwright.care-assistance.config.ts.');
}
if (! is_file($database)) {
    touch($database);
}
Artisan::call('migrate:fresh', ['--force' => true]);
User::factory()->create(['name' => 'Assistance CS', 'email' => 'assistance.cs@example.test', 'role' => 'customer_success',
    'password' => 'CareAssistanceQA!2026', 'email_verified_at' => now()]);
foreach (['desktop', 'mobile'] as $variant) {
    User::factory()->create(['name' => 'Assistance Family '.$variant, 'email' => 'assistance.'.$variant.'@example.test',
        'role' => 'family', 'city' => 'Raleigh', 'state' => 'NC']);
    CareAssistanceApplication::create(['name' => 'Assistance Family '.$variant, 'email' => 'assistance.'.$variant.'@example.test',
        'phone' => '9195550100', 'relationship' => 'self', 'city' => 'Raleigh', 'zip' => '27601', 'care_needs' => ['companionship'],
        'weekly_hours' => '5_10', 'start_timeframe' => 'asap', 'hardship_details' => 'Synthetic private application.',
        'status' => 'approved', 'consented_at' => now(), 'reviewed_at' => now()]);
}
CareTask::create(['name' => 'Companionship']);
fwrite(STDOUT, "Care Assistance synthetic fixtures ready; external services disabled.\n");
$server = new Process([PHP_BINARY, '-S', '127.0.0.1:8027', '-t', $root.'/public', __FILE__], $root, timeout: null);
$server->run(static function (string $type, string $buffer): void {
    if (! preg_match('/127\.0\.0\.1:[0-9]+ (?:Accepted|Closing)/', $buffer)) {
        fwrite($type === Process::ERR ? STDERR : STDOUT, $buffer);
    }
});
exit($server->getExitCode() ?? 1);
