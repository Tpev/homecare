<?php

use App\Models\CareBooking;
use App\Models\CaregiverCrmRecord;
use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Process\Process;

$root = dirname(__DIR__, 2);
$database = str_replace('\\', '/', $root.'/storage/app/caregiver-crm-qa.sqlite');
$cache = str_replace('\\', '/', $root.'/storage/app/caregiver-crm-qa-config.php');

// Reject any accidental use of a real database before bootstrapping Laravel.
if (getenv('APP_ENV') !== 'testing'
    || getenv('DB_CONNECTION') !== 'sqlite'
    || str_replace('\\', '/', (string) getenv('DB_DATABASE')) !== $database
    || str_replace('\\', '/', (string) getenv('APP_CONFIG_CACHE')) !== $cache
    || getenv('DB_URL') !== ''
    || getenv('STRIPE_BYPASS') !== 'true'
    || getenv('MAIL_MAILER') !== 'array'
    || is_file($cache)
) {
    throw new RuntimeException('Caregiver CRM QA requires its isolated SQLite database and uncached testing environment.');
}

if (PHP_SAPI === 'cli-server') {
    $requestPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $public = realpath($root.'/public');
    $file = realpath($root.'/public'.$requestPath);
    if ($file && $public
        && str_starts_with(str_replace('\\', '/', $file), str_replace('\\', '/', $public).'/')
        && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php'
    ) {
        return false;
    }
}

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing') || config('database.default') !== 'sqlite') {
    throw new RuntimeException('Unexpected application environment in caregiver CRM QA.');
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
    throw new RuntimeException('Run this guarded fixture through playwright.caregiver-crm.config.ts.');
}
if (! is_file($database)) {
    touch($database);
}
Artisan::call('migrate:fresh', ['--force' => true]);
fwrite(STDOUT, Artisan::output());

foreach (['admin', 'sales'] as $role) {
    User::withoutEvents(fn () => User::factory()->create([
        'name' => 'Caregiver CRM '.ucfirst($role),
        'email' => 'caregiver.crm.'.$role.'@example.test',
        'password' => 'CaregiverCrmQA!2026',
        'role' => $role, 'email_verified_at' => now(),
    ]));
}
$profile = static function (string $name, array $attributes = []): User {
    $user = User::withoutEvents(fn () => User::factory()->create([
        'name' => $name, 'email' => str($name)->slug().'@example.test',
        'role' => 'caregiver', 'phone' => '+19195550123',
        'city' => 'Raleigh', 'state' => 'NC',
    ]));
    CaregiverProfile::withoutEvents(fn () => CaregiverProfile::create([
        'user_id' => $user->id, 'status' => 'active',
        'screening_initial_approved_at' => now()->subDays(4),
        'service_area_zip' => '27601', 'service_radius_miles' => 20,
        ...$attributes,
    ]));

    return $user;
};
$profile('Morgan New');
$applied = $profile('Robin Applied');
$hired = $profile('Avery Hired');
$legacyHire = $profile('Legacy Hire');
$profile('Pending Hidden', ['screening_initial_approved_at' => null]);
$profile('Suspended Hidden', ['screening_suspended_at' => now()]);
foreach (['desktop', 'mobile'] as $variant) {
    $profile('Taylor Followup '.$variant);
}

$family = User::withoutEvents(fn () => User::factory()->create(['role' => 'family', 'name' => 'Synthetic Family']));
// Distinct engagements make the latest dates visibly different from the first.
foreach ([
    ['2026-08-12 09:15:00', '2026-08-10 10:30:00', '2026-08-11 14:45:00'],
    ['2026-09-14 09:15:00', '2026-09-12 10:30:00', '2026-09-15 14:45:00'],
] as [$appliedAt, $hiredAppliedAt, $hiredAt]) {
    $request = CareRequest::withoutEvents(fn () => CareRequest::create([
        'family_user_id' => $family->id, 'title' => 'Synthetic companionship request',
        'status' => CareRequest::STATUS_OPEN, 'request_type' => 'one_time',
        'address_line1' => '123 Synthetic Lane', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
        'is_system_generated' => false,
    ]));
    $appliedApplication = CareRequestApplication::withoutEvents(fn () => CareRequestApplication::create([
        'care_request_id' => $request->id, 'caregiver_user_id' => $applied->id,
        'status' => CareRequestApplication::STATUS_WITHDRAWN, 'proposed_rate' => 30,
    ]));
    $appliedApplication->forceFill(['created_at' => $appliedAt, 'updated_at' => $appliedAt])->saveQuietly();
    $application = CareRequestApplication::withoutEvents(fn () => CareRequestApplication::create([
        'care_request_id' => $request->id, 'caregiver_user_id' => $hired->id,
        'status' => CareRequestApplication::STATUS_HIRED, 'proposed_rate' => 30,
    ]));
    $application->forceFill(['created_at' => $hiredAppliedAt, 'updated_at' => $hiredAppliedAt])->saveQuietly();
    $booking = CareBooking::withoutEvents(fn () => CareBooking::create([
        'family_user_id' => $family->id, 'caregiver_user_id' => $hired->id,
        'care_request_id' => $request->id, 'care_request_application_id' => $application->id,
        'status' => CareBooking::STATUS_COMPLETED,
        'scheduled_start_at' => Carbon::parse($hiredAt)->addDay(),
        'scheduled_end_at' => Carbon::parse($hiredAt)->addDay()->addHour(),
        'completed_at' => Carbon::parse($hiredAt)->addDay()->addHour(),
        'expected_minutes' => 60, 'worked_minutes' => 60,
    ]));
    $booking->forceFill(['created_at' => $hiredAt, 'updated_at' => $hiredAt])->saveQuietly();
}
$legacyApplication = CareRequestApplication::withoutEvents(fn () => CareRequestApplication::create([
    'care_request_id' => $request->id, 'caregiver_user_id' => $legacyHire->id,
    'status' => CareRequestApplication::STATUS_HIRED, 'proposed_rate' => 30,
]));
$legacyApplication->forceFill(['created_at' => '2026-08-02 11:00:00', 'updated_at' => '2026-08-02 11:00:00'])->saveQuietly();
CaregiverCrmRecord::create([
    'caregiver_user_id' => $applied->id, 'priority' => 'high',
    'next_action' => 'Review nearby care requests',
    'next_follow_up_at' => now()->subDay(), 'version' => 1,
]);

fwrite(STDOUT, "Caregiver CRM synthetic fixtures ready; external services are disabled.\n");
$server = new Process([
    PHP_BINARY, '-S', '127.0.0.1:8020', '-t', $root.'/public', __FILE__,
], $root, timeout: null);
$server->run(static function (string $type, string $buffer): void {
    foreach (explode("\n", $buffer) as $line) {
        if ($line !== '' && ! preg_match('/^\[[^\]]+\] 127\.0\.0\.1:[0-9]+ (?:Accepted|Closing|\[[0-9]+\]:)/', $line)) {
            fwrite($type === Process::ERR ? STDERR : STDOUT, $line."\n");
        }
    }
});
exit($server->getExitCode() ?? 1);
