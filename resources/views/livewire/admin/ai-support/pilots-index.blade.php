<div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
    <div>
        <a href="{{ route('admin.ai-support.index') }}" wire:navigate class="text-sm font-semibold text-emerald-700 hover:underline">&larr; AI Support overview</a>
        <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-950">AI pilot users</h1>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Only an exact user with an effective grant can pass the grant check. Access never extends to another member of the same account.</p>
    </div>

    <x-card>
        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_220px]">
            <label class="text-sm font-semibold text-slate-800">Search
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Name, email, user ID, or grant ID" class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-base">
            </label>
            <label class="text-sm font-semibold text-slate-800">Status
                <select wire:model.live="status" class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-base">
                    <option value="current">Current</option>
                    <option value="active">Effective now</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="ended">Expired or revoked</option>
                    <option value="all">All history</option>
                </select>
            </label>
        </div>
    </x-card>

    <div class="space-y-3">
        @forelse($grants as $grant)
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" wire:key="pilot-grant-{{ $grant->id }}">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-bold text-slate-950">{{ $grant->user?->name ?: 'Deleted user' }}</h2>
                            <x-badge :text="strtoupper($grant->status())" :color="$grant->status() === 'active' ? 'green' : ($grant->status() === 'scheduled' ? 'blue' : 'slate')" />
                            <x-badge :text="strtoupper((string) ($grant->user?->role ?: 'unknown'))" color="slate" />
                        </div>
                        <p class="mt-1 break-all text-sm text-slate-600">{{ $grant->user?->email ?: 'User record removed' }} · User #{{ $grant->user_id }}</p>
                        <p class="mt-2 text-sm text-slate-700"><strong>{{ config('ai_support.bundles.'.$grant->bundle_key.'.label', $grant->bundle_key) }}</strong> · Grant {{ $grant->id }}</p>
                        <p class="mt-1 text-sm text-slate-600">Starts {{ $grant->starts_at->format('M j, Y g:i A') }} · {{ $grant->expires_at ? 'expires '.$grant->expires_at->format('M j, Y g:i A') : 'No expiry' }}</p>
                        <p class="mt-1 text-sm text-slate-600">Granted by {{ $grant->grantedBy?->name ?: 'Former administrator' }} · {{ $grant->grant_reason }}</p>
                    </div>
                    @if($grant->user)
                        <a href="{{ route('admin.users.show', $grant->user) }}" wire:navigate class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-800">Open user</a>
                    @endif
                </div>
            </article>
        @empty
            <x-card><p class="text-sm text-slate-600">No pilot grants match this view. AI remains unavailable to users without an exact grant.</p></x-card>
        @endforelse
    </div>

    {{ $grants->links() }}
</div>
