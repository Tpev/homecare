@if($careRequest->status === 'open')<section class="hc-surface p-5 sm:p-6">
<div class="mb-5">
<p class="hc-care-eyebrow">Find the right fit</p>
<h2 class="mb-4 text-xl font-semibold">Applicants</h2>
<p class="hc-care-subtitle">Review responses, shortlist caregivers, and confirm the placement using prepaid hours.</p>
</div>
<div class="space-y-4">
@forelse($careRequest->applications as $application)<div class="rounded-xl border border-[#e3d6c5] p-4">
<strong>{{ $application->caregiver?->name }}</strong> <span class="hc-care-status">{{ ucfirst($application->status) }}</span>
<p class="hc-care-subtitle">{{ $application->cover_note }}</p>
@if(in_array($application->status, ['applied','shortlisted']))<form method="POST" action="{{ route('customer-care.action', $careRequest) }}" class="mt-3 flex flex-wrap gap-3">
@csrf
<input type="hidden" name="application_id" value="{{ $application->id }}">
<button class="hc-secondary-button" name="action" value="shortlist">Shortlist</button>
<button class="hc-secondary-button" name="action" value="reject">Decline</button>
</form>
<form method="POST" action="{{ route('customer-care.action', $careRequest) }}" class="mt-3 flex flex-wrap gap-3">
@csrf
<input type="hidden" name="action" value="hire">
<input type="hidden" name="caregiver_id" value="{{ $application->caregiver_user_id }}">
<label class="text-sm font-semibold flex-1">Hiring note<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="reason" required minlength="5" maxlength="2000" placeholder="Record the agreed placement">
</label>
<button class="hc-primary-button self-end">Hire caregiver</button>
</form>
@endif
</div>
@empty
<p class="hc-care-subtitle">No applications yet. Invite caregivers below or record an agreed placement.</p>
@endforelse
</div>
</section>
<section class="hc-surface p-5 sm:p-6">
<h2 class="mb-4 text-xl font-semibold">Invite caregivers</h2>
<p class="hc-care-subtitle mb-4">Search the caregiver directory and send invitations in one step.</p>
<form method="GET" class="mb-4 flex gap-3">
<input type="hidden" name="tab" value="caregivers">
<label class="text-sm font-semibold flex-1">Name, email, city or ZIP<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="caregiver_search" value="{{ $caregiverSearch }}">
</label>
<button class="hc-secondary-button self-end">Find</button>
</form>
<form method="POST" action="{{ route('customer-care.action', $careRequest) }}">
@csrf
<input type="hidden" name="action" value="recruit">
<div class="grid gap-3 md:grid-cols-2">
@forelse($candidates as $candidate)<label class="text-sm font-semibold rounded-xl border border-[#e3d6c5] p-3">
<input class="rounded border focus:ring-[#23483F]" type="checkbox" name="caregiver_ids[]" value="{{ $candidate->id }}"> {{ $candidate->name }}<span class="hc-care-subtitle block">{{ $candidate->city }}, {{ $candidate->state }}</span>
</label>
@empty
<p class="hc-care-subtitle">No eligible caregivers match, or they have already been contacted.</p>
@endforelse
</div>
@if($candidates->isNotEmpty())<button class="hc-primary-button mt-4">Invite selected caregivers</button>
@endif
</form>
<details class="mt-5">
<summary class="cursor-pointer font-semibold">Record an agreed placement</summary>
<form method="POST" action="{{ route('customer-care.action', $careRequest) }}" class="mt-4 space-y-3">
@csrf
<input type="hidden" name="action" value="hire">
<label class="block text-sm font-semibold">Caregiver<select class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="caregiver_id" required>
<option value="">Choose an eligible caregiver</option>
@foreach($placements as $candidate)<option value="{{ $candidate->id }}">{{ $candidate->name }} — {{ $candidate->city }}</option>
@endforeach
</select>
</label>
<label class="block text-sm font-semibold">Placement agreement / internal note<textarea class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="reason" required minlength="5" maxlength="2000">
</textarea>
</label>
<button class="hc-primary-button">Hire and reserve prepaid hours</button>
</form>
</details>
</section>
@endif
