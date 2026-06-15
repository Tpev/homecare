<div class="hc-page py-8 space-y-6">
    @if (session('status'))
        <x-alert color="blue">{{ session('status') }}</x-alert>
    @endif

    <x-card>
        <x-slot:header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold">Payment Operations</h1>
                    <p class="mt-1 text-sm text-slate-600">Review authorization, capture, transfer, and refund states.</p>
                </div>
                <div class="text-xs text-slate-500">Rows: {{ $payments->total() }}</div>
            </div>
        </x-slot:header>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
            <x-select.styled
                label="Status"
                wire:model.live="status"
                :options="$statusOptions"
            />
            <x-input
                label="Search"
                placeholder="Family, caregiver, payment intent, transfer id"
                wire:model.blur="search"
            />
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 text-xs text-slate-600">
                Amount values are in cents for precise ops actions.
            </div>
        </div>

        <div class="space-y-3">
            @forelse ($payments as $payment)
                @php
                    $captured = (int) ($payment->amount_captured_cents ?? 0);
                    $refunded = (int) ($payment->amount_refunded_cents ?? 0);
                    $remaining = max(0, $captured - $refunded);
                @endphp
                <article class="rounded-xl border border-slate-200 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-slate-900">Payment #{{ $payment->id }}</p>
                            <p class="text-xs text-slate-500 mt-1">
                                Family: {{ $payment->family?->name }} · Caregiver: {{ $payment->caregiver?->name }}
                            </p>
                            <p class="text-xs text-slate-500">
                                Request #{{ $payment->booking?->care_request_id }} · Booking #{{ $payment->care_booking_id }}
                            </p>
                        </div>
                        <x-badge :text="strtoupper((string) $payment->status)" color="blue" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-2 mt-3 text-xs text-slate-700">
                        <div class="rounded-md border border-slate-200 p-2">Authorized: <strong>{{ number_format(((int) ($payment->amount_authorized_cents ?? 0)) / 100, 2) }} {{ strtoupper($payment->currency) }}</strong></div>
                        <div class="rounded-md border border-slate-200 p-2">Captured: <strong>{{ number_format($captured / 100, 2) }} {{ strtoupper($payment->currency) }}</strong></div>
                        <div class="rounded-md border border-slate-200 p-2">Refunded: <strong>{{ number_format($refunded / 100, 2) }} {{ strtoupper($payment->currency) }}</strong></div>
                        <div class="rounded-md border border-slate-200 p-2">Remaining: <strong>{{ number_format($remaining / 100, 2) }} {{ strtoupper($payment->currency) }}</strong></div>
                    </div>

                    <div class="mt-3 text-xs text-slate-500 space-y-1">
                        <p>Intent: {{ $payment->stripe_payment_intent_id ?: 'N/A' }}</p>
                        <p>Transfer: {{ $payment->stripe_transfer_id ?: 'N/A' }}</p>
                        @if($payment->last_error)
                            <p class="text-rose-600">Last error: {{ $payment->last_error }}</p>
                        @endif
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-2 md:grid-cols-4">
                        <x-input
                            wire:model.defer="refundAmountCents.{{ $payment->id }}"
                            placeholder="Refund cents (blank = full)"
                        />
                        <x-input
                            wire:model.defer="refundReason.{{ $payment->id }}"
                            placeholder="Reason (default: requested_by_customer)"
                        />
                        <x-button
                            color="red"
                            light
                            wire:click="refund({{ $payment->id }})"
                            :disabled="$remaining <= 0"
                            class="justify-center"
                        >
                            Refund
                        </x-button>
                        <x-button
                            color="green"
                            light
                            wire:click="retryTransfer({{ $payment->id }})"
                            :disabled="! in_array($payment->status, ['captured', 'transfer_failed'])"
                            class="justify-center"
                        >
                            Retry transfer
                        </x-button>
                    </div>
                </article>
            @empty
                <p class="text-sm text-slate-600">No payments found for this filter.</p>
            @endforelse
        </div>

        <x-slot:footer>
            {{ $payments->links() }}
        </x-slot:footer>
    </x-card>
</div>
