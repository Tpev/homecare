<?php

namespace App\Listeners;

use App\Models\AiSupportActionPreview;
use App\Models\AiSupportGuidedTask;
use App\Models\AiSupportMessageAction;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\DB;

class InvalidateAiSupportConfirmationsOnLogout
{
    public function handle(Logout $event): void
    {
        $userId = $event->user?->getAuthIdentifier();
        if (! $userId) {
            return;
        }

        DB::transaction(function () use ($userId): void {
            $now = now();
            AiSupportActionPreview::query()
                ->where('actor_user_id', $userId)
                ->whereNull('content_deleted_at')
                ->update([
                    'preview_payload' => null,
                    'invalidated_at' => $now,
                    'invalidation_reason' => 'actor_logged_out',
                    'content_deleted_at' => $now,
                ]);
            AiSupportMessageAction::query()
                ->where('actor_user_id', $userId)
                ->where('action_type', AiSupportMessageAction::TYPE_RECAP)
                ->whereNull('consumed_at')
                ->whereNull('invalidated_at')
                ->get()
                ->each(function (AiSupportMessageAction $action) use ($now): void {
                    $payload = (array) $action->payload;
                    $action->forceFill([
                        'payload' => [
                            'recap' => $payload['recap'] ?? null,
                            'can_confirm' => false,
                            'renewal_available' => true,
                        ],
                        'invalidated_at' => $now,
                        'invalidation_reason' => 'actor_logged_out',
                    ])->save();
                });
            AiSupportGuidedTask::query()
                ->where('actor_user_id', $userId)
                ->whereIn('state', AiSupportGuidedTask::OPEN_STATES)
                ->update([
                    'state' => AiSupportGuidedTask::STATE_CANCELLED,
                    'cancelled_at' => $now,
                    'last_result_code' => 'actor_logged_out',
                    'updated_at' => $now,
                ]);
        }, 3);
    }
}
