<div class="hc-page space-y-7 py-8 sm:py-10">
    @if (session('partner-profile-saved'))
        <div role="status" class="rounded-2xl border border-[#C9D9D2] bg-[#EDF3ED] px-5 py-4 text-sm font-semibold text-[#23483F]">Your partner profile has been updated.</div>
    @endif

    <header class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0 max-w-3xl">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#B95745]">Partner workspace</p>
            <h1 class="mt-3 break-words text-3xl font-semibold tracking-tight sm:text-4xl">{{ $profile->business_name }}</h1>
            <p class="mt-3 text-sm leading-6 text-[#66746D]">Your business, your services and the communities you serve.</p>
        </div>
        <a href="{{ route('partner.profile.edit') }}" wire:navigate class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-xl bg-[#23483F] px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-[#173F35] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#23483F]">Edit partner profile</a>
    </header>

    @php
        $statusContent = match ($profile->status) {
            'approved' => ['title' => 'Welcome to the LoLo partner network', 'body' => 'Your profile is approved. Keep your services and coverage up to date so our team knows where your business can help.'],
            'paused' => ['title' => 'Your partnership is paused', 'body' => 'You can still update your business details. Contact our team when you are ready to discuss next steps.'],
            default => ['title' => 'Thanks for joining us', 'body' => 'Your registration is complete and your profile is ready for our team to review. You can update your details at any time.'],
        };
    @endphp
    <section class="rounded-2xl border border-[#D9E3D8] bg-[#EDF3ED] p-5 sm:p-6" aria-label="Partner account status">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-xl font-semibold">{{ $statusContent['title'] }}</h2>
            <span class="w-fit rounded-full border border-[#CDDCCE] bg-[#FFFCF7] px-3 py-1.5 text-xs font-bold text-[#315B4D]">{{ $profile->statusLabel() }}</span>
        </div>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-[#52665B]">{{ $statusContent['body'] }}</p>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-[#E3D6C5] bg-[#FFFCF7] p-5 shadow-sm sm:p-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-[#66746D]">What you offer</p>
            <h2 class="mt-2 text-2xl font-semibold">Your services</h2>
            <div class="mt-5 flex flex-wrap gap-2">
                @foreach ($profile->categoryLabels() as $category)
                    <span class="rounded-xl border border-[#DCE5DC] bg-[#F1F5EF] px-4 py-3 text-sm font-semibold text-[#23483F]">{{ $category }}</span>
                @endforeach
            </div>
            @if ($profile->customer_notes)
                <div class="mt-6 border-t border-[#E8DED1] pt-5">
                    <h3 class="text-base font-semibold">Customer preferences</h3>
                    <p class="mt-2 whitespace-pre-line break-words text-sm leading-6 text-[#66746D]">{{ $profile->customer_notes }}</p>
                </div>
            @endif
        </section>

        <section class="rounded-2xl border border-[#E3D6C5] bg-[#FFFCF7] p-5 shadow-sm sm:p-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-[#66746D]">Where you work</p>
            <h2 class="mt-2 text-2xl font-semibold">Your service area</h2>
            <div class="mt-5 flex flex-wrap gap-2">
                @foreach ($profile->coverageStateLabels() as $state)
                    <span class="rounded-full bg-[#F2EBDD] px-3 py-1.5 text-sm font-semibold text-[#52665B]">{{ $state }}</span>
                @endforeach
            </div>
            <p class="mt-4 whitespace-pre-line break-words text-sm leading-6 text-[#66746D]">{{ $profile->coverage_type === 'entire_states' ? 'All areas in the selected states.' : $profile->coverage_areas }}</p>
        </section>

        <section class="rounded-2xl border border-[#E3D6C5] bg-[#FFFCF7] p-5 shadow-sm sm:p-6">
            <h2 class="text-2xl font-semibold">About your business</h2>
            @if ($profile->description)
                <p class="mt-4 whitespace-pre-line break-words text-sm leading-7 text-[#66746D]">{{ $profile->description }}</p>
            @else
                <p class="mt-4 text-sm leading-7 text-[#66746D]">Add a short description to tell our team more about your business.</p>
            @endif
            @if ($profile->website)
                <a href="{{ $profile->website }}" target="_blank" rel="noopener noreferrer" class="mt-4 inline-block max-w-full break-all text-sm font-semibold text-[#315B4D] underline underline-offset-4">{{ preg_replace('#^https?://#', '', $profile->website) }}</a>
            @endif
        </section>

        <section class="rounded-2xl border border-[#E3D6C5] bg-[#FFFCF7] p-5 shadow-sm sm:p-6">
            <h2 class="text-2xl font-semibold">Your main contact</h2>
            <dl class="mt-4 space-y-4 text-sm">
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-[#66746D]">Name</dt><dd class="mt-1 break-words font-semibold text-[#23483F]">{{ $profile->contact_name }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-[#66746D]">Email</dt><dd class="mt-1 break-all"><a href="mailto:{{ $profile->contact_email }}" class="font-semibold text-[#315B4D] underline underline-offset-4">{{ $profile->contact_email }}</a></dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-[#66746D]">Phone</dt><dd class="mt-1"><a href="tel:{{ preg_replace('/[^+0-9]/', '', $profile->contact_phone) }}" class="font-semibold text-[#315B4D] underline underline-offset-4">{{ $profile->contact_phone }}</a></dd></div>
            </dl>
        </section>
    </div>

    <aside class="flex flex-col gap-4 rounded-2xl border border-[#E3D6C5] bg-[#F6EFE3] p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
        <div><h2 class="text-lg font-semibold">Let’s keep in touch</h2><p class="mt-1 text-sm leading-6 text-[#66746D]">Questions about your partnership? Our team is here to help.</p></div>
        <a href="mailto:hello@carelolo.com" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl border border-[#BDB9A8] bg-[#FFFCF7] px-5 py-2.5 text-sm font-bold text-[#23483F] hover:bg-white">Contact LoLo</a>
    </aside>
</div>
