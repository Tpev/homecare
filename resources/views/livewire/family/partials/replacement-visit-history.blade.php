@php($releasedVisits = $requestItem->releasedBookings()->with('caregiver:id,name')->get())
@if ($releasedVisits->isNotEmpty())
    <section class="rounded-xl border border-[#D6CCBE] bg-white p-4 text-sm text-[#17313F]">
        @if ($requestItem->status === \App\Models\CareRequest::STATUS_OPEN)
            <p class="font-semibold">Your request is open again</p>
            <p class="mt-1">Review the available applicants or invite another caregiver to arrange a replacement.</p>
        @endif
        <details class="mt-2">
            <summary class="cursor-pointer font-medium">Cancelled caregiver visits ({{ $releasedVisits->count() }})</summary>
            <ul class="mt-3 space-y-3">
                @foreach ($releasedVisits as $releasedVisit)
                    <li>
                        <p class="font-semibold">{{ $releasedVisit->caregiver?->name }} · Cancelled visit #{{ $releasedVisit->id }}</p>
                        <p>{{ $releasedVisit->scheduled_start_at?->format('M j, Y g:i A') }} – {{ $releasedVisit->scheduled_end_at?->format('M j, Y g:i A') }}</p>
                        <p class="mt-1">{{ $releasedVisit->cancellation_reason }}</p>
                    </li>
                @endforeach
            </ul>
        </details>
    </section>
@endif
