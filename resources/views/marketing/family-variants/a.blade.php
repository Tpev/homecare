@extends('layouts.marketing')

@section('title', 'You Can’t Do This Alone. And You Shouldn’t Have To. | HomeCare Raleigh')
@section('meta_description', 'Fast, trusted home support for your mom or dad in Raleigh. Skip the agency delay, post what you need, and get help in place sooner.')
@section('canonical', route('landing.family.variant', ['variant' => 'a']))

@section('content')
    <div x-data="{ showSticky: false }" @scroll.window="showSticky = window.scrollY > 500" class="min-h-screen bg-black pb-24 text-white sm:pb-0">
        <header class="sticky top-0 z-40 border-b border-white/10 bg-black/90 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
                <a href="{{ route('landing') }}" class="flex items-center gap-3">
                    <x-application-logo class="h-10 w-10 text-white" />
                    <div class="leading-tight">
                        <div class="text-lg font-black tracking-tight text-white">HomeCare</div>
                        <div class="text-xs uppercase tracking-[0.2em] text-white/50">Family support</div>
                    </div>
                </a>

                <div class="flex items-center gap-2">
                    <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center rounded-full border border-white/15 px-4 py-2 text-sm font-bold text-white/85 transition hover:bg-white/5">Sign in</a>
                    <a href="{{ route('register') }}" class="inline-flex min-h-11 items-center rounded-full bg-white px-4 py-2 text-sm font-black text-black transition hover:bg-white/90">Get Help Now</a>
                </div>
            </div>
        </header>

        <main>
            <section class="flex min-h-screen items-center px-6 py-20">
                <div class="mx-auto max-w-5xl">
                    <h1 class="text-5xl font-black leading-[0.9] tracking-[-0.05em] sm:text-7xl lg:text-8xl">
                        You can’t do this alone.
                    </h1>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-white/45 sm:text-5xl lg:text-6xl">
                        And you shouldn’t have to.
                    </h2>

                    <p class="mt-8 text-xl font-medium leading-8 text-white/55 sm:text-2xl">
                        Work. Kids. Life.<br>
                        And now… care for your parents.
                    </p>

                    <div class="mt-10">
                        <a href="{{ route('register') }}" class="inline-flex min-h-14 w-full items-center justify-center rounded-full bg-white px-8 py-4 text-lg font-black text-black transition hover:bg-white/90 sm:w-auto sm:px-10">
                            Get Help Now
                        </a>
                    </div>
                </div>
            </section>

            <section class="flex min-h-[85vh] items-center bg-zinc-950 px-6 py-20">
                <div class="mx-auto max-w-4xl text-center">
                    <h2 class="text-5xl font-black italic tracking-tight sm:text-7xl">“Be honest.”</h2>
                    <div class="h-16 sm:h-24"></div>
                    <p class="text-4xl font-bold tracking-tight text-white/75 sm:text-6xl">You’ve been putting this off.</p>
                </div>
            </section>

            <section class="flex min-h-[85vh] items-center bg-zinc-950 px-6 py-20">
                <div class="mx-auto max-w-4xl space-y-6 text-center">
                    <p class="text-3xl font-bold text-white/35 sm:text-5xl">Because it’s complicated.</p>
                    <p class="text-3xl font-bold text-white/35 sm:text-5xl">Expensive.</p>
                    <p class="text-3xl font-bold text-white/35 sm:text-5xl">Time-consuming.</p>
                </div>
            </section>

            <section class="flex min-h-[75vh] items-center bg-white px-6 py-20 text-black">
                <div class="mx-auto max-w-5xl text-center">
                    <h2 class="text-5xl font-black tracking-[-0.04em] sm:text-7xl lg:text-8xl">It doesn’t have to be.</h2>
                </div>
            </section>

            <section class="bg-gray-50 px-6 py-20 text-black">
                <div class="mx-auto max-w-5xl text-center">
                    <h2 class="text-5xl font-black tracking-[-0.04em] sm:text-7xl">This is different.</h2>
                    <p class="mt-5 text-3xl font-bold text-emerald-600 sm:text-5xl">Home care. $30/hour.</p>
                    <p class="mt-2 text-xl font-medium text-black/55 sm:text-2xl">No agency. No process.</p>

                    <a href="#how-it-works" class="mt-12 inline-flex min-h-14 w-full items-center justify-center rounded-full border-2 border-black px-8 py-4 text-lg font-black transition hover:bg-black hover:text-white sm:w-auto sm:px-10">
                        See how it works
                    </a>
                </div>
            </section>

            <section id="how-it-works" class="bg-white px-6 py-20 text-black">
                <div class="mx-auto max-w-6xl">
                    <div class="grid gap-5 md:grid-cols-2">
                        <div class="rounded-[2rem] border-2 border-gray-100 bg-white p-6 shadow-sm">
                            <h3 class="text-3xl font-black tracking-tight">Post what you need</h3>
                            <p class="mt-3 text-xl font-medium text-black/45">2–3 taps. That’s it.</p>
                        </div>
                        <div class="rounded-[2rem] border-2 border-gray-100 bg-white p-6 shadow-sm">
                            <h3 class="text-3xl font-black tracking-tight">See who’s available</h3>
                            <p class="mt-3 text-xl font-medium text-black/45">Real people. Real time.</p>
                        </div>
                        <div class="rounded-[2rem] border-2 border-gray-100 bg-white p-6 shadow-sm">
                            <h3 class="text-3xl font-black tracking-tight">Talk before you decide</h3>
                            <p class="mt-3 text-xl font-medium text-black/45">No guessing. No blind booking.</p>
                        </div>
                        <div class="rounded-[2rem] border-2 border-gray-100 bg-white p-6 shadow-sm">
                            <h3 class="text-3xl font-black tracking-tight">Book. Done.</h3>
                            <p class="mt-3 text-xl font-medium text-black/45">You’re covered.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-emerald-600 px-6 py-24 text-white">
                <div class="mx-auto max-w-5xl text-center">
                    <div class="text-7xl font-black leading-none tracking-[-0.07em] sm:text-[10rem]">$30/hr</div>
                    <h2 class="mt-4 text-4xl font-bold tracking-tight sm:text-6xl">That’s the whole model.</h2>
                    <div class="mt-10 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-white/20 bg-white/10 px-4 py-4 text-lg font-bold">No intake calls</div>
                        <div class="rounded-2xl border border-white/20 bg-white/10 px-4 py-4 text-lg font-bold">No contracts</div>
                        <div class="rounded-2xl border border-white/20 bg-white/10 px-4 py-4 text-lg font-bold">No minimums</div>
                    </div>
                </div>
            </section>

            <section class="bg-white px-6 py-20 text-black">
                <div class="mx-auto max-w-6xl">
                    <h2 class="text-center text-4xl font-black tracking-[-0.04em] sm:text-6xl">This isn’t how home care used to work.</h2>

                    <div class="mt-10 overflow-hidden rounded-[2rem] border-2 border-black md:grid md:grid-cols-2">
                        <div class="bg-gray-100 p-8">
                            <h3 class="text-2xl font-bold uppercase tracking-[0.24em] text-black/30">Traditional</h3>
                            <ul class="mt-8 space-y-5 text-xl font-medium text-black/30">
                                <li class="line-through">Wait days</li>
                                <li class="line-through">Phone calls</li>
                                <li class="line-through">Locked schedules</li>
                                <li class="line-through">High costs</li>
                            </ul>
                        </div>
                        <div class="bg-black p-8 text-white">
                            <h3 class="text-2xl font-bold uppercase tracking-[0.24em] text-emerald-400">HomeCare</h3>
                            <ul class="mt-8 space-y-5 text-xl font-bold">
                                <li>Start today</li>
                                <li>Do it from your phone</li>
                                <li>Use it when you want</li>
                                <li>$30/hr</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-black px-6 py-24 text-white">
                <div class="mx-auto max-w-4xl text-center">
                    <h2 class="text-4xl font-black tracking-tight sm:text-6xl">This is what this is really about:</h2>
                    <div class="mt-14 space-y-14">
                        <p class="text-3xl font-bold text-white/75 sm:text-5xl">Making sure they’re okay.</p>
                        <p class="text-3xl font-bold text-white/75 sm:text-5xl">Not feeling guilty.</p>
                        <p class="text-3xl font-bold text-white/75 sm:text-5xl">Having someone there.</p>
                        <p class="text-3xl font-bold text-white/75 sm:text-5xl">Getting your life back.</p>
                    </div>
                </div>
            </section>

            <section class="bg-zinc-900 px-6 py-24 text-white">
                <div class="mx-auto max-w-4xl text-center">
                    <h2 class="text-5xl font-black tracking-[-0.04em] sm:text-7xl">You can fix this today.</h2>
                    <p class="mt-4 text-2xl font-medium text-white/35">It takes less than a minute to start.</p>
                    <a href="{{ route('register') }}" class="mt-10 inline-flex min-h-16 w-full items-center justify-center rounded-full bg-emerald-500 px-8 py-5 text-2xl font-black text-white shadow-2xl transition hover:bg-emerald-400 sm:w-auto sm:px-12">
                        Post a Care Request
                    </a>
                </div>
            </section>

            <section class="border-t border-zinc-800 bg-zinc-900 px-6 py-16 text-white">
                <div class="mx-auto grid max-w-4xl grid-cols-2 gap-6 md:grid-cols-4">
                    <div class="text-center"><div class="text-sm font-bold uppercase tracking-tight text-white/40">Verified caregivers</div></div>
                    <div class="text-center"><div class="text-sm font-bold uppercase tracking-tight text-white/40">Ratings + reviews</div></div>
                    <div class="text-center"><div class="text-sm font-bold uppercase tracking-tight text-white/40">Secure payments</div></div>
                    <div class="text-center"><div class="text-sm font-bold uppercase tracking-tight text-white/40">You stay in control</div></div>
                </div>
            </section>

            <section class="flex min-h-[85vh] items-center bg-white px-6 py-20 text-black">
                <div class="mx-auto max-w-5xl text-center">
                    <h2 class="text-5xl font-black leading-[0.9] tracking-[-0.06em] sm:text-8xl lg:text-9xl">Stop thinking about it.</h2>
                    <p class="mt-6 text-3xl font-bold italic text-black/30 sm:text-5xl">Just start.</p>
                    <a href="{{ route('register') }}" class="mt-12 inline-flex min-h-16 w-full items-center justify-center rounded-full bg-black px-8 py-5 text-2xl font-black text-white transition hover:bg-zinc-800 sm:w-auto sm:px-12">
                        Get Help — $30/hr
                    </a>
                </div>
            </section>
        </main>

        <div x-cloak x-show="showSticky" x-transition.opacity.duration.200ms class="fixed inset-x-0 bottom-0 z-50 border-t border-gray-200 bg-white/95 px-4 py-3 text-black backdrop-blur supports-[padding:max(0px)]:pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:hidden">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-lg font-black leading-tight">Start Now</div>
                    <div class="text-sm font-bold text-emerald-600">$30/hr</div>
                </div>
                <a href="{{ route('register') }}" class="shrink-0 rounded-full bg-black px-5 py-3 text-sm font-black text-white">Post Request</a>
            </div>
        </div>
    </div>
@endsection
