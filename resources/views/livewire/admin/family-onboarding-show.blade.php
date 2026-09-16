<div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6">
    <div><a href="{{ route('admin.family-onboarding.index') }}" class="text-sm underline">← Family onboarding</a><h1 class="mt-3 text-2xl font-semibold">{{ $onboarding->initiatedBy?->name ?? 'Family' }} · Account #{{ $onboarding->family_account_id }}</h1><p class="mt-2 text-sm text-slate-600">{{ str_replace('_', ' ', $onboarding->status) }}@if($onboarding->completed_at) · {{ $onboarding->completed_at->format('M j, Y g:i A T') }}@endif</p></div>
    @if(session('status'))<p role="status" class="rounded-lg bg-green-50 p-4 text-green-900">{{ session('status') }}</p>@endif
    @if($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @if($onboarding->completed_at)
        <section class="rounded-xl border bg-white p-5"><h2 class="mb-4 text-lg font-semibold">Submitted information</h2><dl class="space-y-3">@foreach($onboarding->submissionDetails() as $detail)<div class="grid gap-1 border-b pb-3 sm:grid-cols-3"><dt class="text-sm text-slate-500">{{ $detail['label'] }}</dt><dd class="whitespace-pre-wrap break-words text-sm sm:col-span-2">{{ $detail['value'] }}</dd></div>@endforeach</dl></section>
    @else
        <p class="rounded-xl border bg-white p-5">{{ $onboarding->status === 'in_progress' ? 'The family has not submitted this form yet. Last saved step: '.$onboarding->current_step.'.' : 'Onboarding was exempted: '.$onboarding->exemption_reason }}</p>
    @endif
    @if($visit = $onboarding->welcomeVisit)
        <section class="rounded-xl border bg-white p-5"><h2 class="text-lg font-semibold">Welcome visit follow-up</h2><p class="mt-2 text-sm">{{ $visit->preferred_date->format('M j, Y') }} · {{ ucfirst($visit->preferred_range) }} · {{ $visit->timezone }} · {{ $visit->phone }}</p><p class="mt-2 text-sm text-slate-600">Confirm the final time with the family using <a class="underline" href="{{ route('admin.sms.index') }}">the SMS inbox</a>, then record the outcome here.</p>
            <form wire:submit="saveVisit" class="mt-4 space-y-4">
                <label class="block text-sm">Follow-up status<select wire:model.live="visitStatus" class="mt-1 block w-full rounded-lg border-slate-300">@foreach(['requested' => 'Awaiting contact', 'contacted' => 'Contacted', 'confirmed' => 'Confirmed by text', 'completed' => 'Visit completed', 'cancelled' => 'Cancelled', 'unavailable' => 'Unavailable'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                <label class="block text-sm">Agreed start time ({{ $visit->timezone }})<input type="datetime-local" wire:model="confirmedStart" class="mt-1 block w-full rounded-lg border-slate-300"></label>
                @if($visitStatus === 'confirmed')<label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="textConfirmed" class="rounded">The family has confirmed this time by text.</label>@endif
                <label class="block text-sm">Follow-up note<textarea wire:model="staffNote" maxlength="2000" class="mt-1 block w-full rounded-lg border-slate-300"></textarea></label>
                <button class="rounded-lg bg-emerald-900 px-4 py-2 text-white" wire:loading.attr="disabled">Save follow-up</button>
            </form>
            @if($visit->handled_at)<p class="mt-3 text-xs text-slate-500">Last handled by user #{{ $visit->handled_by_user_id }} · {{ $visit->handled_at->format('M j, Y g:i A T') }}</p>@endif
        </section>
    @endif
    @if($onboarding->completed_at)
        <section class="rounded-xl border bg-white p-5"><h2 class="text-lg font-semibold">Admin email delivery</h2><p class="mt-2 text-sm text-slate-600">Accepted means the mail provider accepted the email. Check the provider before retrying an unconfirmed delivery.</p>
            @foreach($onboarding->deliveries->where('status', '!=', 'superseded') as $delivery)
                <div class="mt-4 border-t pt-4"><p class="text-sm font-medium">{{ $delivery->recipient ?: 'No admin recipients configured' }} · {{ $delivery->status }}</p><p class="text-xs text-slate-500">Attempts: {{ $delivery->attempts }}@if($delivery->last_error_code) · {{ $delivery->last_error_code }}@endif</p>
                    @if(in_array($delivery->status, ['unconfirmed', 'failed']))<div class="mt-2 flex flex-wrap gap-4 text-sm"><button class="underline" wire:click="resolveDelivery({{ $delivery->id }}, 'retry')" wire:loading.attr="disabled">Retry after verification</button><button class="underline" wire:click="resolveDelivery({{ $delivery->id }}, 'accepted')" wire:loading.attr="disabled">Record provider acceptance</button></div>@endif
                </div>
            @endforeach
            @if($onboarding->deliveries->whereIn('status', ['unconfirmed', 'failed'])->isNotEmpty())<label class="mt-4 block text-sm">Verification / reason for delivery action<input wire:model="deliveryReason" maxlength="500" class="mt-1 block w-full rounded-lg border-slate-300" placeholder="What did you verify with the provider?"></label>@endif
        </section>
    @endif
</div>
