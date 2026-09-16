<?php

// Isolated local QA fixtures; this script refuses the normal application database.
require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$expected = realpath(storage_path('app/onboarding-preview.sqlite'));
if (! app()->environment('local') || config('database.default') !== 'sqlite'
    || ! $expected || realpath(config('database.connections.sqlite.database')) !== $expected
    || config('mail.default') !== 'array') {
    throw new RuntimeException('Use local-preview.ps1 with the isolated SQLite database and array mailer.');
}

Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
echo Illuminate\Support\Facades\Artisan::output();

foreach (['onboarding.preview@example.test' => 'family', 'existing.preview@example.test' => 'family', 'onboarding.admin@example.test' => 'admin'] as $email => $role) {
    $user = App\Models\User::query()->firstOrCreate(['email' => $email], [
        'name' => $role === 'admin' ? 'Onboarding Preview Admin' : 'Preview Family',
        'role' => $role, 'phone' => '(984) 555-0100',
        'password' => Illuminate\Support\Facades\Hash::make('LoLoPreview!2026'),
    ]);
    $user->forceFill(['email_verified_at' => now()])->save();
    if ($email === 'onboarding.preview@example.test') {
        Illuminate\Support\Facades\DB::transaction(fn () => app(App\Services\Family\FamilyOnboardingService::class)->enrollRegistration($user));
    }
}
app(Database\Seeders\CareTaskSeeder::class)->run();
echo "Isolated onboarding preview ready at http://127.0.0.1:8033\n";
