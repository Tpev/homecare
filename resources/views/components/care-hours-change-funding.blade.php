@props(['change', 'booking'])
@if($change->type === \App\Models\CareBookingChangeRequest::TYPE_RESCHEDULE && $change->status === \App\Models\CareBookingChangeRequest::STATUS_PENDING && $booking->status === \App\Models\CareBooking::STATUS_SCHEDULED && $change->proposed_start_at && $change->proposed_end_at && app(\App\Services\CareHours\PrepaidBookingFundingService::class)->forBooking($booking))
    @php($changeCoverage = app(\App\Services\CareHours\CareHourScheduleChangeFundingService::class)->quote($change, auth()->user()))
    @if($changeCoverage['extra_cents'] > 0)
        <div class="mt-3 rounded-lg border border-[#D8E1D7] bg-[#F2F8F4] p-3 text-sm">
            <p>Cover ${{ number_format($changeCoverage['extra_cents'] / 100, 2) }} of extra time before this change can be accepted.</p>
            <form action="{{ route('family.care-hours.change.payment', $change) }}" method="POST" class="mt-2">@csrf<button type="submit" class="hc-primary-button">Cover extra time · ${{ number_format($changeCoverage['extra_cents'] / 100, 2) }}</button></form>
            <p class="mt-2 text-xs">Your current visit stays covered. This payment is only used if the change is accepted.</p>
        </div>
    @elseif($changeCoverage['protected_extra_cents'] > 0)
        <p class="mt-2 text-sm">Extra time is covered. The schedule changes only after acceptance.</p>
    @endif
@endif
