<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="font-display text-2xl font-semibold text-slate-900">Billing & Payments</h1>
                <p class="mt-1 text-sm text-slate-600">Add a card once. We pre-authorize at hire, capture at shift confirmation.</p>
            </div>
            <a href="{{ route('family.requests.index') }}" wire:navigate>
                <x-button color="blue" light>Back to requests</x-button>
            </a>
        </div>
    </x-slot>

    <div class="hc-page py-8 space-y-6">
        @if (session('status'))
            <x-alert color="green">{{ session('status') }}</x-alert>
        @endif

        @if ($errors->has('billing'))
            <x-alert color="red">{{ $errors->first('billing') }}</x-alert>
        @endif

        <x-card>
            <x-slot:header>
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-display text-lg font-semibold">Payment method</h2>
                    @if ($billing['ready'])
                        <x-badge text="READY" color="green" />
                    @else
                        <x-badge text="SETUP NEEDED" color="amber" />
                    @endif
                </div>
            </x-slot:header>

            <div class="space-y-4">
                @if ($billing['ready'] && $billing['card'])
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                        <p class="font-semibold">
                            {{ strtoupper($billing['card']['brand']) }} ending in {{ $billing['card']['last4'] }}
                        </p>
                        <p class="mt-1">Expires {{ str_pad((string) $billing['card']['exp_month'], 2, '0', STR_PAD_LEFT) }}/{{ $billing['card']['exp_year'] }}</p>
                    </div>
                @else
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                        No card on file yet. Add one now so you can hire caregivers without checkout friction.
                    </div>
                @endif

                <form method="POST" action="{{ route('family.billing.checkout') }}">
                    @csrf
                    <x-button color="blue" type="submit">
                        {{ $billing['ready'] ? 'Update card' : 'Add card with Stripe' }}
                    </x-button>
                </form>
            </div>
        </x-card>

        <x-card>
            <x-slot:header>
                <h2 class="font-display text-lg font-semibold">How charging works</h2>
            </x-slot:header>
            <div class="space-y-2 text-sm text-slate-700">
                <p>1. When you click hire, LoLo pre-authorizes your card for the expected shift amount.</p>
                <p>2. After the shift is confirmed, we capture the final amount based on actual worked time.</p>
                <p>3. Caregiver payout moves through Stripe Connect once the shift is confirmed.</p>
            </div>
        </x-card>
    </div>
</x-app-layout>
