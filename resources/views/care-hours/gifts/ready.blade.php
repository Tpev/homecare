<x-app-layout>
    <div class="hc-page mx-auto max-w-xl space-y-6 py-10">
        <header><p class="mb-3 text-sm font-semibold text-[#485E53]">Gift claimed</p><h1 class="font-display text-3xl font-semibold">Your care hours are ready</h1></header>
        <section class="hc-surface space-y-4 p-6" aria-label="Gift hours added">
            <p class="font-display text-5xl font-semibold">{{ \App\Models\CareHourAccount::formatMinutes($purchase->total_minutes) }} added</p>
            <p class="text-[#485E53]">
                {{ \App\Models\CareHourAccount::formatMinutes($purchase->paid_minutes) }} gifted
                @if($purchase->bonus_minutes > 0)
                    + {{ \App\Models\CareHourAccount::formatMinutes($purchase->bonus_minutes) }} free welcome care
                @endif
                for {{ $account->display_name }}.
            </p>
            <p class="border-t border-[#D8E1D7] pt-4">Available balance: <strong>{{ \App\Models\CareHourAccount::formatMinutes($account->balance()['available_minutes']) }}</strong></p>
        </section>
        <p class="text-[#485E53]">Use your hours when you book a visit. There’s no rush—they never expire.</p>
        <div class="flex flex-wrap gap-3">
            <a href="{{ \App\Support\CareHourRequestHandoff::url($account, auth()->user()) }}" class="hc-primary-button">Find care</a>
            <a href="{{ route('family.care-hours.show', $account) }}" class="hc-secondary-button">View my care hours</a>
        </div>
    </div>
</x-app-layout>
