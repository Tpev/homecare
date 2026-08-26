@extends('layouts.marketing')

@section('title', 'Home Care. $30/Hour. On Demand. | HomeCare Raleigh')
@section('meta_description', 'Post what your parent needs, review caregivers, and book trusted home support in Raleigh without the agency back-and-forth.')
@section('canonical', route('landing.family.variant', ['variant' => 'b']))

@section('content')
    <div x-data="{ showSticky: false }" @scroll.window="showSticky = window.scrollY > 360" class="min-h-screen overflow-x-hidden bg-white pb-24 text-slate-900 sm:pb-0">
        <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
                <a href="{{ route('landing') }}" class="flex items-center gap-3">
                    <x-application-logo class="h-10 w-10 text-blue-600" />
                    <div class="leading-tight">
                        <div class="text-lg font-black tracking-tight">HomeCare</div>
                        <div class="text-xs uppercase tracking-[0.18em] text-slate-500">On-demand family care</div>
                    </div>
                </a>
                <div class="flex items-center gap-2">
                    <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50">Sign in</a>
                    <a href="{{ route('register') }}" class="inline-flex min-h-11 items-center rounded-full bg-blue-600 px-4 py-2 text-sm font-black text-white transition hover:bg-blue-500">Get Help Now</a>
                </div>
            </div>
        </header>

        <main>
            <section class="relative min-h-[78vh] px-6 pb-12 pt-16 sm:min-h-screen sm:pb-16">
                <div class="absolute left-1/4 top-24 h-72 w-72 rounded-full bg-blue-400/10 blur-[100px]"></div>
                <div class="absolute bottom-24 right-1/4 h-72 w-72 rounded-full bg-indigo-400/10 blur-[100px]"></div>
                <div class="mx-auto flex min-h-[68vh] max-w-7xl flex-col justify-center sm:min-h-[calc(100vh-6rem)]">
                    <div class="mx-auto max-w-4xl text-center">
                        <div class="inline-flex items-center gap-2 rounded-full bg-blue-50 px-4 py-2 text-xs font-black uppercase tracking-[0.24em] text-blue-700">
                            Raleigh &amp; Wake County Active
                        </div>
                        <h1 class="mt-8 text-5xl font-black uppercase leading-[0.95] tracking-[-0.05em] sm:text-7xl lg:text-8xl">
                            Home care.<br>
                            <span class="text-blue-600">$30/hour.*</span><br>
                            On demand.
                    </h1>
                    <p class="mt-3 text-sm text-slate-600">*Plus a $1/hour processing fee for completed care.</p>
                        <p class="mx-auto mt-8 max-w-2xl text-xl font-medium leading-8 text-slate-600 sm:text-2xl">
                            No agencies. No waiting.<br class="hidden sm:block">
                            Just post what you need and get help today.
                        </p>
                        <div class="mt-12 flex flex-col items-center gap-5">
                            <a href="{{ route('register') }}" class="inline-flex min-h-16 w-full items-center justify-center gap-3 rounded-2xl bg-blue-600 px-8 py-5 text-xl font-black text-white shadow-[0_24px_60px_rgba(37,99,235,0.22)] transition hover:bg-blue-500 sm:w-auto sm:px-12">
                                Get Help Now
                            </a>
                            <a href="#how" class="text-sm font-bold text-slate-400 underline decoration-2 underline-offset-4 transition hover:text-blue-600">See how it works</a>
                        </div>
                    </div>
                </div>
            </section>

            <section class="border-y border-slate-200 bg-slate-50 py-8">
                <div class="mx-auto max-w-7xl overflow-x-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex min-w-max justify-center gap-4 pb-2">
                        <div class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-800 shadow-sm">$30/hr flat rate</div>
                        <div class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-800 shadow-sm">Book in minutes</div>
                        <div class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-800 shadow-sm">No commitment</div>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden bg-white px-6 py-24" id="how">
                <div class="mx-auto flex max-w-7xl flex-col items-center gap-14 lg:flex-row">
                    <div class="flex-1 text-left">
                        <h2 class="text-4xl font-black uppercase leading-none tracking-[-0.04em] sm:text-5xl">
                            This is how <br>it works
                        </h2>
                        <p class="mt-6 text-lg font-medium leading-8 text-slate-600">
                            We built an interface as simple as your favorite food delivery app. Transparency and speed are baked in.
                        </p>
                        <div class="mt-8 space-y-6">
                            <div class="flex items-start gap-4">
                                <div class="mt-1 h-6 w-6 rounded-full bg-blue-100 text-center text-sm leading-6 text-blue-600">•</div>
                                <div><div class="font-bold text-slate-900">Post Request</div><div class="text-sm text-slate-500">Tap once to describe the need</div></div>
                            </div>
                            <div class="flex items-start gap-4">
                                <div class="mt-1 h-6 w-6 rounded-full bg-blue-100 text-center text-sm leading-6 text-blue-600">•</div>
                                <div><div class="font-bold text-slate-900">Instant Matching</div><div class="text-sm text-slate-500">View available caregivers near you</div></div>
                            </div>
                            <div class="flex items-start gap-4">
                                <div class="mt-1 h-6 w-6 rounded-full bg-blue-100 text-center text-sm leading-6 text-blue-600">•</div>
                                <div><div class="font-bold text-slate-900">Secure Payment</div><div class="text-sm text-slate-500">Handled through the app automatically</div></div>
                            </div>
                        </div>
                    </div>
                    <div class="relative flex-1">
                        <div class="mx-auto h-[580px] w-[300px] rounded-[3rem] border-[8px] border-slate-800 bg-slate-900 p-4 shadow-[0_0_100px_rgba(37,99,235,0.2)]">
                            <div class="flex h-full flex-col overflow-hidden rounded-[2rem] bg-white">
                                <div class="bg-blue-600 p-4 text-white">
                                    <div class="mx-auto mb-4 h-1 w-12 rounded-full bg-white/20"></div>
                                    <div class="text-sm font-bold uppercase">Caregivers Nearby</div>
                                </div>
                                <div class="flex-1 space-y-4 p-4">
                                    <div class="flex gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                                        <div class="h-10 w-10 rounded-full bg-slate-300"></div>
                                        <div class="min-w-0 flex-1"><div class="flex items-center justify-between gap-2"><span class="truncate text-xs font-black uppercase">Sarah M.</span><span class="rounded bg-yellow-100 px-1 text-[10px] font-bold text-yellow-700">4.9</span></div><p class="text-[10px] text-slate-500">HHA, 5 yrs exp</p></div>
                                        <div class="rounded bg-blue-600 px-2 py-1 text-[10px] font-bold uppercase text-white">Chat</div>
                                    </div>
                                    <div class="flex gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                                        <div class="h-10 w-10 rounded-full bg-slate-300"></div>
                                        <div class="min-w-0 flex-1"><div class="flex items-center justify-between gap-2"><span class="truncate text-xs font-black uppercase">David K.</span><span class="rounded bg-yellow-100 px-1 text-[10px] font-bold text-yellow-700">4.8</span></div><p class="text-[10px] text-slate-500">Specialized in mobility</p></div>
                                        <div class="rounded bg-blue-600 px-2 py-1 text-[10px] font-bold uppercase text-white">Chat</div>
                                    </div>
                                    <div class="flex gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                                        <div class="h-10 w-10 rounded-full bg-slate-300"></div>
                                        <div class="min-w-0 flex-1"><div class="flex items-center justify-between gap-2"><span class="truncate text-xs font-black uppercase">Elena R.</span><span class="rounded bg-yellow-100 px-1 text-[10px] font-bold text-yellow-700">5.0</span></div><p class="text-[10px] text-slate-500">Meals & errands specialist</p></div>
                                        <div class="rounded bg-blue-600 px-2 py-1 text-[10px] font-bold uppercase text-white">Chat</div>
                                    </div>
                                </div>
                                <div class="border-t border-slate-100 p-4">
                                    <div class="rounded-xl bg-slate-900 py-3 text-center text-xs font-bold uppercase text-white">Post New Request</div>
                                </div>
                            </div>
                        </div>
                        <div class="absolute right-0 top-10 hidden rounded-2xl border border-slate-200 bg-white p-4 shadow-xl lg:block">
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-green-500 text-white">✓</div>
                                <div><div class="text-[10px] font-black uppercase text-slate-400">Matched</div><div class="text-sm font-bold text-slate-900">Caregiver Arriving</div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-slate-50 px-6 py-24">
                <div class="mx-auto max-w-7xl">
                    <div class="grid gap-12 md:grid-cols-3">
                        <div><div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-600 text-xl font-black text-white">1</div><h3 class="mt-4 text-xl font-black uppercase tracking-tight">Post your need</h3><p class="mt-2 font-medium text-slate-600">Tell us what you need help with and when. It takes less than 60 seconds.</p></div>
                        <div><div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-600 text-xl font-black text-white">2</div><h3 class="mt-4 text-xl font-black uppercase tracking-tight">Review &amp; connect</h3><p class="mt-2 font-medium text-slate-600">See available caregivers instantly. Message them before you decide.</p></div>
                        <div><div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-600 text-xl font-black text-white">3</div><h3 class="mt-4 text-xl font-black uppercase tracking-tight">Book &amp; relax</h3><p class="mt-2 font-medium text-slate-600">Confirm, pay, and track everything from your phone. No paperwork.</p></div>
                    </div>
                </div>
            </section>

            <section class="relative overflow-hidden bg-blue-600 px-6 py-28 text-center text-white">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,_rgba(255,255,255,0.12)_0%,_transparent_70%)]"></div>
                <div class="relative mx-auto max-w-4xl">
                    <h2 class="text-6xl font-black uppercase leading-none tracking-[-0.06em] sm:text-8xl">
                        $30 per hour.<br>That’s it.
                    </h2>
                    <div class="mt-10 flex flex-wrap justify-center gap-6 text-lg font-bold uppercase">
                        <span>No hidden fees</span>
                        <span>No contracts</span>
                        <span>No intake calls</span>
                    </div>
                    <p class="mt-12 text-2xl font-medium text-white/80">Use it once or every day. You’re in control.</p>
                </div>
            </section>

            <section class="bg-white px-6 py-24">
                <div class="mx-auto max-w-7xl">
                    <div class="flex flex-col gap-16 lg:flex-row">
                        <div class="flex-1">
                            <h2 class="text-4xl font-black uppercase leading-none tracking-[-0.04em] sm:text-5xl">
                                Why families <br>are switching
                            </h2>
                            <div class="mt-8 space-y-4">
                                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4 text-lg font-bold uppercase tracking-tight text-slate-800">Start today, not next week</div>
                                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4 text-lg font-bold uppercase tracking-tight text-slate-800">Talk to caregivers before booking</div>
                                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4 text-lg font-bold uppercase tracking-tight text-slate-800">Only pay for what you use</div>
                                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4 text-lg font-bold uppercase tracking-tight text-slate-800">No locked schedules</div>
                                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4 text-lg font-bold uppercase tracking-tight text-slate-800">No intake calls or paperwork</div>
                            </div>
                            <p class="mt-12 text-xl font-bold uppercase italic tracking-tight text-slate-500">
                                “Care should work like everything else in your life—fast and flexible.”
                            </p>
                        </div>
                        <div class="grid flex-1 grid-cols-2 gap-4">
                            <div class="rounded-[2.5rem] bg-slate-100 p-8 text-center"><h4 class="text-xl font-black uppercase">Verified</h4><p class="mt-2 text-sm font-bold uppercase text-slate-500">Background checks included</p></div>
                            <div class="rounded-[2.5rem] bg-slate-900 p-8 text-center text-white"><h4 class="text-xl font-black uppercase">5-Star</h4><p class="mt-2 text-sm font-bold uppercase text-slate-400">Rated by real families</p></div>
                            <div class="col-span-2 rounded-[2.5rem] bg-blue-50 p-8 text-center"><h4 class="text-xl font-black uppercase">Secure &amp; Direct</h4><p class="mt-2 text-sm font-bold uppercase text-slate-500">Direct messaging + encrypted payments</p></div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-slate-50 px-6 py-24">
                <div class="mx-auto max-w-7xl">
                    <h2 class="text-center text-4xl font-black uppercase tracking-[-0.04em] sm:text-5xl">What people use this for</h2>
                    <div class="mt-12 grid grid-cols-2 gap-4 md:grid-cols-5">
                        <div class="rounded-3xl border border-slate-200 bg-white p-6 text-center shadow-sm"><div class="text-sm font-black uppercase tracking-tight">Check-ins</div></div>
                        <div class="rounded-3xl border border-slate-200 bg-white p-6 text-center shadow-sm"><div class="text-sm font-black uppercase tracking-tight">Post-Surgery</div></div>
                        <div class="rounded-3xl border border-slate-200 bg-white p-6 text-center shadow-sm"><div class="text-sm font-black uppercase tracking-tight">Companionship</div></div>
                        <div class="rounded-3xl border border-slate-200 bg-white p-6 text-center shadow-sm"><div class="text-sm font-black uppercase tracking-tight">Errands/Meals</div></div>
                        <div class="rounded-3xl border border-slate-200 bg-white p-6 text-center shadow-sm"><div class="text-sm font-black uppercase tracking-tight">Short Coverage</div></div>
                    </div>
                </div>
            </section>

            <section class="px-6 py-24 text-center">
                <div class="mx-auto max-w-3xl">
                    <h2 class="text-5xl font-black uppercase tracking-[-0.05em] sm:text-6xl">Need help<br class="sm:hidden"> this week?</h2>
                    <p class="mt-4 text-2xl font-bold uppercase tracking-tight text-slate-500">You can start today.</p>
                    <a href="{{ route('register') }}" class="mt-10 inline-flex min-h-16 w-full items-center justify-center rounded-2xl bg-slate-900 px-10 py-5 text-xl font-black uppercase text-white shadow-xl transition hover:bg-black sm:w-auto">
                        Post a Request
                    </a>
                </div>
            </section>
        </main>

        <div x-cloak x-show="showSticky" x-transition.opacity.duration.200ms class="fixed inset-x-0 bottom-0 z-50 border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur supports-[padding:max(0px)]:pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:hidden">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-xs font-black uppercase tracking-[0.22em] text-slate-500">Find Help — $30/hr</div>
                    <div class="truncate text-sm font-bold text-slate-900">No agencies. No waiting.</div>
                </div>
                <a href="{{ route('register') }}" class="shrink-0 rounded-2xl bg-blue-600 px-4 py-3 text-sm font-black text-white">Get Help</a>
            </div>
        </div>
    </div>
@endsection
