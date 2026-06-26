@extends('layouts.marketing')

@section('title', 'SMS Opt-In Evidence | LoLo Care')
@section('meta_description', 'Public evidence page showing where LoLo Care collects SMS opt-in consent on the get-care callback form.')
@section('canonical', route('legal.sms-opt-in-evidence'))
@section('robots', 'noindex,follow')

@section('content')
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <header class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                <a href="{{ route('landing') }}" class="flex items-center gap-3">
                    <x-application-logo class="h-9 w-9" />
                    <div>
                        <div class="text-base font-bold tracking-tight">LoLo Care</div>
                        <div class="text-xs text-slate-500">SMS opt-in evidence</div>
                    </div>
                </a>
                <a href="{{ route('landing.get-care') }}" class="text-sm font-semibold text-cyan-700 hover:underline">Open live form</a>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
            <section class="grid gap-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:items-start">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-cyan-700">Twilio A2P evidence</p>
                    <h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">SMS opt-in appears on Step 7 of the public get-care form.</h1>
                    <p class="mt-4 text-sm leading-6 text-slate-600">
                        This page documents the public opt-in flow for LoLo Care Inc. The live form is available at
                        <a href="{{ route('landing.get-care') }}" class="font-semibold text-cyan-700 underline">{{ route('landing.get-care') }}</a>.
                        The SMS consent checkbox appears on the final contact step before a visitor can submit the callback request.
                    </p>

                    <div class="mt-6 space-y-3 rounded-xl border border-cyan-100 bg-cyan-50 p-4 text-sm text-cyan-950">
                        <p class="font-bold">Opt-in details shown to the user</p>
                        <ul class="list-disc space-y-2 pl-5">
                            <li>Form URL: <a href="{{ route('landing.get-care') }}" class="font-semibold underline">{{ route('landing.get-care') }}</a></li>
                            <li>Opt-in location: Step 7 of 7, labeled "Final step" and "Where should we call you?"</li>
                            <li>Consent is collected with an unchecked checkbox next to the SMS/call disclosure.</li>
                            <li>The checkbox is required before the callback request can be submitted.</li>
                            <li>The disclosure includes text/call consent, message and data rates, STOP opt-out instructions, and a Privacy Policy link.</li>
                        </ul>
                    </div>

                    <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Exact consent text</p>
                        <blockquote class="mt-2 border-l-4 border-cyan-600 pl-4 text-sm font-semibold leading-6 text-slate-800">
                            I agree LoLo may call or text me about this care request. Message and data rates may apply. I can reply STOP to texts.
                            <a href="{{ route('legal.show', ['slug' => 'privacy-policy']) }}" class="text-cyan-700 underline">Privacy policy</a>
                        </blockquote>
                    </div>

                    <p class="mt-6 text-xs leading-5 text-slate-500">
                        Privacy policy note: LoLo Care Inc states that mobile information, text messaging opt-in data, and SMS consent are not shared with third parties or affiliates for marketing or promotional purposes.
                    </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-[#f5f1eb]">
                        <div class="flex items-center gap-2 border-b border-slate-200 bg-white px-4 py-3">
                            <span class="h-3 w-3 rounded-full bg-red-300"></span>
                            <span class="h-3 w-3 rounded-full bg-amber-300"></span>
                            <span class="h-3 w-3 rounded-full bg-emerald-300"></span>
                            <div class="ml-3 min-w-0 flex-1 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                {{ route('landing.get-care') }}
                            </div>
                        </div>

                        <div class="grid gap-5 p-4 sm:p-6">
                            <div class="flex items-center justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <x-application-logo class="h-10 w-10" />
                                    <div>
                                        <p class="text-lg font-black leading-none text-[#0f3d3e]">LoLo</p>
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Get care</p>
                                    </div>
                                </div>
                                <p class="rounded-full border border-[#0f3d3e]/10 bg-white px-3 py-1 text-xs font-bold text-[#0f3d3e]">Step 7 of 7</p>
                            </div>

                            <div class="rounded-xl border border-[#0f3d3e]/15 bg-white p-4 shadow-sm sm:p-5">
                                <div class="mb-5">
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <p class="text-xs font-bold uppercase tracking-[0.14em] text-[#b95745]">Final step</p>
                                            <h2 class="mt-1 text-2xl font-black tracking-tight text-[#0f3d3e]">Where should we call you?</h2>
                                        </div>
                                        <p class="hidden text-xs font-bold text-slate-500 sm:block">Takes about 2 minutes</p>
                                    </div>
                                    <p class="mt-2 text-sm leading-6 text-slate-600">Add your contact details and anything useful for the first conversation.</p>
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2">
                                    <label class="grid gap-1 text-xs font-bold text-slate-600">
                                        Your name
                                        <span class="rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-400">Sarah Martin</span>
                                    </label>
                                    <label class="grid gap-1 text-xs font-bold text-slate-600">
                                        Phone number
                                        <span class="rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-400">(984) 400-4008</span>
                                    </label>
                                    <label class="grid gap-1 text-xs font-bold text-slate-600">
                                        Email, optional
                                        <span class="rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-400">you@example.com</span>
                                    </label>
                                    <label class="grid gap-1 text-xs font-bold text-slate-600">
                                        Care ZIP code
                                        <span class="rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-400">27601</span>
                                    </label>
                                </div>

                                <label class="mt-3 grid gap-1 text-xs font-bold text-slate-600">
                                    Anything we should know before calling?
                                    <span class="min-h-20 rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm font-semibold leading-6 text-slate-400">Example: We are looking for companionship twice a week and help with light meals.</span>
                                </label>

                                <div class="mt-4 rounded-xl border-2 border-cyan-500 bg-cyan-50 p-1">
                                    <div class="rounded-lg border border-[#0f3d3e]/15 bg-white p-3">
                                        <div class="grid grid-cols-[1.25rem_minmax(0,1fr)] gap-3">
                                            <span class="mt-0.5 flex h-5 w-5 items-center justify-center rounded border-2 border-[#0f3d3e] bg-white text-xs font-black text-[#0f3d3e]" aria-hidden="true"></span>
                                            <div>
                                                <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-cyan-700">Required SMS/call consent checkbox</p>
                                                <p class="mt-1 text-sm font-semibold leading-6 text-slate-800">
                                                    I agree LoLo may call or text me about this care request. Message and data rates may apply. I can reply STOP to texts.
                                                    <span class="text-cyan-700 underline">Privacy policy</span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4 grid grid-cols-[auto_minmax(0,1fr)] gap-3">
                                    <span class="inline-flex min-h-12 items-center justify-center rounded-lg border border-slate-200 px-4 text-sm font-bold text-[#0f3d3e]">Back</span>
                                    <span class="inline-flex min-h-12 items-center justify-center rounded-lg bg-[#0f3d3e] px-4 text-sm font-bold text-white">Request my callback</span>
                                </div>

                                <p class="mt-3 text-center text-xs font-semibold leading-5 text-slate-500">Care starts at $30/hr. LoLo provides non-medical support and is not for emergencies.</p>
                            </div>
                        </div>
                    </div>

                    <p class="mt-3 text-center text-xs leading-5 text-slate-500">
                        Static evidence rendering of the final public form step. The live interactive form is at
                        <a href="{{ route('landing.get-care') }}" class="font-semibold text-cyan-700 underline">{{ route('landing.get-care') }}</a>.
                    </p>
                </div>
            </section>
        </main>
    </div>
@endsection
