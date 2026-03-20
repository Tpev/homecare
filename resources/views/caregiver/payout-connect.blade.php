<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="font-display text-2xl font-semibold text-slate-900">Payout Setup</h1>
                <p class="mt-1 text-sm text-slate-600">Connect Stripe once so completed shifts can be paid out automatically.</p>
            </div>
            <a href="{{ route('caregiver.setup.index') }}" wire:navigate>
                <x-button color="blue" light>Back to setup</x-button>
            </a>
        </div>
    </x-slot>

    <div class="hc-page py-8 space-y-6">
        @if (session('status'))
            <x-alert color="green">{{ session('status') }}</x-alert>
        @endif

        @if ($errors->has('payouts'))
            <x-alert color="red">{{ $errors->first('payouts') }}</x-alert>
        @endif

        <x-card>
            <x-slot:header>
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-display text-lg font-semibold">Stripe Connect status</h2>
                    @if ($profile->stripeConnectIsReady())
                        <x-badge text="READY" color="green" />
                    @else
                        <x-badge text="ACTION REQUIRED" color="amber" />
                    @endif
                </div>
            </x-slot:header>

            <div class="space-y-3 text-sm">
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                    <p><span class="font-medium">Account ID:</span> {{ $profile->stripe_connect_account_id ?: 'Not created yet' }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                    <p><span class="font-medium">Charges enabled:</span> {{ $profile->stripe_charges_enabled ? 'Yes' : 'No' }}</p>
                    <p><span class="font-medium">Payouts enabled:</span> {{ $profile->stripe_payouts_enabled ? 'Yes' : 'No' }}</p>
                </div>
                <div class="flex flex-wrap gap-2 pt-1">
                    <form method="POST" action="{{ route('caregiver.payouts.connect.start') }}">
                        @csrf
                        <x-button color="blue" type="submit">
                            {{ $profile->stripe_connect_account_id ? 'Continue Stripe onboarding' : 'Start Stripe onboarding' }}
                        </x-button>
                    </form>
                    <a href="{{ route('caregiver.payouts.connect.show', ['sync' => 1]) }}">
                        <x-button color="blue" light>Refresh status</x-button>
                    </a>
                </div>
            </div>
        </x-card>
    </div>
</x-app-layout>
