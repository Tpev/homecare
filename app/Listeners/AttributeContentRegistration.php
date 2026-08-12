<?php

namespace App\Listeners;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Carbon;

class AttributeContentRegistration
{
    public function handle(Registered $event): void
    {
        if (! $event->user instanceof User || ! app()->bound('request') || ! request()->hasSession()) {
            return;
        }

        $attribution = request()->session()->get('content_attribution');
        if (! is_array($attribution) || empty($attribution['blog_post_id'])) {
            return;
        }

        try {
            $clickedAt = Carbon::parse((string) ($attribution['clicked_at'] ?? ''));
        } catch (\Throwable) {
            request()->session()->forget('content_attribution');

            return;
        }
        if ($clickedAt->lt(now()->subDays(30))) {
            request()->session()->forget('content_attribution');

            return;
        }

        $post = BlogPost::query()->find($attribution['blog_post_id']);
        if (! $post) {
            return;
        }

        $post->events()->create([
            'event' => 'signup_completed',
            'session_hash' => null,
            'user_id' => $event->user->id,
            'metadata' => [
                'placement' => $attribution['placement'] ?? null,
                'clicked_at' => $attribution['clicked_at'] ?? null,
                'registered_role' => $event->user->role,
            ],
            'occurred_at' => now(),
        ]);
        request()->session()->forget('content_attribution');
    }
}
