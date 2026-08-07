<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="font-display text-2xl font-semibold text-slate-900">Billing & Payments</h1>
                <p class="mt-1 text-sm text-slate-600">Add a card once. We pre-authorize when you hire, then capture after you approve the visit.</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <a href="{{ route('family.care.history', ['payment' => 'charged']) }}" wire:navigate>
                    <x-button color="green" light class="w-full sm:w-auto">View payment history</x-button>
                </a>
                <a href="{{ route('family.requests.index') }}" wire:navigate>
                    <x-button color="blue" light class="w-full sm:w-auto">Back to Care</x-button>
                </a>
            </div>
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
                        No card on file yet. Add one now so hiring is smooth when you choose a caregiver.
                    </div>
                @endif

                @if ($canManageBilling)
                    <form method="POST" action="{{ route('family.billing.checkout') }}">
                        @csrf
                        <x-button color="blue" type="submit">
                            {{ $billing['ready'] ? 'Update card' : 'Add card securely' }}
                        </x-button>
                    </form>
                @else
                    <p class="text-sm text-slate-600">The family account owner manages the saved card. You can still use it when booking and approving care.</p>
                @endif
            </div>
        </x-card>

        <x-card>
            <x-slot:header>
                <h2 class="font-display text-lg font-semibold">How charging works</h2>
            </x-slot:header>
            <div class="space-y-2 text-sm text-slate-700">
                <p>1. When you hire a caregiver, LoLo pre-authorizes your card for the expected visit amount.</p>
                <p>2. After the caregiver submits hours, you review the timesheet.</p>
                <p>3. When you approve the visit, we capture the final amount and move caregiver payout forward.</p>
            </div>
        </x-card>
    </div>
</x-app-layout>
