<section class="rounded-2xl border border-slate-200 bg-white p-5" aria-label="Affected bookings requiring review">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold text-slate-900">Affected bookings & commitments</h2>
        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900">{{ $bookingReviews->total() }} awaiting review</span>
    </div>
    <p class="mt-1 text-sm text-slate-500">Suspension does not automatically cancel existing work. Review each flagged commitment and record any action taken.</p>
    <div class="mt-4 space-y-4">
        @forelse ($bookingReviews as $review)
            <article wire:key="booking-review-{{ $review->id }}" class="rounded-xl border border-amber-200 p-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h3 class="font-semibold text-slate-900">{{ str($review->subject_type)->headline() }} #{{ $review->subject_id }}</h3>
                        <p class="mt-1 text-sm text-slate-600">{{ $review->screeningCase?->profile?->user?->name ?? 'Unavailable caregiver' }}</p>
                    </div>
                    <button type="button" wire:click="selectCase({{ $review->screening_case_id }})" class="min-h-11 rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700">View suspension case #{{ $review->screening_case_id }}</button>
                </div>
                <p class="mt-2 text-sm text-slate-700">{{ $review->reason }}</p>
                <p class="mt-1 text-xs font-medium text-slate-700">{{ $review->commitment_status ? str($review->commitment_status)->headline() : 'Commitment unavailable' }}{{ $review->commitment_starts_at ? ' · Starts '.\Illuminate\Support\Carbon::parse($review->commitment_starts_at)->format('M j, Y g:i A') : '' }}</p>
                <p class="mt-1 text-xs text-slate-500">Flagged {{ $review->created_at?->format('M j, Y g:i A') }}</p>
                @php
                    $managementUrl = match (true) {
                        $review->subject_type === 'care_plan' => route('admin.care-plans.show', $review->subject_id),
                        $review->subject_type === 'booking' && $review->booking_plan_id => route('admin.care-plans.show', $review->booking_plan_id),
                        $review->subject_type === 'booking' && $review->booking_request_id => route('admin.requests.show', $review->booking_request_id),
                        $review->subject_type === 'coverage_shift' => route('admin.continuous-coverage.index'),
                        default => null,
                    };
                @endphp
                @if ($managementUrl)<a href="{{ $managementUrl }}" target="_blank" rel="noopener" class="mt-2 inline-flex min-h-11 items-center text-sm font-semibold text-teal-800 underline">Open commitment management</a>@endif
                <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-[minmax(12rem,1fr)_minmax(12rem,2fr)_auto] lg:items-end">
                    <div>
                        <label for="booking-resolution-{{ $review->id }}" class="mb-1 block text-xs font-semibold text-slate-600">Resolution</label>
                        <select id="booking-resolution-{{ $review->id }}" wire:model="bookingResolutions.{{ $review->id }}" class="min-h-11 w-full rounded-xl border-slate-300 text-sm">
                            <option value="">Choose a resolution</option>
                            <option value="reviewed_no_change">Reviewed · keep current booking</option>
                            <option value="action_taken">Action taken in booking management</option>
                        </select>
                    </div>
                    <div>
                        <label for="booking-note-{{ $review->id }}" class="mb-1 block text-xs font-semibold text-slate-600">Review note (required)</label>
                        <input id="booking-note-{{ $review->id }}" type="text" wire:model="bookingNotes.{{ $review->id }}" maxlength="5000" placeholder="Explain the decision and any action taken" class="min-h-11 w-full rounded-xl border-slate-300 text-sm">
                    </div>
                    <button type="button" wire:click="resolveBookingReview({{ $review->id }})" wire:loading.attr="disabled" class="min-h-11 rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Resolve flag</button>
                </div>
            </article>
        @empty
            <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500">No existing bookings are awaiting a screening review.</p>
        @endforelse
    </div>
    @if ($bookingReviews->hasPages()) <div class="mt-4">{{ $bookingReviews->links() }}</div> @endif
</section>
