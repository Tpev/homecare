<?php

namespace App\Http\Controllers;

use App\Models\CareRequest;
use App\Services\Messaging\CareRequestChatService;
use Illuminate\Http\RedirectResponse;

class CareRequestConversationController extends Controller
{
    public function __invoke(CareRequest $careRequest, int $caregiver, CareRequestChatService $chat): RedirectResponse
    {
        $conversation = $chat->open(auth()->user(), $careRequest, $caregiver);

        return new RedirectResponse(route('messages.show', $conversation->id));
    }
}
