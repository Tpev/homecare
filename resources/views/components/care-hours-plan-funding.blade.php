@props(['plan'])
@php
    $hoursPlanAccount = null;
    $hoursPlanPreference = null;
    if (! $plan->care_arrangement_id && $plan->isLive() && app(\App\Services\CareHours\PrepaidBookingFundingService::class)->available()) {
        $hoursPlanBooking = new \App\Models\CareBooking(['family_account_id' => $plan->family_account_id]);
        $hoursPlanBooking->setRelation('careRequest', $plan->sourceCareRequest);
        $hoursPlanAccount = app(\App\Services\CareHours\CareHourIdentityService::class)->forBooking($hoursPlanBooking);
        $hoursPlanPreference = \App\Models\CareHourPlanPreference::where('care_plan_id', $plan->id)->first();
    }
@endphp
@if($hoursPlanAccount && ($hoursPlanPreference?->enabled || app(\App\Services\CareHours\CareHourAccess::class)->canUseAccount($hoursPlanAccount, auth()->user())))
    <details class="hc-surface p-5 sm:p-6"><summary class="cursor-pointer font-semibold">Payment for future visits · {{ $hoursPlanPreference?->enabled ? 'Care hours' : 'Card' }}</summary>
        <p class="mt-3 text-sm text-[#485E53]">{{ \App\Models\CareHourAccount::formatMinutes($hoursPlanAccount->balance()['available_minutes']) }} available for {{ $hoursPlanAccount->display_name }}.</p>
        <form action="{{ route('family.care-hours.plan', $plan) }}" method="POST" class="mt-4 space-y-4">
            @csrf
            @if($hoursPlanPreference?->enabled)<input type="hidden" name="card" value="1">@else<input type="hidden" name="account_id" value="{{ $hoursPlanAccount->id }}">@endif
            <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="confirm" value="1" required class="mt-1 rounded border-[#D8E1D7]"><span>{{ $hoursPlanPreference?->enabled ? 'Use my usual card for future visits that have not already been funded.' : 'Use care hours for future visits that have not already had payment prepared. I will be asked to cover any shortfall.' }} Already funded visits keep their current payment.</span></label>
            <button class="hc-secondary-button" type="submit">{{ $hoursPlanPreference?->enabled ? 'Use card for future visits' : 'Use care hours for future visits' }}</button>
        </form>
    </details>
@endif
