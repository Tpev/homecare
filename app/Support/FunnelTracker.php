<?php

namespace App\Support;

use App\Models\FunnelEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class FunnelTracker
{
    public static function track(string $event, ?User $user = null, ?Model $entity = null, array $metadata = []): void
    {
        FunnelEvent::query()->create([
            'event' => $event,
            'user_id' => $user?->id,
            'role' => $user?->role,
            'entity_type' => $entity ? $entity::class : null,
            'entity_id' => $entity?->getKey(),
            'metadata' => $metadata === [] ? null : $metadata,
            'occurred_at' => now(),
        ]);
    }
}
