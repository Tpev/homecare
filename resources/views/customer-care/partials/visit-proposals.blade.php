@foreach($booking->changeRequests->where('status','pending') as $change)
<div class="mt-4 rounded-lg bg-amber-50 p-4">
<p class="font-semibold">Pending {{ $change->type }} request</p>
<p class="hc-care-subtitle">{{ $change->reason }}</p>
@if($change->proposed_start_at)<p class="hc-care-subtitle">{{ $change->proposed_start_at->format('M j, g:i A') }} – {{ $change->proposed_end_at?->format('g:i A') }}</p>
@endif
<form method="POST" action="{{ route('customer-care.action', $careRequest) }}" class="mt-3 space-y-3">
@csrf
<input type="hidden" name="action" value="change">
<input type="hidden" name="booking_id" value="{{ $booking->id }}">
<input type="hidden" name="change_id" value="{{ $change->id }}">
<label class="block text-sm font-semibold">Team response<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="reason" required minlength="5" maxlength="2000">
</label>
<button class="hc-primary-button" name="decision" value="accept">Accept change</button> <button class="hc-secondary-button" name="decision" value="reject">Decline change</button>
</form>
</div>
@endforeach
@foreach($booking->timeCorrections as $correction)
<div class="mt-4 rounded-lg border border-[#e3d6c5] p-4">
<p class="font-semibold">Time correction #{{ $correction->id }} · {{ str_replace('_',' ',$correction->status) }}</p>
<p class="hc-care-subtitle">{{ $correction->proposed_worked_minutes }} minutes · {{ $correction->explanation }}</p>
@if($correction->status === \App\Models\CareBookingTimeCorrection::STATUS_PENDING_FAMILY)<form method="POST" action="{{ route('customer-care.action', $careRequest) }}" class="mt-3 space-y-3">
@csrf
<input type="hidden" name="action" value="correction">
<input type="hidden" name="booking_id" value="{{ $booking->id }}">
<input type="hidden" name="correction_id" value="{{ $correction->id }}">
<label class="block text-sm font-semibold">Review note<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="reason" required minlength="8" maxlength="2000">
</label>
<button class="hc-primary-button" name="decision" value="approve">Approve corrected hours</button> <button class="hc-secondary-button" name="decision" value="changes">Ask caregiver to revise</button>
</form>
@endif
</div>
@endforeach