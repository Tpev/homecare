<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceNotificationDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class NotificationEmailTrackingController extends Controller
{
    public function open(MarketplaceNotificationDelivery $delivery, string $token): Response
    {
        abort_unless($this->isValidTrackedDelivery($delivery, $token), 404);

        $delivery->forceFill([
            'open_count' => ((int) $delivery->open_count) + 1,
            'opened_at' => $delivery->opened_at ?: now(),
        ])->save();

        return response(base64_decode('R0lGODlhAQABAIABAP///wAAACwAAAAAAQABAAACAkQBADs='), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function click(MarketplaceNotificationDelivery $delivery, string $token): RedirectResponse
    {
        abort_unless($this->isValidTrackedDelivery($delivery, $token), 404);

        $delivery->forceFill([
            'click_count' => ((int) $delivery->click_count) + 1,
            'clicked_at' => $delivery->clicked_at ?: now(),
        ])->save();

        $target = (string) data_get($delivery->payload, 'tracking.target_url', route('dashboard'));
        if ($target === '' || ! filter_var($target, FILTER_VALIDATE_URL)) {
            $target = route('dashboard');
        }

        return redirect()->away($target);
    }

    private function isValidTrackedDelivery(MarketplaceNotificationDelivery $delivery, string $token): bool
    {
        if ($delivery->channel !== 'email') {
            return false;
        }

        $expected = (string) data_get($delivery->payload, 'tracking.token', '');

        return $expected !== '' && hash_equals($expected, $token);
    }
}

