@extends('layouts.marketing')

@section('title', 'HomeCare HUB | Not an Agency. Just Help.')
@section('meta_description', 'On-demand support for real-life gaps in Raleigh. No medical fluff. No long-term contracts. Just help when you cannot be there.')
@section('canonical', route('landing.family.variant', ['variant' => 'e']))

@section('content')
    <div class="min-h-screen bg-black text-white">
        <header class="sticky top-0 z-40 border-b border-white/5 bg-black/90 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                <a href="{{ route('landing') }}" class="text-xl font-extrabold tracking-tighter text-white">
                    HOMECARE<span class="text-blue-500">HUB</span>
                </a>
                <div class="flex items-center gap-2">
                    <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center rounded-full bg-white px-4 py-2 text-sm font-bold text-black transition hover:bg-gray-200">
                        Sign In
                    </a>
                </div>
            </div>
        </header>

        <main>
            <section class="mx-auto max-w-7xl px-6 pb-20 pt-12 text-center md:flex md:items-center md:gap-12 md:pt-20 md:text-left">
                <div class="md:w-3/5">
                    <h1 class="text-balance text-5xl font-extrabold tracking-tight leading-[0.92] sm:text-6xl md:text-8xl">
                        Your parent needs help. <br>
                        <span class="text-blue-500">Not a process.</span>
                    </h1>

                    <p class="mt-8 max-w-xl text-xl text-gray-400 sm:text-2xl">
                        On-demand support for real-life gaps. No medical fluff. No long-term contracts. Just help when you can't be there.
                    </p>

                    <div class="mt-10 flex flex-col gap-4 sm:flex-row">
                        <a href="{{ route('register') }}" class="inline-flex min-h-14 items-center justify-center rounded-xl bg-blue-600 px-10 py-5 text-center text-lg font-bold text-white transition hover:shadow-[0_0_20px_rgba(59,130,246,0.5)]">
                            Find someone today
                        </a>
                        <a href="#how" class="inline-flex min-h-14 items-center justify-center rounded-xl border border-white/20 px-10 py-5 text-center text-lg font-bold text-white transition hover:bg-white/5">
                            How it works
                        </a>
                    </div>

                    <div class="mt-10 rounded-[2rem] border border-white/10 bg-zinc-900 p-5 shadow-2xl md:hidden">
                        <div class="mb-5 flex items-center justify-between">
                            <div class="space-y-2">
                                <div class="h-2 w-16 rounded-full bg-zinc-700"></div>
                                <div class="h-2 w-24 rounded-full bg-zinc-800"></div>
                            </div>
                            <div class="rounded-full bg-blue-600/15 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.28em] text-blue-300">
                                Live
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div class="rounded-2xl border border-white/5 bg-zinc-800/60 p-4 text-left">
                                <div class="flex items-start gap-3">
                                    <div class="mt-1 h-10 w-10 flex-shrink-0 rounded-full bg-blue-500"></div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-3">
                                            <p class="truncate text-sm font-bold text-white">Angela R.</p>
                                            <span class="rounded-full bg-blue-600/15 px-2 py-1 text-[10px] font-bold uppercase tracking-[0.22em] text-blue-200">
                                                Verified
                                            </span>
                                        </div>
                                        <p class="mt-1 text-xs text-zinc-400">Companionship, errands, mid-day check-ins</p>
                                        <div class="mt-3 flex items-center justify-between text-xs">
                                            <span class="text-zinc-500">Available today</span>
                                            <span class="font-semibold text-white">$30/hr</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-2xl border border-white/5 bg-zinc-950/80 p-4 text-left">
                                <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-zinc-500">Family update</p>
                                <p class="mt-3 text-sm font-semibold text-white">Request posted for mom in Raleigh.</p>
                                <p class="mt-2 text-xs leading-5 text-zinc-400">Meal prep, quick home reset, and a friendly check-in while you are at work.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-12 hidden md:block md:w-2/5 md:pl-12">
                    <div class="rounded-[2rem] border border-white/10 bg-zinc-900 p-6 shadow-2xl">
                        <div class="mb-6 flex items-center justify-between">
                            <div class="space-y-2">
                                <div class="h-2 w-16 rounded-full bg-zinc-700"></div>
                                <div class="h-2 w-24 rounded-full bg-zinc-800"></div>
                            </div>
                            <div class="rounded-full bg-blue-600/15 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.28em] text-blue-300">
                                Live
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div class="rounded-2xl border border-white/5 bg-zinc-800/60 p-5 text-left">
                                <div class="flex items-start gap-4">
                                    <div class="mt-1 h-12 w-12 flex-shrink-0 rounded-full bg-blue-500"></div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-3">
                                            <p class="truncate text-base font-bold text-white">Angela R.</p>
                                            <span class="rounded-full bg-blue-600/15 px-2 py-1 text-[10px] font-bold uppercase tracking-[0.22em] text-blue-200">
                                                Verified
                                            </span>
                                        </div>
                                        <p class="mt-1 text-sm text-zinc-400">Companionship, errands, mid-day check-ins</p>
                                        <div class="mt-4 flex items-center justify-between text-sm">
                                            <span class="text-zinc-500">Available today</span>
                                            <span class="font-semibold text-white">$30/hr</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-2xl border border-white/5 bg-zinc-950/80 p-5 text-left">
                                <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-zinc-500">Family update</p>
                                <p class="mt-3 text-base font-semibold text-white">Request posted for mom in Raleigh.</p>
                                <p class="mt-2 text-sm leading-6 text-zinc-400">Meal prep, quick home reset, and a friendly check-in while you are at work.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="border-y border-white/5 bg-zinc-900 px-6 py-24">
                <div class="mx-auto max-w-4xl">
                    <h2 class="text-4xl font-extrabold tracking-tight sm:text-5xl">You already know the problem.</h2>
                    <div class="mt-12 grid grid-cols-1 gap-8 md:grid-cols-2">
                        <div class="border-l-2 border-blue-500 pl-6">
                            <p class="text-xl text-gray-300">You're getting the mid-day calls.</p>
                        </div>
                        <div class="border-l-2 border-blue-500 pl-6">
                            <p class="text-xl text-gray-300">Agencies are expensive and slow.</p>
                        </div>
                        <div class="border-l-2 border-blue-500 pl-6">
                            <p class="text-xl text-gray-300">Craigslist is a total gamble.</p>
                        </div>
                        <div class="border-l-2 border-blue-500 pl-6">
                            <p class="text-xl text-gray-300">You can't manage it all from your desk.</p>
                        </div>
                    </div>
                    <p class="mt-16 text-2xl font-bold italic text-blue-400">There’s no good middle option. Until now.</p>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-6 py-24">
                <div class="grid grid-cols-1 items-center gap-16 lg:grid-cols-2">
                    <div>
                        <span class="text-sm font-bold uppercase tracking-widest text-blue-500">Real Support</span>
                        <h2 class="mt-4 text-4xl font-extrabold tracking-tight sm:text-5xl">Uber for the gaps no one else handles.</h2>
                        <div class="mt-10 space-y-6">
                            <div class="flex gap-4">
                                <div class="text-2xl font-bold text-blue-500">01</div>
                                <div>
                                    <h3 class="text-xl font-bold">Someone to check in</h3>
                                    <p class="text-gray-400">Eyes on the ground when you're 50 miles away.</p>
                                </div>
                            </div>
                            <div class="flex gap-4">
                                <div class="text-2xl font-bold text-blue-500">02</div>
                                <div>
                                    <h3 class="text-xl font-bold">Run errands</h3>
                                    <p class="text-gray-400">Groceries, pharmacy, or the post office. Handled.</p>
                                </div>
                            </div>
                            <div class="flex gap-4">
                                <div class="text-2xl font-bold text-blue-500">03</div>
                                <div>
                                    <h3 class="text-xl font-bold">Help around the house</h3>
                                    <p class="text-gray-400">Light chores that keep a home running.</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-12 rounded-2xl border border-white/10 bg-zinc-900 p-6">
                            <p class="text-lg font-semibold">Book in minutes. No interviews. No commitment.</p>
                        </div>
                    </div>

                    <div class="relative aspect-square overflow-hidden rounded-[3rem] bg-blue-600">
                        <div class="absolute inset-0 bg-gradient-to-br from-blue-400 to-blue-800 opacity-50"></div>
                        <div class="relative z-10 flex h-full flex-col items-center justify-center p-8 text-center text-white">
                            <p class="text-6xl font-black sm:text-8xl">FAST</p>
                            <p class="mt-4 text-lg font-medium tracking-wide sm:text-xl">AVERAGE MATCH TIME: 14 MINS</p>
                        </div>
                    </div>
                </div>
            </section>

            <section id="how" class="bg-white px-6 py-24 text-black">
                <div class="mx-auto max-w-7xl">
                    <h2 class="text-center text-4xl font-extrabold tracking-tight sm:text-5xl">Three steps to peace of mind.</h2>
                    <div class="mt-16 grid grid-cols-1 gap-12 md:grid-cols-3">
                        <div class="text-center">
                            <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-black text-2xl font-bold text-white">1</div>
                            <h3 class="text-2xl font-bold">Post the need</h3>
                            <p class="mt-4 text-gray-600">Tell us what needs doing. Right now or next Tuesday.</p>
                        </div>
                        <div class="text-center">
                            <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-black text-2xl font-bold text-white">2</div>
                            <h3 class="text-2xl font-bold">Pick a Pro</h3>
                            <p class="mt-4 text-gray-600">Review verified profiles. Pick someone you trust.</p>
                        </div>
                        <div class="text-center">
                            <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-black text-2xl font-bold text-white">3</div>
                            <h3 class="text-2xl font-bold">Stay updated</h3>
                            <p class="mt-4 text-gray-600">Get a notification when they arrive and a report when they leave.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-5xl px-6 py-24">
                <h2 class="text-center text-4xl font-extrabold tracking-tight">Why this isn't an agency.</h2>
                <div class="mt-12 overflow-hidden rounded-3xl border border-white/10 bg-white/10 md:grid md:grid-cols-2 md:gap-px">
                    <div class="bg-zinc-900 p-12">
                        <h3 class="mb-6 font-bold text-red-500">TRADITIONAL AGENCIES</h3>
                        <ul class="space-y-4 text-gray-400">
                            <li>$40+/hr minimums</li>
                            <li>Weeks of coordination</li>
                            <li>Locked-in schedules</li>
                        </ul>
                    </div>
                    <div class="bg-blue-600 p-12">
                        <h3 class="mb-6 font-bold uppercase italic underline text-white">HOMECARE HUB</h3>
                        <ul class="space-y-4 text-white">
                            <li>Pay as you go</li>
                            <li>On-demand matching</li>
                            <li>You decide the scope</li>
                        </ul>
                    </div>
                </div>
            </section>

            <section class="border-t border-white/5 bg-zinc-900 px-6 py-24">
                <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-12 md:flex-row">
                    <div class="text-center md:text-left">
                        <h2 class="text-3xl font-bold">Trust built in.</h2>
                        <p class="mt-4 text-gray-400">Security isn't a premium feature. It's the baseline.</p>
                    </div>
                    <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 md:gap-16">
                        <div class="text-center md:text-left">
                            <p class="text-sm font-bold uppercase text-blue-500">Background Checks</p>
                            <p class="text-xl font-bold">100% Verified</p>
                        </div>
                        <div class="text-center md:text-left">
                            <p class="text-sm font-bold uppercase text-blue-500">Reviews</p>
                            <p class="text-xl font-bold">Verified Only</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="px-6 py-32 text-center">
                <div class="mx-auto max-w-3xl">
                    <h2 class="text-4xl font-black leading-tight sm:text-6xl">
                        You shouldn't have to manage your parent's life from your phone.
                    </h2>
                    <p class="mt-8 text-3xl font-extrabold italic text-blue-500">
                        But you are. So we made this.
                    </p>
                    <div class="mx-auto mt-12 h-1 w-24 bg-white"></div>
                </div>
            </section>

            <section id="signup" class="bg-blue-600 px-6 py-32">
                <div class="mx-auto max-w-4xl text-center">
                    <h2 class="text-5xl font-black sm:text-6xl">Get help today.</h2>
                    <p class="mt-8 text-xl text-blue-100">Create an account and see who's available in your area right now.</p>
                    <form action="{{ route('register') }}" method="GET" class="mx-auto mt-12 max-w-2xl">
                        <div class="flex flex-col gap-4 sm:flex-row">
                            <label for="family-variant-e-email" class="sr-only">Email address</label>
                            <input
                                id="family-variant-e-email"
                                name="email"
                                type="email"
                                inputmode="email"
                                autocomplete="email"
                                placeholder="you@company.com"
                                class="min-h-14 flex-1 rounded-xl border border-white/20 bg-white px-5 text-base font-medium text-black placeholder:text-zinc-500 focus:border-black focus:outline-none focus:ring-2 focus:ring-black/20"
                            >
                            <button type="submit" class="inline-flex min-h-14 items-center justify-center rounded-xl bg-black px-10 py-5 font-black text-white transition hover:bg-zinc-800">
                                Get started
                            </button>
                        </div>
                    </form>
                    <a href="{{ route('login') }}" class="mt-5 inline-flex min-h-11 items-center justify-center rounded-full border border-white/30 px-5 py-3 text-sm font-bold text-white transition hover:bg-white/10">
                        Already have an account? Sign In
                    </a>
                    <p class="mt-8 text-sm text-blue-200">No credit card required to browse profiles.</p>
                </div>
            </section>
        </main>
    </div>
@endsection
