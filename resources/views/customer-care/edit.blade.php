<x-app-layout>
<div class="hc-care-workspace hc-page space-y-6 py-8">
@include('customer-care.partials.status')
<a class="hc-care-text-link" href="{{ route('customer-care.customer', $customer) }}">← {{ $customer->name }}</a>
<header>
<h1 class="hc-care-title">{{ $careRequest ? 'Edit request' : 'Create a request' }}</h1>
<p class="mt-2 hc-care-subtitle">For {{ $customer->name }} · Managed by your team · Customer notifications off</p>
</header>
<form method="POST" action="{{ $careRequest ? route('customer-care.update', $careRequest) : route('customer-care.store', $customer) }}" class="space-y-6" x-data="{ frequency: @js(old('request_type', $careRequest?->request_type ?? 'one_time')) }">
@csrf
<input type="hidden" name="request_key" value="{{ old('request_key', $careRequest?->staff_request_key ?? (string) \Illuminate\Support\Str::uuid()) }}">
<div class="grid items-start gap-6 lg:grid-cols-2">
<section class="hc-surface p-5 sm:p-6 space-y-4">
<p class="hc-care-eyebrow">01 · Care details</p>
<h2 class="mb-4 text-xl font-semibold">What help is needed?</h2>
<label class="block text-sm font-semibold">Request title<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="title" value="{{ old('title', $careRequest?->title) }}" required maxlength="180" placeholder="Afternoon companionship and meal preparation">
</label>
<label class="block text-sm font-semibold">Care recipient<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="recipient_name" value="{{ old('recipient_name', $careRequest?->recipient?->full_name ?? $customer->name) }}" required maxlength="160">
</label>
<label class="block text-sm font-semibold">What should the caregiver do?<textarea class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="scope_of_work" required maxlength="5000">{{ old('scope_of_work', $careRequest?->scope_of_work) }}</textarea>
</label>
<fieldset>
<legend class="mb-2 text-sm font-semibold">Services</legend>
<div class="grid gap-2 sm:grid-cols-2">
@foreach($tasks as $task)<label class="text-sm font-semibold flex items-center gap-2">
<input class="rounded border focus:ring-[#23483F]" type="checkbox" name="tasks[]" value="{{ $task->id }}" @checked(in_array($task->id, old('tasks', $careRequest?->tasks?->pluck('id')->all() ?? [])))> {{ $task->name }}</label>
@endforeach
</div>
</fieldset>
<label class="block text-sm font-semibold">Care notes (optional)<textarea class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="care_notes" maxlength="5000">{{ old('care_notes', $careRequest?->recipient?->care_notes) }}</textarea>
</label>
</section>
<section class="hc-surface p-5 sm:p-6 space-y-4">
<p class="hc-care-eyebrow">02 · Schedule</p>
<h2 class="mb-4 text-xl font-semibold">When is care needed?</h2>
<label class="block text-sm font-semibold">Frequency<select class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="request_type" x-model="frequency">
<option value="one_time">One visit</option>
<option value="recurring">Recurring care</option>
</select>
</label>
<p class="hc-care-subtitle">All times use {{ config('app.timezone') }}.</p>
<div x-show="frequency === 'one_time'" class="space-y-4">
<label class="block text-sm font-semibold">Visit start<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="requested_start_at" type="datetime-local" value="{{ old('requested_start_at', $careRequest?->requested_start_at?->format('Y-m-d\TH:i')) }}" :disabled="frequency !== 'one_time'" :required="frequency === 'one_time'">
</label>
<label class="block text-sm font-semibold">Visit end<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="requested_end_at" type="datetime-local" value="{{ old('requested_end_at', $careRequest?->requested_end_at?->format('Y-m-d\TH:i')) }}" :disabled="frequency !== 'one_time'" :required="frequency === 'one_time'">
</label>
</div>
<div x-show="frequency === 'recurring'" class="space-y-4">
<fieldset>
<legend class="text-sm font-semibold">Days</legend>
<div class="mt-2 flex flex-wrap gap-3">
@foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day => $label)<label class="block text-sm font-semibold">
<input class="rounded border focus:ring-[#23483F]" name="recurring_days[]" type="checkbox" value="{{ $day }}" :disabled="frequency !== 'recurring'" @checked(in_array($day, old('recurring_days', $careRequest?->recurring_days ?? [])))> {{ $label }}</label>
@endforeach
</div>
</fieldset>
<div class="grid grid-cols-2 gap-4">
@foreach(['recurring_start_time' => 'Start time', 'recurring_end_time' => 'End time'] as $field => $label)<label class="block text-sm font-semibold">{{ $label }}<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" type="time" name="{{ $field }}" value="{{ old($field, substr($careRequest?->$field ?? '', 0, 5)) }}" :disabled="frequency !== 'recurring'" :required="frequency === 'recurring'">
</label>
@endforeach
</div>
<label class="block text-sm font-semibold">Starts on<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" type="date" name="recurring_starts_on" value="{{ old('recurring_starts_on', $careRequest?->recurring_starts_on?->format('Y-m-d')) }}" :disabled="frequency !== 'recurring'" :required="frequency === 'recurring'">
</label>
<label class="block text-sm font-semibold">Ends on (optional)<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" type="date" name="recurring_ends_on" value="{{ old('recurring_ends_on', $careRequest?->recurring_ends_on?->format('Y-m-d')) }}" :disabled="frequency !== 'recurring'">
</label>
</div>
</section>
<section class="hc-surface p-5 sm:p-6 space-y-4 lg:col-span-2">
<p class="hc-care-eyebrow">03 · Location</p>
<h2 class="mb-4 text-xl font-semibold">Where will care take place?</h2>
<div class="grid gap-4 sm:grid-cols-2">
@foreach(['address_line1' => 'Street address', 'address_line2' => 'Apartment / unit (optional)', 'city' => 'City', 'state' => 'State (two-letter code)', 'zip' => 'ZIP code'] as $field => $label)<label class="block text-sm font-semibold">{{ $label }}<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="{{ $field }}" value="{{ old($field, $careRequest?->$field ?? (in_array($field, ['city','state']) ? $customer->$field : '')) }}" @required($field !== 'address_line2') @if($field === 'state') maxlength="2" @endif>
</label>
@endforeach
<label class="block text-sm font-semibold">Home access notes (optional)<textarea class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="home_access_notes" maxlength="2000">{{ old('home_access_notes', $careRequest?->home_access_notes) }}</textarea>
</label>
</div>
</section>
</div>
<div class="hc-surface p-5 sm:p-6 flex flex-wrap items-center justify-between gap-4">
<p class="hc-care-subtitle">After posting, review applicants and hire using this family's saved card or prepaid hours.</p>
<div class="flex flex-wrap gap-3">
<a class="hc-care-text-link" href="{{ $careRequest ? route('customer-care.show', $careRequest) : route('customer-care.customer', $customer) }}">Cancel</a>
<button class="hc-secondary-button" name="status" value="draft">Save draft</button>
<button class="hc-primary-button" name="status" value="open">Post request</button>
</div>
</div>
</form>
</div>
</x-app-layout>