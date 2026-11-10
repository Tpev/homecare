<x-app-layout>
<div class="hc-care-workspace hc-page space-y-6 py-8">
@include('customer-care.partials.status')<a class="hc-care-text-link" href="{{ route('customer-care.index') }}">← Customer Success</a>
<h1 class="hc-care-title">Gift card purchases</h1>
<form method="GET" class="flex gap-3">
<label class="text-sm font-semibold flex-1">Buyer name, email or purchase ID<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="search" value="{{ $search }}">
</label>
<button class="hc-primary-button self-end">Search</button>
</form>
<section class="hc-surface p-5 sm:p-6 overflow-x-auto">
<table class="w-full text-left text-sm">
<thead>
<tr>
<th class="border-b border-[#E3D6C5] px-3 py-4 font-semibold">Purchase</th>
<th class="border-b border-[#E3D6C5] px-3 py-4 font-semibold">Buyer</th>
<th class="border-b border-[#E3D6C5] px-3 py-4 font-semibold">Hours / amount</th>
<th class="border-b border-[#E3D6C5] px-3 py-4 font-semibold">Payment</th>
<th class="border-b border-[#E3D6C5] px-3 py-4 font-semibold">Gift</th>
</tr>
</thead>
<tbody>
@forelse($gifts as $purchase)<tr>
<td class="border-b border-[#E3D6C5] px-3 py-4 align-top">{{ $purchase->created_at->format('M j, Y') }}<p class="hc-care-subtitle break-all">{{ $purchase->public_id }}</p>
</td>
<td class="border-b border-[#E3D6C5] px-3 py-4 align-top">{{ $purchase->buyer_name }}<p class="hc-care-subtitle">{{ $purchase->buyer_email }}</p>
</td>
<td class="border-b border-[#E3D6C5] px-3 py-4 align-top">{{ \App\Models\CareHourAccount::formatMinutes($purchase->total_minutes) }}<p class="hc-care-subtitle">USD {{ number_format($purchase->amount_cents/100,2) }}</p>
</td>
<td class="border-b border-[#E3D6C5] px-3 py-4 align-top">{{ ucfirst($purchase->status) }}</td>
<td class="border-b border-[#E3D6C5] px-3 py-4 align-top">{{ $purchase->gift->claimed_at ? 'Claimed' : ($purchase->gift->ready_at ? 'Awaiting claim' : 'Awaiting payment') }}</td>
</tr>
@empty
<tr>
<td class="border-b border-[#E3D6C5] px-3 py-4 align-top" colspan="5">No gift purchases found.</td>
</tr>
@endforelse
</tbody>
</table>
<div class="mt-4">{{ $gifts->links() }}</div>
</section>
</div>
</x-app-layout>