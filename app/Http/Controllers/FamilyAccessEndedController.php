<?php

namespace App\Http\Controllers;

use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FamilyAccessEndedController extends Controller
{
    public function __invoke(Request $request, FamilyAccountContext $context): View
    {
        $user = $request->user();
        abort_unless($user?->role === 'family'
            && ! $user->isAdministrator()
            && ! $context->membershipFor($user, false)
            && $user->familyAccountMemberships()->exists(), 404);

        return view('family.access-ended');
    }
}
