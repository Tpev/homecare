<?php

namespace App\Http\Controllers;

use App\Exceptions\Payments\PaymentException;
use App\Services\CustomerOnboarding\FamilyPaymentSetupService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class PublicFamilyPaymentSetupController extends Controller
{
    public function show(Request $request, string $token, FamilyPaymentSetupService $setup): Response
    {
        return $this->page($request, $token, $setup);
    }

    public function start(Request $request, string $token, FamilyPaymentSetupService $setup): BaseResponse
    {
        $link = $setup->findByToken($token);
        if (! $link || ! $link->isUsable() || $link->familyAccount?->status !== 'active') {
            return $this->page($request, $token, $setup);
        }
        if (! in_array($request->input('consent'), ['1', 'yes', 'on', 1, true], true)) {
            return $this->page($request, $token, $setup, 'Please agree to the payment terms before continuing.', 422);
        }
        try {
            return $this->privateResponse(redirect()->away($setup->start($link)));
        } catch (PaymentException $exception) {
            report($exception);

            return $this->page($request, $token, $setup, $exception->userMessage, 422);
        }
    }

    public function complete(Request $request, string $token, FamilyPaymentSetupService $setup): Response
    {
        // The browser can only display the webhook's saved result. Query-string
        // session ids or "success" flags never activate a payment method.
        return $this->page($request, $token, $setup, returning: true);
    }

    private function page(
        Request $request,
        string $token,
        FamilyPaymentSetupService $setup,
        ?string $error = null,
        int $statusCode = 200,
        bool $returning = false,
    ): Response {
        $link = $setup->findByToken($token);
        $state = ! $link || $link->familyAccount?->status !== 'active' ? 'unavailable' : match ($link->status()) {
            'completed' => 'completed',
            'active' => $returning && $link->stripe_checkout_session_id ? 'pending' : 'ready',
            default => 'unavailable',
        };

        return $this->privateResponse(response()->view('public.family-payment-setup', [
            'state' => $state,
            'startUrl' => $state === 'ready' ? route('public.family-payment-setup.start', ['token' => $token]) : null,
            'error' => $error,
            'cancelled' => $request->query('checkout') === 'cancel',
        ], $state === 'unavailable' ? 410 : $statusCode));
    }

    private function privateResponse(BaseResponse $response): BaseResponse
    {
        $response->headers->set('Cache-Control', 'no-store, private, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src 'self'; form-action 'self' https://checkout.stripe.com; base-uri 'none'; frame-ancestors 'none'");

        return $response;
    }
}
