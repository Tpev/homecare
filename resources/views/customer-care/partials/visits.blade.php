<fieldset @disabled($careRequest->staff_archived_at) class="min-w-0 space-y-6">
    @if($careRequest->carePlan)
        <section class="hc-surface p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-xl font-semibold">Recurring care</h2><span class="hc-care-status">{{ ucfirst($careRequest->carePlan->status) }}</span></div>
            <p class="hc-care-subtitle">{{ \App\Support\WeeklySchedule::label($careRequest->carePlan->weeklyScheduleSlots()) }}</p>
            @if(! in_array($careRequest->carePlan->status, ['ended', 'cancelled', 'declined', 'expired']))
                <details class="mt-4"><summary class="cursor-pointer text-sm font-semibold">Manage recurring care</summary>
                    <form method="POST" action="{{ route('customer-care.action', $careRequest) }}" class="mt-4 space-y-4">
                        @csrf<input type="hidden" name="action" value="plan"><label class="block text-sm font-semibold">Reason<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="reason" required minlength="5" maxlength="2000"></label>
                        <div class="flex flex-wrap gap-3">
                            @if($careRequest->carePlan->status === 'paused')<button class="hc-primary-button" name="plan_action" value="resume">Resume care</button>@else<button class="hc-secondary-button" name="plan_action" value="pause">Pause care</button>@endif
                            <button class="hc-secondary-button" name="plan_action" value="end">End recurring care</button>
                        </div>
                    </form>
                </details>
                @include('customer-care.partials.plan-changes')
            @endif
        </section>
    @endif
    <section class="hc-surface p-5 sm:p-6">
        <div class="mb-6"><p class="hc-care-eyebrow">Care in progress</p><h2 class="mt-2 text-2xl font-semibold">Visits & follow-up</h2><p class="hc-care-subtitle">Times use {{ config('app.timezone') }}. Review actual care before approving hours.</p></div>
        <div class="space-y-5">
            @forelse($bookings as $booking)
                @php
                    $actions = [];
                    if ($booking->status === 'scheduled') $actions += ['reschedule' => 'Reschedule', 'cancel' => 'Cancel visit'];
                    if (in_array($booking->status, ['scheduled', 'in_progress', 'paused'])) $actions['complete'] = 'Record completed care';
                    if (in_array($booking->status, ['completed', 'reviewed'])) $actions += ['approve' => 'Approve hours', 'dispute' => 'Open dispute'];
                    if ($booking->dispute_status === 'open') $actions['resolve_dispute'] = 'Resolve dispute';
                    if ($booking->status !== 'cancelled') $actions['coverage'] = 'Refresh coverage';
                    $selectedAction = (int) old('booking_id') === $booking->id && old('action') === 'visit' ? old('visit_action', '') : '';
                @endphp
                <article id="visit-{{ $booking->id }}" class="rounded-2xl border border-[#E3D6C5] p-4 sm:p-5" x-data="{ visitAction: @js($selectedAction) }">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div><p class="hc-care-eyebrow">Visit #{{ $booking->id }}</p><h3 class="mt-1 text-lg font-semibold">{{ $booking->scheduled_start_at?->format('D, M j · g:i A') }} – {{ $booking->scheduled_end_at?->format('g:i A') }}</h3><p class="hc-care-subtitle">{{ $booking->caregiver?->name }}</p></div>
                        <span class="hc-care-status">{{ ucfirst(str_replace('_', ' ', $booking->status)) }}</span>
                    </div>
                    <dl class="mt-5 grid gap-4 border-y border-[#E3D6C5] py-4 sm:grid-cols-3">
                        <div><dt class="hc-care-meta">Actual care</dt><dd class="mt-1 text-sm font-semibold">{{ $booking->worked_minutes !== null ? \App\Models\CareHourAccount::formatMinutes($booking->worked_minutes) : 'Not recorded yet' }}</dd></div>
                        <div><dt class="hc-care-meta">Hours review</dt><dd class="mt-1 text-sm font-semibold">{{ $booking->family_confirmed_at ? 'Approved' : ($booking->status === 'cancelled' ? 'Not required' : 'Awaiting approval') }}</dd></div>
                        <div><dt class="hc-care-meta">Prepaid coverage</dt><dd class="mt-1 text-sm font-semibold">{{ ucfirst(str_replace('_', ' ', $booking->careHourFunding?->status ?? 'Card billing')) }}</dd></div>
                    </dl>
                    @if($booking->careHourSettlement && ($booking->careHourSettlement->card_minutes > 0 || in_array($booking->careHourSettlement->status, ['payment_attention', 'funding_on_hold', 'reconciliation_pending'])))
                        <p class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">{{ ucfirst(str_replace('_', ' ', $booking->careHourSettlement->status)) }}. @if($booking->careHourSettlement->card_minutes > 0){{ $booking->careHourSettlement->card_minutes }} minutes need coverage. Add prepaid hours, then approve again.@endif</p>
                    @endif
                    @if($actions && ! $careRequest->staff_archived_at)
                        <div class="mt-4 flex flex-wrap gap-2" aria-label="Actions for visit {{ $booking->id }}">
                            @foreach($actions as $value => $label)<button type="button" class="{{ in_array($value, ['approve', 'complete']) ? 'hc-primary-button' : 'hc-secondary-button' }}" @click="visitAction = visitAction === @js($value) ? '' : @js($value)" :aria-expanded="visitAction === @js($value)" aria-controls="visit-form-{{ $booking->id }}">{{ $label }}</button>@endforeach
                        </div>
                        <form id="visit-form-{{ $booking->id }}" method="POST" action="{{ route('customer-care.action', $careRequest) }}" class="hc-care-soft mt-5 space-y-4 p-4 sm:p-5" x-cloak x-show="visitAction !== ''">
                            @csrf<input type="hidden" name="action" value="visit"><input type="hidden" name="booking_id" value="{{ $booking->id }}"><input type="hidden" name="visit_action" :value="visitAction">
                            <h4 class="text-lg font-semibold" x-text="(@js($actions))[visitAction]"></h4>
                            <p x-show="visitAction === 'cancel'" class="hc-care-subtitle">The caregiver will be informed and reserved hours will be released. The customer will not be notified.</p>
                            <p x-show="visitAction === 'approve'" class="hc-care-subtitle">Confirm that the actual hours are correct. This settles care against the family's prepaid balance.</p>
                            <div x-show="['reschedule', 'complete'].includes(visitAction)" class="grid gap-4 sm:grid-cols-2">
                                <label class="block text-sm font-semibold">Start<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" type="datetime-local" name="start" value="{{ $selectedAction ? old('start') : $booking->scheduled_start_at?->format('Y-m-d\TH:i') }}" :required="['reschedule','complete'].includes(visitAction)" :disabled="!['reschedule','complete'].includes(visitAction)"></label>
                                <label class="block text-sm font-semibold">End<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" type="datetime-local" name="end" value="{{ $selectedAction ? old('end') : $booking->scheduled_end_at?->format('Y-m-d\TH:i') }}" :required="['reschedule','complete'].includes(visitAction)" :disabled="!['reschedule','complete'].includes(visitAction)"></label>
                            </div>
                            <label class="block text-sm font-semibold" x-show="visitAction === 'complete'">Unpaid break (minutes)<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" type="number" name="break_minutes" min="0" value="{{ $selectedAction ? old('break_minutes', 0) : 0 }}"></label>
                            <label class="block text-sm font-semibold">Reason / evidence<textarea class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="reason" required minlength="5" maxlength="2000" rows="3" placeholder="Record what was agreed or verified">{{ $selectedAction ? old('reason') : '' }}</textarea></label>
                            <div class="flex flex-wrap gap-3"><button class="hc-primary-button" x-text="(@js($actions))[visitAction] || 'Save'">Save</button><button type="button" class="hc-secondary-button" @click="visitAction = ''">Keep unchanged</button></div>
                        </form>
                    @endif
                    @include('customer-care.partials.visit-proposals')
                </article>
            @empty
                <div class="hc-care-soft p-8 text-center"><h3 class="text-lg font-semibold">Your first visit starts here</h3><p class="hc-care-subtitle">Hire a caregiver to schedule care and reserve prepaid hours.</p><a class="hc-care-text-link mt-2" href="{{ route('customer-care.show', ['careRequest' => $careRequest, 'tab' => 'caregivers']) }}">Find a caregiver</a></div>
            @endforelse
        </div>
        <div class="mt-5">{{ $bookings->links() }}</div>
    </section>
</fieldset>
