@extends('layouts.marketing')

@section('title', 'Take Care of Her | HomeCare Raleigh')
@section('meta_description', 'For the family trying to get trusted help for mom or dad in Raleigh without overcomplicating it. Start the request and move forward with confidence.')
@section('canonical', route('landing.family.variant', ['variant' => 'd']))

@section('content')
    <div x-data="{ showSticky: false }" @scroll.window="showSticky = window.scrollY > 500" class="min-h-screen overflow-x-hidden bg-black pb-24 text-white sm:pb-0">
        <header class="fixed inset-x-0 top-0 z-40 px-8 py-5">
            <div class="mx-auto flex max-w-6xl items-center justify-between">
                <a href="{{ route('landing') }}" class="text-sm font-bold uppercase tracking-[0.24em] text-white/70">HomeCare</a>
                <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center rounded-full border border-white/15 px-4 py-2 text-sm font-bold text-white transition hover:bg-white hover:text-black">
                    Sign in
                </a>
            </div>
        </header>

        <main>
            <section class="flex min-h-screen w-full flex-col justify-center px-8 pb-12 pt-28 sm:pt-24">
                <div class="mx-auto max-w-5xl">
                    <h1 class="text-5xl font-extrabold leading-tight sm:text-7xl">
                        Yo momma so strong...
                    </h1>
                    <p class="mt-4 text-3xl font-semibold text-gray-400 sm:text-5xl">
                        She made you think she didn’t need help.
                    </p>
                    <div class="mt-12">
                        <p class="mb-6 text-sm uppercase tracking-[0.24em] text-gray-500">But you know that’s not true.</p>
                        <a href="#shift" class="inline-flex min-h-14 items-center justify-center rounded-full bg-white px-10 py-4 text-lg font-bold text-black transition hover:bg-gray-200">
                            Find Help Now
                        </a>
                    </div>
                </div>
            </section>

            <section class="flex min-h-screen w-full flex-col justify-center bg-black px-8 py-20">
                <div class="mx-auto max-w-5xl">
                    <h2 class="text-5xl font-extrabold leading-tight sm:text-7xl">
                        Yo momma so tough...
                    </h2>
                    <p class="mt-4 text-3xl font-semibold text-gray-400 sm:text-5xl">
                        She’ll say she’s fine… even when she’s not.
                    </p>
                    <div class="mt-12">
                        <p class="text-lg italic text-gray-500">And that’s exactly why this is hard.</p>
                    </div>
                </div>
            </section>

            <section id="shift" class="min-h-screen bg-neutral-900 px-8 py-20">
                <div class="mx-auto flex min-h-[calc(100vh-10rem)] max-w-3xl flex-col justify-center">
                    <h2 class="text-4xl font-bold sm:text-6xl">This is where you step in.</h2>
                    <div class="mt-8 space-y-6 text-xl text-gray-300 sm:text-2xl">
                        <p>Not with a long process.</p>
                        <p class="font-semibold text-white">Not with a big commitment.</p>
                        <p>Just… the right help.</p>
                    </div>
                    <div class="mt-12">
                        <a href="#how-it-works" class="inline-flex min-h-14 items-center justify-center rounded-full border-2 border-white px-10 py-4 text-lg font-bold text-white transition hover:bg-white hover:text-black">
                            See How It Works
                        </a>
                    </div>
                </div>
            </section>

            <section class="bg-white px-8 py-24 text-black">
                <div class="mx-auto max-w-md text-center">
                    <h2 class="mb-4 text-3xl font-bold">Watch how simple this is</h2>
                    <div class="relative aspect-[9/16] overflow-hidden rounded-2xl border border-[#333] bg-[#1a1a1a]">
                        <div class="flex h-full flex-col items-center justify-center space-y-4 p-6">
                            <div class="mb-4 h-12 w-48 animate-pulse rounded bg-gray-200"></div>
                            <p class="text-xs uppercase tracking-tight text-gray-400">Post Request • Match • Book</p>
                            <div class="mt-4 text-5xl font-bold">Handled.</div>
                        </div>
                        <div class="absolute inset-0 flex items-center justify-center bg-black/20">
                            <div class="flex h-20 w-20 items-center justify-center rounded-full bg-white/10 text-white">▶</div>
                        </div>
                    </div>
                    <a href="{{ route('register') }}" class="mt-8 inline-flex min-h-14 w-full items-center justify-center rounded-xl bg-black py-4 text-xl font-bold text-white transition hover:bg-zinc-800">
                        Start Now
                    </a>
                </div>
            </section>

            <section id="how-it-works" class="bg-neutral-50 px-8 py-24 text-black">
                <div class="mx-auto max-w-4xl">
                    <h2 class="mb-12 text-center text-3xl font-bold">No process. Just action.</h2>
                    <div class="grid gap-6 md:grid-cols-3">
                        <div class="rounded-2xl border border-gray-100 bg-white p-8 shadow-sm">
                            <div class="mb-6 flex h-12 w-12 items-center justify-center rounded-full bg-black font-bold text-white">1</div>
                            <h3 class="text-xl font-bold">Post what you need</h3>
                            <p class="mt-2 text-gray-500">2–3 taps on your phone.</p>
                        </div>
                        <div class="rounded-2xl border border-gray-100 bg-white p-8 shadow-sm">
                            <div class="mb-6 flex h-12 w-12 items-center justify-center rounded-full bg-black font-bold text-white">2</div>
                            <h3 class="text-xl font-bold">See who’s available</h3>
                            <p class="mt-2 text-gray-500">Qualified caregivers, right now.</p>
                        </div>
                        <div class="rounded-2xl border border-gray-100 bg-white p-8 shadow-sm">
                            <div class="mb-6 flex h-12 w-12 items-center justify-center rounded-full bg-black font-bold text-white">3</div>
                            <h3 class="text-xl font-bold">Book and move on</h3>
                            <p class="mt-2 text-gray-500">You’re covered. She’s safe.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="border-t border-gray-100 bg-white px-8 py-24 text-black">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-4xl font-extrabold">One simple rate.</h2>
                    <div class="mt-6 space-y-2 text-xl text-gray-500">
                        <p>No confusion</p>
                        <p>No contracts</p>
                        <p>No commitment</p>
                    </div>
                    <div class="mt-12 rounded-3xl bg-neutral-100 p-8">
                        <p class="text-2xl font-bold">Use it when you need it. That’s it.</p>
                    </div>
                </div>
            </section>

            <section class="flex min-h-screen flex-col items-center justify-center bg-black px-8 py-24 text-center">
                <div class="mx-auto max-w-3xl">
                    <h2 class="text-4xl font-bold sm:text-5xl">You’re not supposed to do this alone.</h2>
                    <div class="mt-12 space-y-8 text-xl text-gray-400">
                        <p>Not while working</p>
                        <p>Not while raising a family</p>
                        <p>Not while managing everything else</p>
                    </div>
                    <div class="mt-16">
                        <p class="text-4xl font-extrabold text-white">Get help.</p>
                    </div>
                </div>
            </section>

            <section class="bg-white px-8 py-32 text-center text-black">
                <div class="mx-auto max-w-4xl">
                    <h2 class="text-5xl font-extrabold sm:text-7xl">Take care of her</h2>
                    <a href="{{ route('register') }}" class="mt-12 inline-flex min-h-16 items-center justify-center rounded-full bg-black px-12 py-5 text-2xl font-bold text-white transition hover:scale-105 hover:bg-zinc-800">
                        Find Help Now
                    </a>
                </div>
            </section>
        </main>

        <div x-cloak x-show="showSticky" x-transition.opacity.duration.200ms class="fixed inset-x-0 bottom-0 z-50 border-t border-gray-200 bg-white px-4 py-3 text-black shadow-[0_-4px_10px_rgba(0,0,0,0.3)] supports-[padding:max(0px)]:pb-[max(0.75rem,env(safe-area-inset-bottom))]">
            <div class="mx-auto flex max-w-md items-center justify-between gap-3">
                <div class="text-sm font-semibold">On-Demand Care</div>
                <a href="{{ route('register') }}" class="rounded-full bg-black px-6 py-2 text-sm font-bold text-white">
                    Start Now
                </a>
            </div>
        </div>
    </div>
@endsection
