<div class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
    <div>
        <a href="{{ route('admin.ai-support.index') }}" wire:navigate class="text-sm font-semibold text-emerald-700 hover:underline">&larr; AI Support overview</a>
        <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-950">Restricted AI settings</h1>
        <p class="mt-2 text-sm text-slate-600">Every change creates a new immutable control version and audit event. Missing state always fails closed.</p>
    </div>

    @if(session('status'))<x-alert color="green">{{ session('status') }}</x-alert>@endif
    @if(! $runtimeAvailable)<x-alert color="blue">The deployment runtime guard is off. Stored control changes cannot expose customer AI.</x-alert>@endif
    @if(! $providerEnabled)<x-alert color="blue">The provider deployment guard is off. Stored controls cannot cause a model call.</x-alert>@endif
    @error('controlKey')<x-alert color="red">{{ $message }}</x-alert>@enderror

    <x-card>
        <x-slot:header><h2 class="text-lg font-semibold">Current control versions</h2></x-slot:header>
        <div class="divide-y divide-slate-200">
            @foreach($states as $key => $state)
                <div class="flex items-center justify-between gap-4 py-3">
                    <div>
                        <p class="font-semibold text-slate-900">{{ str($key)->replace(['.', '_'], ' ')->headline() }}</p>
                        <p class="text-xs text-slate-500">{{ $state['source'] === 'stored' ? 'Stored version '.$state['version'] : 'Safe default; no stored version' }}</p>
                    </div>
                    <x-badge :text="$state['enabled'] ? 'ON' : 'OFF'" :color="$state['enabled'] ? ($key === 'human_only' ? 'blue' : 'green') : 'slate'" />
                </div>
            @endforeach
        </div>
    </x-card>

    <x-card>
        <x-slot:header><h2 class="text-lg font-semibold">Record a control change</h2></x-slot:header>
        <form wire:submit="changeControl" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-semibold text-slate-800">Control
                    <select wire:model="controlKey" class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-base">
                        @foreach($states as $key => $state)<option value="{{ $key }}">{{ str($key)->replace(['.', '_'], ' ')->headline() }}</option>@endforeach
                    </select>
                </label>
                <label class="text-sm font-semibold text-slate-800">New state
                    <select wire:model="desiredEnabled" class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-base">
                        <option value="0">Off</option><option value="1">On</option>
                    </select>
                </label>
            </div>
            <label class="block text-sm font-semibold text-slate-800">Operational reason
                <textarea wire:model="controlReason" maxlength="500" rows="3" class="mt-1 w-full rounded-xl border-slate-300 text-base" placeholder="No transcript or care details"></textarea>
            </label>
            @error('controlReason')<p class="text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                Changing a control can block or permit eligibility for named grants. It never bypasses the deployment guard, role policy, exact-user grant, capability state, or conversation ownership.
            </div>
            <label class="flex items-start gap-3 text-sm text-slate-800"><input wire:model="impactConfirmed" type="checkbox" class="mt-1 rounded border-slate-300"> <span>I reviewed the impact and want this version applied.</span></label>
            @error('impactConfirmed')<p class="text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
            <label class="block text-sm font-semibold text-slate-800">Type APPLY to confirm
                <input wire:model="confirmationText" type="text" autocomplete="off" class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-base">
            </label>
            @error('confirmationText')<p class="text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
            <x-button type="submit" color="red" class="min-h-11 justify-center">Apply control version</x-button>
        </form>
    </x-card>
</div>
