<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$expected = realpath(getcwd().'/.tmp/find-care-screen-audit-2026-09-08/find-care-audit.sqlite');
$actual = realpath((string) config('database.connections.sqlite.database'));
if (! $app->environment('playwright') || config('database.default') !== 'sqlite' || ! $expected || $actual !== $expected) {
    throw new RuntimeException('Only the isolated September 8 screenshot database may receive this synthetic admin.');
}
$email = 'admin.find-care-audit@example.com';
$admin = User::firstOrNew(['email' => $email]);
$admin->forceFill([
    'name' => 'LoLo Demo Administrator',
    'role' => 'admin',
    'password' => Hash::make('password'),
    'email_verified_at' => now(),
    'onboarding_completed_at' => now(),
])->save();
echo json_encode(['id' => $admin->id, 'name' => $admin->name, 'email' => $email, 'password' => 'password', 'role' => 'admin'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
