<?php

namespace App\Support;

use App\Models\User;
use App\Services\Messaging\TwilioSmsClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class StaffAccess
{
    public static function email(string $email): string
    {
        return strtolower(trim($email));
    }

    public static function blocksEmail(string $email): bool
    {
        return DB::table('staff_access_blocks')->where('kind', 'email')->where('value', self::email($email))->exists();
    }

    public static function blocksPhone(string $phone): bool
    {
        $phone = TwilioSmsClient::normalizePhone($phone);

        return $phone !== '' && DB::table('staff_access_blocks')->where('kind', 'phone')->where('value', $phone)->exists();
    }

    public static function allowed(Builder $query): Builder
    {
        return $query->whereNull('access_revoked_at')->whereNotIn(
            DB::raw('LOWER(TRIM(email))'),
            DB::table('staff_access_blocks')->select('value')->where('kind', 'email')
        );
    }

    public static function allowsUser(User $user): bool
    {
        // Read current state, including for jobs or Livewire components holding an old model.
        return self::allowed(User::query())->whereKey($user->id)->exists();
    }
}
