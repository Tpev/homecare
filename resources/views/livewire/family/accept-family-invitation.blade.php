<div class="space-y-7" aria-live="polite">
    @if (! $emailMatches)
        <div class="space-y-4" tabindex="-1" autofocus>
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#B95745]">Family access</p>
            <h1 class="font-semibold tracking-tight">Use the invited email</h1>
            <p class="text-sm leading-6 text-[#68756F]">Sign in with the email address that received this invitation.</p>
            <x-button wire:click="signInWithAnotherAccount" color="blue" class="auth-primary min-h-11 w-full justify-center">Sign in with another account</x-button>
        </div>
    @else
        <div class="space-y-2" tabindex="-1" autofocus>
            <p class="mb-3 text-xs font-bold uppercase tracking-[0.18em] text-[#B95745]">Family access</p>
            <h1 class="font-semibold tracking-tight">Help {{ $ownerFirstName }} manage care</h1>
            <p class="text-sm leading-6 text-[#68756F]">You will share access to this family's care requests, visits, caregivers, messages, and care history.</p>
        </div>

        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
            You will be able to schedule care, message caregivers, and approve care-related charges using the family's saved payment method. Your actions will be recorded under your name.
        </div>

        <x-input-error :messages="$errors->get('invitation')" />

        <div class="grid gap-3 sm:grid-cols-2">
            <x-button wire:click="join" color="blue" class="auth-primary min-h-11 w-full justify-center">Join family account</x-button>
            <button type="button" wire:click="notNow" class="min-h-11 rounded-xl border border-[#D7CEC2] px-4 font-semibold text-[#526474] hover:bg-[#F7F2EA]">Not now</button>
        </div>
    @endif
</div>
