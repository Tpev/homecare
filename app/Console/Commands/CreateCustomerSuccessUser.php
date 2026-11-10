<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\StaffAccess;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateCustomerSuccessUser extends Command
{
    protected $signature = 'customer-care:create-user {email : Staff login email} {--name= : Staff display name}';

    protected $description = 'Create a Customer Success account without sending email or changing existing accounts.';

    public function handle(): int
    {
        $email = StaffAccess::email((string) $this->argument('email'));
        if (strlen($email) > 255 || ! filter_var($email, FILTER_VALIDATE_EMAIL) || StaffAccess::blocksEmail($email)) {
            $this->error('This email is invalid or blocked from staff access.');

            return self::FAILURE;
        }
        $existing = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if ($existing) {
            if (! StaffAccess::allowsUser($existing) || ! $existing->canManageCustomerCare()) {
                $this->error('This email belongs to an existing account. Its role and login have not been changed.');

                return self::FAILURE;
            }
            $this->info('This staff account already has Customer Success access. Its login is unchanged.');

            return self::SUCCESS;
        }
        $name = trim((string) $this->option('name'));
        if ($name === '' || mb_strlen($name) > 255 || ! $this->input->isInteractive()) {
            $this->error('Supply --name and run interactively to enter the initial password securely.');

            return self::FAILURE;
        }
        $password = (string) $this->secret('Initial password (at least 12 characters)');
        if (mb_strlen($password) < 12) {
            $this->error('The initial password must contain at least 12 characters.');

            return self::FAILURE;
        }
        User::create(['name' => $name, 'email' => $email, 'password' => Hash::make($password),
            'role' => 'customer_success', 'email_verified_at' => now()]);
        $this->info('Customer Success account created. No email was sent. Workspace: /admin/customer-care');

        return self::SUCCESS;
    }
}
