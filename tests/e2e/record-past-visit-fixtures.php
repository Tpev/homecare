<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (! app()->environment('playwright') || config('database.default') !== 'sqlite'
    || realpath(config('database.connections.sqlite.database')) !== realpath(base_path('tmp/record-past-visit-e2e/test.sqlite'))
    || ! config('services.stripe.bypass')) {
    throw new RuntimeException('These fixtures may run only against the isolated recorded-visit test database.');
}
Illuminate\Support\Facades\Notification::fake();
Illuminate\Support\Facades\Http::preventStrayRequests();
App\Models\User::factory()->create(['name' => 'Visit Test Admin', 'email' => 'recorded.admin@example.test', 'password' => bcrypt('password'), 'role' => 'admin']);
$owner = App\Models\User::factory()->create(['name' => 'John Example', 'email' => 'recorded.family@example.test', 'role' => 'family']);
$family = App\Models\FamilyAccount::create(['owner_user_id' => $owner->id, 'status' => 'active']);
$caregiver = App\Models\User::factory()->create(['name' => 'Charles Example', 'email' => 'recorded.caregiver@example.test', 'role' => 'caregiver']);
App\Models\CaregiverProfile::create(['user_id' => $caregiver->id, 'status' => 'active', 'platform_hourly_rate' => 30,
    'stripe_connect_account_id' => 'acct_recorded_browser', 'stripe_charges_enabled' => true, 'stripe_payouts_enabled' => true]);
$request = new App\Models\CareRequest(['family_user_id' => $owner->id, 'family_account_id' => $family->id,
    'title' => 'Transportation and companionship', 'request_type' => 'one_time', 'status' => 'filled',
    'address_line1' => '123 Example Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27609']);
$request->saveQuietly();
App\Models\CareRecipient::create(['care_request_id' => $request->id, 'full_name' => 'John Example', 'recipient_is_requester' => true, 'relationship_to_family' => 'Self']);
$task = App\Models\CareTask::firstOrCreate(['name' => 'Transportation']);
$request->tasks()->sync([$task->id]);
echo "Recorded-visit browser fixtures ready.\n";
