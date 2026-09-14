@props(['lead', 'compact' => false])
@php($welcome = $lead->welcomeEmail)
@if($welcome)
    <div class="{{ $compact ? 'space-y-1' : 'rounded-2xl border border-[#D9CEC0] bg-[#FFFBF4] p-4' }}">
        @unless($compact)<p class="mb-3 text-[11px] font-bold uppercase tracking-[0.14em] text-[#A55343]">Email & online progress</p>@endunless
        <p class="text-xs font-semibold {{ in_array($welcome->status, ['failed', 'retrying']) ? 'text-rose-700' : 'text-slate-600' }}">{{ $welcome->statusLabel() }}@if($welcome->sent_at || $welcome->previewed_at)<span class="font-normal"> · {{ ($welcome->sent_at ?: $welcome->previewed_at)->format('M j, g:i A') }}</span>@endif</p>
        @if($welcome->reason)<p class="text-xs leading-5 text-slate-500">{{ $welcome->reason }}</p>@endif
        <p class="{{ $compact ? 'text-xs' : 'mt-3 text-sm' }} font-bold text-[#23483F]">{{ $welcome->progressLabel() }}</p>
        @unless($compact)
            @if($welcome->account_created_at)<p class="mt-1 text-xs text-slate-500">Account created {{ $welcome->account_created_at->format('M j, g:i A') }}</p>@endif
            @if($welcome->request_posted_at)<p class="mt-1 text-xs text-slate-500">Request posted {{ $welcome->request_posted_at->format('M j, g:i A') }}</p>@endif
            @if($welcome->care_request_id && auth()->user()?->isAdministrator())<a href="{{ route('admin.requests.show', $welcome->care_request_id) }}" wire:navigate class="mt-3 inline-block text-xs font-bold text-emerald-800 underline">View care request →</a>@endif
            <p class="mt-3 text-xs leading-5 text-slate-500">{{ $welcome->request_posted_at ? 'Their request is posted. Help with the next steps when you call.' : ($welcome->account_linked_at ? 'Their account is ready. Help them finish posting their request.' : 'They can get started from the email, or you can guide them on the call.') }}</p>
        @endunless
    </div>
@else
    <p class="text-xs text-slate-400">{{ $lead->isFacebookLead() ? 'No welcome email recorded' : 'Welcome email applies to Facebook leads' }}</p>
@endif
