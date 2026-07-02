<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateSdrUser extends Command
{
    protected $signature = 'crm:create-sdr-user
        {email : SDR user email}
        {--name= : SDR user display name}
        {--password= : Temporary password. Generated if omitted.}';

    protected $description = 'Create or update an SDR-only calling user.';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Enter a valid email address.');

            return self::FAILURE;
        }

        $name = trim((string) $this->option('name'));
        if ($name === '') {
            $name = $this->nameFromEmail($email);
        }

        $password = (string) $this->option('password');
        if ($password === '') {
            $password = Str::password(16);
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role' => 'sdr',
                'email_verified_at' => now(),
            ]
        );

        $this->info(($user->wasRecentlyCreated ? 'Created' : 'Updated').' SDR calling user:');
        $this->line('Name: '.$user->name);
        $this->line('Email: '.$user->email);
        $this->line('Role: '.$user->role);
        $this->line('Temporary password: '.$password);
        $this->warn('Share the temporary password securely and ask the user to change it after first login.');

        return self::SUCCESS;
    }

    private function nameFromEmail(string $email): string
    {
        $localPart = (string) str($email)->before('@');

        return str($localPart)
            ->replaceMatches('/[._-]+/', ' ')
            ->title()
            ->toString();
    }
}
