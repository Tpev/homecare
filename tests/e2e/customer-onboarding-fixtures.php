<?php

use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\Lead;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountProvisioner;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Process\Process;

$root = dirname(__DIR__, 2);
$database = str_replace('\\', '/', $root.'/storage/app/customer-onboarding-qa.sqlite');
$cache = str_replace('\\', '/', $root.'/storage/app/customer-onboarding-qa-config.php');

// These checks run before Laravel loads any environment or touches a database.
if (getenv('APP_ENV') !== 'testing'
    || getenv('DB_CONNECTION') !== 'sqlite'
    || str_replace('\\', '/', (string) getenv('DB_DATABASE')) !== $database
    || str_replace('\\', '/', (string) getenv('APP_CONFIG_CACHE')) !== $cache
    || getenv('DB_URL') !== ''
    || getenv('STRIPE_BYPASS') !== 'true'
    || getenv('MAIL_MAILER') !== 'array'
    || is_file($cache)
) {
    throw new RuntimeException('Customer onboarding QA requires its isolated SQLite database and uncached testing environment.');
}

if (PHP_SAPI === 'cli-server') {
    $requestPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $public = realpath($root.'/public');
    $file = realpath($root.'/public'.$requestPath);

    if ($file && $public
        && str_starts_with(str_replace('\\', '/', $file), str_replace('\\', '/', $public).'/')
        && is_file($file)
        && pathinfo($file, PATHINFO_EXTENSION) !== 'php'
    ) {
        return false;
    }
}

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing') || config('database.default') !== 'sqlite') {
    throw new RuntimeException('Unexpected application environment in customer onboarding QA.');
}

config([
    'database.connections.sqlite.url' => null,
    'database.connections.sqlite.database' => $database,
    'mail.default' => 'array',
    'services.stripe.bypass' => true,
    'services.stripe.secret' => null,
    'services.stripe.publishable_key' => null,
    'services.twilio.bypass' => true,
    'services.slack.ops.enabled' => false,
    'marketplace.ops_alert_recipients' => [],
]);

// All outbound notifications and Laravel HTTP calls stay in this process.
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
    throw new RuntimeException('Run this guarded fixture through playwright.customer-onboarding.config.ts.');
}

if (! is_file($database)) {
    touch($database);
}

Artisan::call('migrate:fresh', ['--force' => true]);
fwrite(STDOUT, Artisan::output());

User::withoutEvents(function (): void {
    User::factory()->create([
        'name' => 'Customer Onboarding Admin',
        'email' => 'onboarding.admin@example.test',
        'password' => 'CustomerOnboardingQA!2026',
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);
});

foreach (['desktop', 'mobile'] as $variant) {
    Lead::withoutEvents(fn () => Lead::create([
        'lead_type' => Lead::TYPE_FAMILY,
        'name' => 'Jamie Prospect '.$variant,
        'email' => 'jamie.'.$variant.'@example.test',
        'phone' => '+19195550101',
        'source' => 'manual_crm',
        'status' => 'qualified',
        'priority' => 'normal',
        'submitted_at' => now(),
    ]));
}

$accountOwner = User::factory()->create([
    'name' => 'Morgan Account',
    'email' => 'morgan.account@example.test',
    'role' => 'family',
]);
app(FamilyAccountProvisioner::class)->provisionOwner($accountOwner, 'browser_qa');

$requestOwner = User::factory()->create([
    'name' => 'Robin Request',
    'email' => 'robin.request@example.test',
    'role' => 'family',
]);
$requestAccount = app(FamilyAccountProvisioner::class)->provisionOwner($requestOwner, 'browser_qa');
CareRequest::withoutEvents(fn () => CareRequest::create([
    'family_user_id' => $requestOwner->id,
    'family_account_id' => $requestAccount->id,
    'title' => 'Companionship for Robin',
    'status' => CareRequest::STATUS_OPEN,
    'request_type' => 'one_time',
    'address_line1' => '123 Synthetic Lane',
    'city' => 'Raleigh',
    'state' => 'NC',
    'zip' => '27601',
    'is_system_generated' => false,
]));

$hiredOwner = User::factory()->create([
    'name' => 'Avery Hired',
    'email' => 'avery.hired@example.test',
    'role' => 'family',
]);
$hiredAccount = app(FamilyAccountProvisioner::class)->provisionOwner($hiredOwner, 'browser_qa');
$hiredAccount->forceFill([
    'stripe_customer_id' => 'cus_customer_onboarding_qa',
    'payment_method_status' => 'ready',
    'payment_method_summary' => ['brand' => 'visa', 'last4' => '4242'],
    'payment_method_checked_at' => now(),
])->save();
$caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Synthetic Caregiver']);
$hiredRequest = CareRequest::withoutEvents(fn () => CareRequest::create([
    'family_user_id' => $hiredOwner->id,
    'family_account_id' => $hiredAccount->id,
    'title' => 'Care for Avery',
    'status' => CareRequest::STATUS_FILLED,
    'request_type' => 'one_time',
    'address_line1' => '123 Synthetic Lane',
    'city' => 'Raleigh',
    'state' => 'NC',
    'zip' => '27601',
    'is_system_generated' => false,
    'first_hire_at' => now()->subDays(3),
]));
$application = CareRequestApplication::withoutEvents(fn () => $hiredRequest->applications()->create([
    'caregiver_user_id' => $caregiver->id,
    'status' => 'hired',
    'proposed_rate' => 30,
]));
CareBooking::withoutEvents(fn () => CareBooking::create([
    'family_user_id' => $hiredOwner->id,
    'family_account_id' => $hiredAccount->id,
    'care_request_id' => $hiredRequest->id,
    'care_request_application_id' => $application->id,
    'caregiver_user_id' => $caregiver->id,
    'status' => CareBooking::STATUS_COMPLETED,
    'scheduled_start_at' => now()->subDays(2),
    'scheduled_end_at' => now()->subDays(2)->addHour(),
    'completed_at' => now()->subDays(2)->addHour(),
    'expected_minutes' => 60,
    'worked_minutes' => 60,
]));

fwrite(STDOUT, "Customer onboarding synthetic fixtures ready; external services are disabled.\n");
$server = new Process([
    PHP_BINARY,
    '-S', '127.0.0.1:8018',
    '-t', $root.'/public',
    __FILE__,
], $root, timeout: null);
$server->run(static function (string $type, string $buffer): void {
    foreach (explode("\n", $buffer) as $line) {
        if ($line !== '' && ! preg_match('/^\[[^\]]+\] 127\.0\.0\.1:[0-9]+ (?:Accepted|Closing|\[[0-9]+\]:)/', $line)) {
            fwrite($type === Process::ERR ? STDERR : STDOUT, $line."\n");
        }
    }
});
exit($server->getExitCode() ?? 1);
