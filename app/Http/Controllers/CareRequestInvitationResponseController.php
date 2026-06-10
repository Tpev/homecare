<?php

namespace App\Http\Controllers;

use App\Models\CareRequestConversation;
use App\Models\CareRequestInvitation;
use App\Services\Marketplace\CareRequestInvitationResponseService;
use Illuminate\Http\RedirectResponse;

class CareRequestInvitationResponseController extends Controller
{
    public function accept(
        CareRequestInvitation $invitation,
        CareRequestInvitationResponseService $responder
    ): RedirectResponse {
        $result = $responder->accept($invitation, auth()->user(), 'work_inbox_form');

        $conversation = $result['conversation'] ?? null;
        $redirect = $conversation instanceof CareRequestConversation
            ? route('messages.show', $conversation->id)
            : route('caregiver.work-inbox.index');

        return redirect($redirect)->with('status', $result['message']);
    }

    public function decline(
        CareRequestInvitation $invitation,
        CareRequestInvitationResponseService $responder
    ): RedirectResponse {
        $result = $responder->decline($invitation, auth()->user(), 'work_inbox_form');

        return redirect()
            ->route('caregiver.work-inbox.index')
            ->with('status', $result['message']);
    }
}
