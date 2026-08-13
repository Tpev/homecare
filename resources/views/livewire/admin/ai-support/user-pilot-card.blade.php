<x-card>
    <x-slot:header>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold">AI pilot access</h2>
                <p class="mt-1 text-xs text-slate-500">Exact user #{{ $targetUser->id }} only · never inherited by account members</p>
            </div>
            <x-badge
                :text="$currentGrant ? strtoupper($currentGrant->status()) : 'NOT ENABLED'"
                :color="$currentGrant?->status() === 'active' ? 'green' : ($currentGrant ? 'blue' : 'slate')"
            />
        </div>
    </x-slot:header>

    <div class="space-y-5">
        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 p-3"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Role</p><p class="mt-1 font-semibold text-slate-900">{{ str($targetUser->role)->headline() }}</p></div>
            <div class="rounded-xl border border-slate-200 p-3 sm:col-span-2"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Effective eligibility</p><p class="mt-1 font-semibold {{ $eligibility->allowed ? 'text-emerald-700' : 'text-slate-900' }}">{{ $eligibility->allowed ? 'Eligible' : str($eligibility->reasonCode)->replace('_', ' ')->headline() }}</p></div>
        </div>

        @if($currentGrant)
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-950">
                <p class="font-bold">{{ config('ai_support.bundles.'.$currentGrant->bundle_key.'.label', $currentGrant->bundle_key) }}</p>
                <p class="mt-1">Starts {{ $currentGrant->starts_at->format('M j, Y g:i A') }} · {{ $currentGrant->expires_at ? 'expires '.$currentGrant->expires_at->format('M j, Y g:i A') : 'No expiry; manual revocation required' }}</p>
                <p class="mt-1">Reason: {{ $currentGrant->grant_reason }}</p>
                @if(! $eligibility->allowed)<p class="mt-2 font-semibold">Stored grant is blocked by: {{ str($eligibility->reasonCode)->replace('_', ' ')->headline() }}.</p>@endif
            </div>

            @if(auth()->user()->canManageAiSupportPilot())
                <form wire:submit="disablePilot" class="space-y-3 rounded-xl border border-rose-200 bg-rose-50 p-4">
                    <h3 class="font-bold text-rose-950">Disable now</h3>
                    <p class="text-sm text-rose-900">Revocation is immediate. It does not delete the support conversation, and human support remains available.</p>
                    <label class="block text-sm font-semibold text-rose-950">Reason
                        <textarea wire:model="revocationReason" maxlength="500" rows="2" class="mt-1 w-full rounded-xl border-rose-300 bg-white text-base" placeholder="No care or transcript details"></textarea>
                    </label>
                    @error('revocationReason')<p class="text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
                    <label class="flex items-start gap-3 text-sm text-rose-950"><input wire:model="revocationImpactConfirmed" type="checkbox" class="mt-1 rounded border-rose-300"> <span>I understand this exact user loses AI eligibility immediately.</span></label>
                    @error('revocationImpactConfirmed')<p class="text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
                    <x-button type="submit" color="red" class="min-h-11 justify-center">Disable AI pilot</x-button>
                </form>
            @endif
        @elseif(in_array($targetUser->role, config('ai_support.supported_roles', []), true) && auth()->user()->canManageAiSupportPilot())
            <form wire:submit="enablePilot" class="space-y-4 rounded-xl border border-slate-200 p-4">
                <div><h3 class="font-bold text-slate-950">Enable AI pilot for {{ $targetUser->name }}?</h3><p class="mt-1 text-sm text-slate-600">This stores access for this user ID only. Global, role, capability, safety, and human-only controls still apply.</p></div>
                <label class="block text-sm font-semibold text-slate-800">Pilot bundle
                    <select wire:model="grantBundleKey" class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-base">
                        @foreach($bundles as $key => $bundle)<option value="{{ $key }}">{{ $bundle['label'] }}</option>@endforeach
                    </select>
                </label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="text-sm font-semibold text-slate-800">Starts
                        <input wire:model="grantStartsAt" type="datetime-local" class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-base">
                    </label>
                    <label class="text-sm font-semibold text-slate-800">Expires
                        <input wire:model="grantExpiresAt" type="datetime-local" @disabled($grantNoExpiry) class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-base disabled:bg-slate-100">
                    </label>
                </div>
                @error('grantStartsAt')<p class="text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
                @error('grantExpiresAt')<p class="text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
                <label class="flex items-start gap-3 text-sm text-slate-800"><input wire:model.live="grantNoExpiry" type="checkbox" class="mt-1 rounded border-slate-300"> <span>No expiry</span></label>
                @if($grantNoExpiry)
                    <label class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950"><input wire:model="noExpiryAcknowledged" type="checkbox" class="mt-1 rounded border-amber-300"> <span>I understand this grant remains active until an administrator manually revokes it.</span></label>
                    @error('noExpiryAcknowledged')<p class="text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
                @endif
                <label class="block text-sm font-semibold text-slate-800">Reason
                    <textarea wire:model="grantReason" maxlength="500" rows="2" class="mt-1 w-full rounded-xl border-slate-300 text-base" placeholder="Example: scheduled usability pilot. Do not include care details."></textarea>
                </label>
                @error('grantReason')<p class="text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
                @error('grant')<p class="text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">This grant may remain blocked while the deployment guard or a higher-level control is off. It never turns those controls on.</div>
                <label class="flex items-start gap-3 text-sm text-slate-800"><input wire:model="grantImpactConfirmed" type="checkbox" class="mt-1 rounded border-slate-300"> <span>I confirm the named user, dates, bundle, and exact-user-only scope.</span></label>
                @error('grantImpactConfirmed')<p class="text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
                <x-button type="submit" color="green" class="min-h-11 justify-center">Enable AI pilot</x-button>
            </form>
        @else
            <x-alert color="blue">This user role is not supported by a pilot bundle. No grant can be created.</x-alert>
        @endif

        <details class="rounded-xl border border-slate-200 p-4">
            <summary class="cursor-pointer font-semibold text-slate-900">Grant history ({{ $history->count() }})</summary>
            <div class="mt-3 space-y-3">
                @forelse($history as $grant)
                    <div class="rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
                        <p class="font-semibold text-slate-900">{{ strtoupper($grant->status()) }} · {{ $grant->bundle_key }}</p>
                        <p class="mt-1">{{ $grant->starts_at->format('M j, Y g:i A') }} → {{ $grant->expires_at?->format('M j, Y g:i A') ?: 'No expiry' }}</p>
                        <p class="mt-1">Granted by {{ $grant->grantedBy?->name ?: 'Former administrator' }} · {{ $grant->grant_reason }}</p>
                        @if($grant->revoked_at)<p class="mt-1 text-rose-800">Revoked {{ $grant->revoked_at->format('M j, Y g:i A') }} by {{ $grant->revokedBy?->name ?: 'Former administrator' }} · {{ $grant->revocation_reason }}</p>@endif
                        <p class="mt-1 text-xs text-slate-500">Evidence scheduled through {{ $grant->retain_until?->format('M j, Y') ?: 'lifecycle end + 24 months' }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No grant history.</p>
                @endforelse
            </div>
        </details>
    </div>
</x-card>
