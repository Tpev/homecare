<div class="grid items-start gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <section class="hc-surface p-5 sm:p-6">
            <p class="hc-care-eyebrow">The care request</p>
            <h2 class="mt-2 text-2xl font-semibold">Care for {{ $careRequest->recipient?->full_name ?? $careRequest->family->name }}</h2>
            <p class="mt-4 whitespace-pre-line text-sm leading-7">{{ $careRequest->scope_of_work }}</p>
            <div class="mt-5 flex flex-wrap gap-2">@foreach($careRequest->tasks as $task)<span class="hc-care-status">{{ $task->name }}</span>@endforeach</div>
            @if($careRequest->recipient?->care_notes)
                <div class="mt-6 border-t border-[#E3D6C5] pt-5"><h3 class="text-sm font-semibold">Care notes</h3><p class="hc-care-subtitle whitespace-pre-line">{{ $careRequest->recipient->care_notes }}</p></div>
            @endif
        </section>
        <section class="hc-surface p-5 sm:p-6">
            <h2 class="text-xl font-semibold">Schedule & location</h2>
            <dl class="mt-5 grid gap-6 sm:grid-cols-2">
                <div><dt class="hc-care-eyebrow">{{ $careRequest->isRecurring() ? 'Weekly schedule' : 'Visit time' }}</dt><dd class="mt-2 text-sm leading-6">{{ $schedule }}<p class="hc-care-meta">{{ config('app.timezone') }}</p>@if($careRequest->isRecurring())<p class="hc-care-meta">From {{ $careRequest->recurring_starts_on?->format('M j, Y') }}{{ $careRequest->recurring_ends_on ? ' until '.$careRequest->recurring_ends_on->format('M j, Y') : '' }}</p>@endif</dd></div>
                <div><dt class="hc-care-eyebrow">Care location</dt><dd class="mt-2 text-sm leading-6">{{ $careRequest->address_line1 }} {{ $careRequest->address_line2 }}<br>{{ $careRequest->city }}, {{ $careRequest->state }} {{ $careRequest->zip }}</dd></div>
                @if($careRequest->home_access_notes)<div class="sm:col-span-2"><dt class="hc-care-eyebrow">Home access</dt><dd class="mt-2 whitespace-pre-line text-sm leading-6">{{ $careRequest->home_access_notes }}</dd></div>@endif
            </dl>
        </section>
    </div>
    <aside class="space-y-6">
        <section class="hc-surface p-5 sm:p-6">
            <p class="hc-care-eyebrow">Prepaid coverage</p>
            <p class="mt-3 text-3xl font-semibold">{{ \App\Models\CareHourAccount::formatMinutes($wallet?->balance()['available_minutes'] ?? 0) }}</p>
            <p class="hc-care-meta mt-1">available for this family</p>
            <p class="hc-care-subtitle">Prepaid hours cover managed visits so the customer can leave the arrangements to your team.</p>
            <a class="hc-secondary-button mt-5 w-full" href="{{ route('customer-care.customer', $careRequest->family) }}#prepaid-hours">Manage prepaid hours</a>
        </section>
        <section class="hc-care-soft p-5">
            <h2 class="text-base font-semibold">Your team handles the details</h2>
            <p class="hc-care-subtitle">Messages go to the caregiver. Customer notifications stay off for this request and its visits.</p>
            <a class="hc-care-text-link mt-2" href="{{ route('customer-care.show', ['careRequest' => $careRequest, 'tab' => 'activity']) }}">View notes & activity</a>
        </section>
    </aside>
</div>
