<?php

namespace App\Services\CustomerCare;

use App\Models\User;
use App\Support\StaffAccess;
use Illuminate\Auth\Access\AuthorizationException;

class CustomerCareAccess
{
    public static function authorize(User $actor): void
    {
        $current = $actor->fresh();
        if (! ($current?->canManageCustomerCare() && StaffAccess::allowsUser($current))) {
            throw new AuthorizationException('Customer Success access is required.');
        }
    }
}
