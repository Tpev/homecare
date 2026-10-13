<?php

/** A separate synthetic family with no saved profiles, for create-form capture. */
use App\Models\CareRecipientProfile;
use App\Models\FamilyHouseholdProfile;
use App\Models\FamilyRecipientProfile;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountProvisioner;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$normalize = fn ($path) => strtolower(str_replace('\\', '/', (string) realpath($path)));
$database = config('database.connections.sqlite.database');
$expected = getcwd().'/.tmp/find-care-screen-audit/find-care-audit.sqlite';
if (! $app->environment('playwright') || config('database.default') !== 'sqlite'
    || ! realpath($database) || $normalize($database) !== $normalize($expected)) {
    throw new RuntimeException('Empty-profile fixture requires the exact isolated playwright audit SQLite database.');
}
$email = 'nora.empty-profile.find-care-audit@example.com';
if (User::where('email', $email)->exists()) {
    throw new RuntimeException('Empty-profile audit user already exists; refusing duplicate or existing-user changes.');
}
$result = DB::transaction(function () use ($email): array {
    $password = 'password';
    $user = User::create([
        'name' => 'Nora Brooks', 'email' => $email, 'role' => 'family',
        'password' => Hash::make($password), 'email_verified_at' => now(),
        'phone' => '+1 919 555 0178', 'city' => 'Raleigh', 'state' => 'NC',
    ]);
    $account = app(FamilyAccountProvisioner::class)->provisionOwner($user, 'find_care_empty_profile_audit');
    foreach ([CareRecipientProfile::class, FamilyRecipientProfile::class, FamilyHouseholdProfile::class] as $model) {
        if ($model::where('family_account_id', $account->id)->exists()) {
            throw new RuntimeException('The new screenshot family must have no saved profiles.');
        }
    }
    return ['email' => $email, 'password' => $password, 'id' => $user->id];
});
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
