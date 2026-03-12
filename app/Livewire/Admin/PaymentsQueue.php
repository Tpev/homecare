<?php

namespace App\Livewire\Admin;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBookingPayment;
use App\Services\Payments\BookingPaymentService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class PaymentsQueue extends Component
{
    use WithPagination;

    public string $status = 'all';
    public string $search = '';

    /**
     * @var array<int, string>
     */
    public array $refundAmountCents = [];

    /**
     * @var array<int, string>
     */
    public array $refundReason = [];

    public function retryTransfer(int $paymentId): void
    {
        $payment = CareBookingPayment::query()
            ->with('booking')
            ->findOrFail($paymentId);

        try {
            app(BookingPaymentService::class)->retryTransfer($payment);
            session()->flash('status', 'Transfer retry submitted.');
        } catch (PaymentException $e) {
            session()->flash('status', $e->userMessage);
        }
    }

    public function refund(int $paymentId): void
    {
        $payment = CareBookingPayment::query()
            ->with('booking')
            ->findOrFail($paymentId);

        if (! $payment->booking) {
            session()->flash('status', 'Booking not found for this payment.');

            return;
        }

        $amountInput = trim((string) ($this->refundAmountCents[$paymentId] ?? ''));
        $amountCents = null;

        if ($amountInput !== '') {
            if (! ctype_digit($amountInput)) {
                session()->flash('status', 'Refund amount must be in cents, digits only.');

                return;
            }

            $amountCents = (int) $amountInput;
        }

        $reason = trim((string) ($this->refundReason[$paymentId] ?? 'requested_by_customer'));
        if ($reason === '') {
            $reason = 'requested_by_customer';
        }

        try {
            app(BookingPaymentService::class)->refundForBooking($payment->booking, $amountCents, $reason);
            session()->flash('status', 'Refund processed.');
            unset($this->refundAmountCents[$paymentId], $this->refundReason[$paymentId]);
        } catch (PaymentException $e) {
            session()->flash('status', $e->userMessage);
        }
    }

    public function render()
    {
        $payments = CareBookingPayment::query()
            ->with([
                'booking:id,care_request_id',
                'family:id,name,email',
                'caregiver:id,name,email',
            ])
            ->when($this->status !== 'all', fn ($query) => $query->where('status', $this->status))
            ->when($this->search !== '', function ($query): void {
                $term = trim($this->search);
                $query->where(function ($inner) use ($term): void {
                    $inner->where('stripe_payment_intent_id', 'like', '%'.$term.'%')
                        ->orWhere('stripe_transfer_id', 'like', '%'.$term.'%')
                        ->orWhereHas('family', fn ($q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%'))
                        ->orWhereHas('caregiver', fn ($q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%'));
                });
            })
            ->latest()
            ->paginate(25);

        return view('livewire.admin.payments-queue', [
            'payments' => $payments,
            'statusOptions' => [
                ['label' => 'All', 'value' => 'all'],
                ['label' => 'Authorized', 'value' => CareBookingPayment::STATUS_AUTHORIZED],
                ['label' => 'Action required', 'value' => CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED],
                ['label' => 'Reauth required', 'value' => CareBookingPayment::STATUS_REAUTH_REQUIRED],
                ['label' => 'Captured', 'value' => CareBookingPayment::STATUS_CAPTURED],
                ['label' => 'Transfer failed', 'value' => CareBookingPayment::STATUS_TRANSFER_FAILED],
                ['label' => 'Transferred', 'value' => CareBookingPayment::STATUS_TRANSFERRED],
                ['label' => 'Partially refunded', 'value' => CareBookingPayment::STATUS_PARTIALLY_REFUNDED],
                ['label' => 'Refunded', 'value' => CareBookingPayment::STATUS_REFUNDED],
                ['label' => 'Failed', 'value' => CareBookingPayment::STATUS_FAILED],
                ['label' => 'Cancelled', 'value' => CareBookingPayment::STATUS_CANCELLED],
            ],
        ]);
    }
}
