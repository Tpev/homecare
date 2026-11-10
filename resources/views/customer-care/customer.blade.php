<x-app-layout>
<div class="hc-care-workspace hc-page space-y-6 py-8">
@include('customer-care.partials.status')
<a class="hc-care-text-link" href="{{ route('customer-care.index') }}">← Customer Success</a>
<header class="flex flex-wrap items-start justify-between gap-4">
<div>
<h1 class="hc-care-title">{{ $customer->name }}</h1>
<p class="hc-care-subtitle break-all">{{ $customer->email }} · {{ $customer->phone ?: 'No phone recorded' }}</p>
</div>
<a class="hc-primary-button" href="{{ route('customer-care.create', $customer) }}">Create a request</a>
</header>
<div class="grid items-start gap-6 lg:grid-cols-3">
<section id="prepaid-hours" class="hc-surface p-5 sm:p-6">
<p class="hc-care-eyebrow">Family balance</p>
<h2 class="mb-4 text-xl font-semibold">Prepaid hours</h2>
@php($balance = $wallet?->balance())<p class="text-3xl font-semibold">{{ \App\Models\CareHourAccount::formatMinutes($balance['available_minutes'] ?? 0) }} <span class="text-base font-normal">available</span>
</p>
<p class="mt-2 hc-care-subtitle">{{ \App\Models\CareHourAccount::formatMinutes($balance['reserved_minutes'] ?? 0) }} reserved · {{ \App\Models\CareHourAccount::formatMinutes($balance['used_minutes'] ?? 0) }} used</p>
<details class="mt-6" @if($errors->any()) open @endif>
<summary class="hc-care-text-link cursor-pointer">Add hours from an offline payment</summary>
<form method="POST" action="{{ route('customer-care.credit', $customer) }}" class="mt-6 space-y-4">
@csrf
<input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
<h2 class="mb-4 text-xl font-semibold">Add hours from an offline payment</h2>
<div class="grid grid-cols-2 gap-4">
<label class="block text-sm font-semibold">Hours<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="hours" type="number" min="0.25" max="10000" step="0.25" value="{{ old('hours') }}" required>
</label>
<label class="block text-sm font-semibold">Amount received (USD)<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="amount" type="number" min="0" step="0.01" max="1000000" value="{{ old('amount') }}" required>
</label>
</div>
<label class="block text-sm font-semibold">Payment reference<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="reference" maxlength="120" value="{{ old('reference') }}" placeholder="Bank transfer or receipt reference" required>
</label>
<label class="block text-sm font-semibold">Reason / internal note<textarea class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="reason" minlength="5" maxlength="1000" required>{{ old('reason') }}</textarea>
</label>
<p class="hc-care-subtitle">Adds exactly these hours, without a promotional bonus. No customer email is sent. Use the payment's reference to prevent duplicate credits.</p>
<button class="hc-primary-button">Record prepaid hours</button>
</form>
</details>
</section>
<section class="hc-surface p-5 sm:p-6 lg:col-span-2">
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
<h2 class="mb-4 text-xl font-semibold">{{ $archived ? 'Archived requests' : 'Care requests' }}</h2>
<a class="hc-care-text-link" href="{{ route('customer-care.customer', ['customer' => $customer, 'view' => $archived ? null : 'archived']) }}">{{ $archived ? 'View active requests' : 'Archived requests' }}</a>
</div>
<div class="space-y-3">
@forelse($requests as $item)<a class="block rounded-xl border border-[#e3d6c5] p-4" href="{{ route('customer-care.show', $item) }}">
<strong>{{ $item->title }}</strong>
<p class="hc-care-subtitle">#{{ $item->id }} · {{ $archived ? 'Archived' : ucfirst($item->status) }} · {{ $item->staff_managed ? 'Managed by Customer Success' : 'Customer-managed' }}</p>
</a>
@empty
<p class="hc-care-subtitle">No requests yet.</p>
@endforelse
</div>
<div class="mt-4">{{ $requests->links() }}</div>
</section>
</div>
<section class="hc-surface p-5 sm:p-6">
<h2 class="mb-4 text-xl font-semibold">Manual credit history</h2>
<div class="overflow-x-auto">
<table class="w-full text-left text-sm">
<thead>
<tr>
<th class="border-b border-[#E3D6C5] px-3 py-4 font-semibold">Date / staff</th>
<th class="border-b border-[#E3D6C5] px-3 py-4 font-semibold">Hours / amount</th>
<th class="border-b border-[#E3D6C5] px-3 py-4 font-semibold">Reference / reason</th>
</tr>
</thead>
<tbody>
@forelse($credits as $credit)<tr>
<td class="border-b border-[#E3D6C5] px-3 py-4 align-top">{{ $credit->created_at }}<p class="hc-care-subtitle">{{ $credit->actor_name }}</p>
</td>
<td class="border-b border-[#E3D6C5] px-3 py-4 align-top">{{ \App\Models\CareHourAccount::formatMinutes($credit->minutes) }}<p class="hc-care-subtitle">USD {{ number_format($credit->amount_cents / 100, 2) }}</p>
</td>
<td class="border-b border-[#E3D6C5] px-3 py-4 align-top">{{ $credit->reference }}<p class="hc-care-subtitle">{{ $credit->reason }}</p>
</td>
</tr>
@empty
<tr>
<td class="border-b border-[#E3D6C5] px-3 py-4 align-top" colspan="3">No manual credits recorded.</td>
</tr>
@endforelse
</tbody>
</table>
</div>
</section>
</div>
</x-app-layout>