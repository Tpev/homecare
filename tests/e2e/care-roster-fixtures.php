<?php

use App\Models\CaregiverProfile;
use App\Models\CareTask;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use App\Services\CustomerCare\ManagedCareService;
use App\Services\Messaging\TwilioSmsClient;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

$root = dirname(__DIR__, 2);
$database = str_replace('\\', '/', $root.'/storage/app/care-roster-qa.sqlite');
$cache = str_replace('\\', '/', $root.'/storage/app/care-roster-qa-config.php');
if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'sqlite'
    || str_replace('\\', '/', (string) getenv('DB_DATABASE')) !== $database
    || str_replace('\\', '/', (string) getenv('APP_CONFIG_CACHE')) !== $cache
    || getenv('DB_URL') !== '' || is_file($cache)) {
    throw new RuntimeException('Roster browser tests require their isolated SQLite database and uncached configuration.');
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
config(['database.connections.sqlite.url' => null, 'database.connections.sqlite.database' => $database,
    'services.stripe.secret' => null, 'services.stripe.bypass' => true, 'services.slack.ops.enabled' => false,
    'marketplace.ops_alert_recipients' => [], 'marketplace.caregiver_prelaunch_mode' => false,
    'services.twilio.sms_from' => '+19845550100', 'services.openai.ca_bundle' => null]);
Mail::fake();
Notification::fake();
Http::preventStrayRequests();
Http::fake(['api.openai.test/*' => function ($request) {
    $input = json_decode($request['input'], true);
    $data = isset($input['candidates']) ? ['assessments' => array_map(fn ($candidate) => [
        'caregiver_user_id' => $candidate['caregiver_user_id'], 'fit' => 'strong',
        'why' => 'Reports bathing and transfer experience. Saved afternoon hours cover this request, and the service area is nearby.',
        'concerns' => ['Availability has not yet been confirmed for this request.'],
        'confirm' => ['Confirm the first visit and recurring commitment.', 'Check supporting qualification documents.'],
        'evidence_ids' => [$candidate['records'][0]['evidence_id']], 'avoid_contact' => false,
        'invitation_body' => 'The LoLo team is looking for bathing assistance in Durham on Monday afternoon. Are you interested and available?',
        'sms_body' => 'Hi! LoLo Care has an afternoon care opportunity in Durham. Please review the invitation and let us know whether the hours work for you.',
    ], $input['candidates'])] : ['summary' => 'Start with the nearby caregivers below. Confirm the visit duration and qualifications before sharing profiles with the family.',
        'customer_questions' => ['Is a two-hour visit confirmed?', 'What mobility assistance is needed during bathing?'],
        'caregiver_user_ids' => array_column($input['assessments'], 'caregiver_user_id')];

    return Http::response(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode($data)]]]]]);
}]);
$app->instance(TwilioSmsClient::class, new class extends TwilioSmsClient
{
    public function sendMessage(string $to, string $body, ?string $from = null, ?string $statusCallbackUrl = null): array
    {
        return ['sid' => 'SMsynthetic'.Str::random(20), 'status' => 'queued'];
    }
});
if (PHP_SAPI === 'cli-server') {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['PHP_SELF'] = '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $root.'/public/index.php';
    $app->handleRequest(Request::capture());

    return;
}
if (($argv[1] ?? '') !== 'serve') {
    throw new RuntimeException('Use playwright.care-roster.config.ts.');
}
if (! is_file($database)) {
    touch($database);
}
Artisan::call('migrate:fresh', ['--force' => true]);
$actor = User::factory()->create(['name' => 'Roster CS', 'email' => 'roster.cs@example.test', 'role' => 'customer_success',
    'password' => 'RosterBrowserQA!2026', 'email_verified_at' => now()]);
foreach (['desktop', 'mobile'] as $variant) {
    $customer = User::factory()->create(['name' => 'Roster Family '.$variant, 'email' => 'roster.family.'.$variant.'@example.test', 'role' => 'family']);
    app(ManagedCareService::class)->save($actor, $customer, ['request_key' => (string) Str::uuid(), 'title' => 'Bathing assistance '.$variant,
        'scope_of_work' => 'Bathing assistance and dressing.', 'recipient_name' => 'Synthetic recipient', 'address_line1' => '100 Test Street',
        'city' => 'Durham', 'state' => 'NC', 'zip' => '27704', 'request_type' => 'one_time',
        'requested_start_at' => now()->addDays(3)->setTime(15, 0)->toDateTimeString(),
        'requested_end_at' => now()->addDays(3)->setTime(17, 0)->toDateTimeString(), 'status' => 'open',
        'tasks' => [CareTask::firstOrCreate(['name' => 'Bathing'])->id]]);
}
foreach (['Taylor Morgan', 'Jordan Ellis', 'Casey Avery'] as $name) {
    $user = User::factory()->create(['name' => $name, 'role' => 'caregiver', 'city' => 'Durham', 'state' => 'NC', 'phone' => '+1984555'.random_int(1000, 9999), 'sms_opt_in' => true]);
    $profile = CaregiverProfile::create(['user_id' => $user->id, 'slug' => 'qa-roster-'.$user->id, 'status' => 'active',
        'screening_initial_approved_at' => now(), 'bio' => 'Experienced caregiver supporting bathing, dressing and safe transfers.',
        'years_experience' => 5, 'service_area_zip' => '27704', 'service_radius_miles' => 20, 'is_accepting_new_clients' => true, 'identity_verified_at' => now()]);
    $profile->skills()->sync([Skill::firstOrCreate(['name' => 'Personal care'])->id]);
    $profile->languages()->sync([Language::firstOrCreate(['name' => 'English'])->id]);
    foreach ([1, 5] as $day) {
        $profile->availabilities()->create(['day_of_week' => $day, 'start_time' => '09:00', 'end_time' => '18:00']);
    }
}
fwrite(STDOUT, "Synthetic roster fixtures ready; all external sending disabled.\n");
$server = new Process([PHP_BINARY, '-S', '127.0.0.1:8041', '-t', $root.'/public', __FILE__], $root, timeout: null);
$server->run(static function (string $type, string $buffer): void {
    if (! preg_match('/127\.0\.0\.1:[0-9]+ (?:Accepted|Closing)/', $buffer)) {
        fwrite($type === Process::ERR ? STDERR : STDOUT, $buffer);
    }
});
exit($server->getExitCode() ?? 1);
