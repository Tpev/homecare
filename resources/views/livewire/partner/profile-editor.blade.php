<div class="mx-auto max-w-4xl space-y-7 px-4 py-8 sm:px-6 sm:py-10">
    <header>
        <a href="{{ route('partner.dashboard') }}" wire:navigate class="text-sm font-semibold text-[#52665B] underline underline-offset-4">Back to your dashboard</a>
        <p class="mt-6 text-xs font-bold uppercase tracking-[0.18em] text-[#B95745]">Partner profile</p>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Keep us up to date</h1>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-[#66746D]">Update your business, contact details and the areas you serve.</p>
    </header>

    @if ($errors->any())
        <div role="alert" class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800">Please check the highlighted fields before saving.</div>
    @endif

    <form wire:submit="save" class="space-y-6">
        <section class="rounded-2xl border border-[#E3D6C5] bg-[#FFFCF7] p-5 shadow-sm sm:p-7">
            <h2 class="mb-6 text-2xl font-semibold">Business & contact</h2>
            <x-partner-profile-fields section="business" />
        </section>
        <section class="rounded-2xl border border-[#E3D6C5] bg-[#FFFCF7] p-5 shadow-sm sm:p-7">
            <h2 class="mb-6 text-2xl font-semibold">Services & coverage</h2>
            <x-partner-profile-fields section="services" :categories="$categories" :states="$states" :selected-states="$form->coverage_states" :coverage-type="$form->coverage_type" />
        </section>
        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
            <a href="{{ route('partner.dashboard') }}" wire:navigate class="inline-flex min-h-12 items-center justify-center rounded-xl px-5 py-3 text-sm font-semibold text-[#52665B] underline underline-offset-4">Cancel</a>
            <button type="submit" wire:loading.attr="disabled" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-[#23483F] px-6 py-3 text-sm font-bold text-white shadow-sm hover:bg-[#173F35] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#23483F] disabled:cursor-wait disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Save changes</span>
                <span wire:loading wire:target="save">Saving changes…</span>
            </button>
        </div>
    </form>
</div>
