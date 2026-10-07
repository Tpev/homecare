@props(['lead', 'compact' => false])
@php($onboarding = $lead->family_account_id ? $lead->familyAccount?->onboarding : null)
@if($onboarding)
    <div class="{{ $compact ? 'space-y-1' : 'rounded-2xl border border-emerald-200 bg-emerald-50 p-4' }}">
        @unless($compact)<p class="mb-3 text-xs font-bold uppercase tracking-wide text-emerald-800">Family account</p>@endunless
        <p class="text-xs font-bold text-emerald-800">{{ match ($onboarding->status) { 'completed' => 'Onboarding completed', 'exempted' => 'Onboarding no longer required', default => 'Onboarding in progress' } }}</p>
        <p class="text-xs text-slate-600">Signed up {{ $onboarding->created_at->format('M j, Y') }}</p>
        @if($visit = $onboarding->welcomeVisit)
            <p class="text-xs text-slate-600">Welcome visit: {{ $visit->status === 'requested' ? 'requested · awaiting confirmation' : ucfirst($visit->status) }}</p>
        @endif
        @unless($compact)
            <p class="mt-2 text-xs leading-5 text-slate-600">{{ match ($onboarding->status) { 'completed' => 'Submitted care details are saved in the timeline below.', 'exempted' => 'The family account owner has changed. Review the account before following up.', default => 'Their contact details are ready. Care details will appear here once they finish onboarding.' } }}</p>
            @if(auth()->user()?->isAdministrator())
                <a href="{{ route('admin.family-onboarding.show', $onboarding) }}" wire:navigate class="mt-3 inline-block text-xs font-bold text-emerald-800 underline">View onboarding details →</a>
            @endif
        @endunless
    </div>
@endif
