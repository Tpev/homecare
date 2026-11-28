<?php

namespace App\Services\CustomerCare;

use App\Models\CareRequest;
use App\Models\User;
use App\Support\StaffAccess;

class CareRosterAccess
{
    public static function allows(?User $actor): bool
    {
        $actor = $actor?->fresh();

        return $actor && $actor->canUseCareRoster() && StaffAccess::allowsUser($actor);
    }

    public static function authorize(?User $actor, ?CareRequest $request = null): void
    {
        abort_unless(self::allows($actor), 403);
        if ($request) {
            $request = $request->fresh();
            abort_unless($request && $request->staff_managed && ! $request->staff_archived_at, 403);
        }
    }
}
