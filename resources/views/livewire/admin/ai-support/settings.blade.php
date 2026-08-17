<div class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
    <div>
        <a href="{{ route('admin.ai-support.index') }}" wire:navigate class="text-sm font-semibold text-emerald-700 hover:underline">&larr; AI Support overview</a>
        <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-950">AI availability</h1>
        <p class="mt-2 text-sm text-slate-600">Choose the two-user pilot or make AI Support live for everyone. That is the complete release workflow.</p>
    </div>

    @if(session('status'))<x-alert color="green">{{ session('status') }}</x-alert>@endif
    @if(! $runtimeAvailable || ! $providerEnabled)
        <x-alert color="blue">The server AI configuration is unavailable. The selected mode is saved, but model responses remain off until both server guards are enabled.</x-alert>
    @endif

    <div class="grid gap-5 lg:grid-cols-2">
        <x-card>
            <x-slot:header>
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Current mode</p>
                        <h2 class="mt-1 text-2xl font-extrabold text-slate-950">{{ $mode }}</h2>
                    </div>
                    <x-badge
                        :text="$humanOnlyState['enabled'] ? 'STOPPED' : ($generalReleaseState['enabled'] ? 'EVERYONE' : 'PILOT')"
                        :color="$humanOnlyState['enabled'] ? 'red' : ($generalReleaseState['enabled'] ? 'green' : 'blue')"
                    />
                </div>
            </x-slot:header>

            <div class="space-y-4">
                <p class="text-sm leading-6 text-slate-700">
                    @if($generalReleaseState['enabled'])
                        Every Family and Caregiver user can use role-appropriate AI help. Family request creation remains Family-only.
                    @else
                        Only users with an active pilot grant can use AI. There are currently <strong>{{ $activeGrants }}</strong> active pilot users.
                    @endif
                </p>

                @if($generalReleaseState['enabled'])
                    <button type="button" wire:click="usePilotOnly" wire:loading.attr="disabled" class="inline-flex min-h-12 w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-5 text-base font-bold text-slate-900 hover:bg-slate-50 disabled:opacity-50">
                        Switch back to pilot only
                    </button>
                @else
                    <button type="button" wire:click="enableForEveryone" wire:confirm="Make AI Support available to every Family and Caregiver user?" wire:loading.attr="disabled" class="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-emerald-700 px-5 text-base font-bold text-white hover:bg-emerald-800 disabled:opacity-50">
                        Make live for everyone
                    </button>
                @endif
            </div>
        </x-card>

        <x-card>
            <x-slot:header>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Emergency control</p>
                    <h2 class="mt-1 text-2xl font-extrabold text-slate-950">{{ $humanOnlyState['enabled'] ? 'Automation stopped' : 'Automation running' }}</h2>
                </div>
            </x-slot:header>

            <div class="space-y-4">
                <p class="text-sm leading-6 text-slate-700">This control immediately stops automated replies while keeping the same chat available to users and human support.</p>
                @if($humanOnlyState['enabled'])
                    <button type="button" wire:click="resumeAutomation" wire:loading.attr="disabled" class="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-emerald-700 px-5 text-base font-bold text-white hover:bg-emerald-800 disabled:opacity-50">Resume automation</button>
                @else
                    <button type="button" wire:click="stopAllAutomation" wire:loading.attr="disabled" class="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-rose-700 px-5 text-base font-bold text-white hover:bg-rose-800 disabled:opacity-50">Stop AI now</button>
                @endif
            </div>
        </x-card>
    </div>

    <x-card>
        <x-slot:header><h2 class="text-lg font-semibold">What remains automatic</h2></x-slot:header>
        <ul class="grid gap-3 text-sm text-slate-700 sm:grid-cols-2">
            <li class="rounded-xl border border-slate-200 p-4">Emergency and medical messages transfer to a person.</li>
            <li class="rounded-xl border border-slate-200 p-4">24/7 coverage requests transfer to a person.</li>
            <li class="rounded-xl border border-slate-200 p-4">Request publication still requires a recap and explicit confirmation.</li>
            <li class="rounded-xl border border-slate-200 p-4">Caregiver users receive only Caregiver-appropriate help.</li>
        </ul>
    </x-card>
</div>
