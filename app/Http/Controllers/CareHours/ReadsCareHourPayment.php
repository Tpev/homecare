<?php

namespace App\Http\Controllers\CareHours;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareHourPurchase;
use App\Services\CareHours\CareHourPurchaseService;
use Stripe\Exception\ApiErrorException;

trait ReadsCareHourPayment
{
    private function readPayment(CareHourPurchase $purchase, CareHourPurchaseService $purchases): CareHourPurchase
    {
        try {
            return $purchases->refresh($purchase);
        } catch (PaymentException|ApiErrorException $exception) {
            report($exception);
            session()->now('status', 'We could not check the payment provider just now. Your purchase is saved; please check again shortly.');

            return $purchase->fresh();
        }
    }
}
