@extends('layouts.marketing')

@section('title', 'YOU NEED HELP. Like… Now. | HomeCare Raleigh')
@section('meta_description', 'Fast, direct home support for Raleigh families caring for an aging parent. Post the request, talk to caregivers, and move quickly.')
@section('canonical', route('landing.family.variant', ['variant' => 'c']))

@section('content')
    <div x-data="{ showSticky: false }" @scroll.window="showSticky = window.scrollY > 300" class="min-h-screen overflow-x-hidden bg-black pb-24 text-white sm:pb-0">
        <header class="fixed inset-x-0 top-0 z-40 px-6 py-5">
            <div class="mx-auto flex max-w-6xl items-center justify-between">
                <a href="{{ route('landing') }}" class="text-sm font-black uppercase tracking-[0.24em] text-white/75">HomeCare</a>
                <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center rounded-full border border-white/15 px-4 py-2 text-sm font-black uppercase tracking-[0.14em] text-white transition hover:bg-white hover:text-black">
                    Sign In
                </a>
            </div>
        </header>

        <main>
            <section class="relative flex min-h-screen items-center justify-center overflow-hidden bg-[linear-gradient(135deg,#2563eb_0%,#7c3aed_45%,#000000_100%)] px-6 pt-16 text-center sm:pt-20">
                <div class="absolute -bottom-20 -right-20 h-80 w-80 rounded-full bg-[#adff2f] opacity-20 blur-[120px]"></div>
                <div class="mx-auto max-w-md">
                    <div class="text-xl font-black uppercase italic tracking-tight">Home Care</div>
                    <h1 class="mt-10 text-7xl font-black uppercase leading-[0.82] tracking-[-0.07em] sm:text-8xl">
                        YOU <br>
                        NEED <br>
                        HELP.
                    </h1>
                    <p class="mt-8 text-2xl font-medium italic text-white/60">
                        (pause) <br>
                        Like… now.
                    </p>
                    <div class="mt-10 space-y-4">
                        <h2 class="text-4xl font-bold">And that's okay.</h2>
                        <a href="{{ route('register') }}" class="inline-flex min-h-14 w-full items-center justify-center rounded-full bg-white px-8 py-4 text-lg font-black text-black shadow-xl transition hover:bg-white/90">
                            Get Help Now
                        </a>
                        <p class="text-center text-sm font-bold uppercase tracking-[0.24em] text-white/50">No calls. No process.</p>
                    </div>
                </div>
            </section>

            <section class="bg-[#ff7f50] px-6 py-32 text-center text-black">
                <div class="mx-auto max-w-md">
                    <h2 class="text-5xl font-black uppercase leading-none tracking-[-0.06em]">
                        YOU'RE <br>
                        JUGGLING <br>
                        TOO MUCH.
                    </h2>
                    <div class="mt-12 space-y-6">
                        <div class="text-2xl font-black italic">WORK</div>
                        <div class="text-2xl font-black italic">FAMILY</div>
                        <div class="text-2xl font-black italic">LIFE</div>
                        <div class="text-2xl font-black italic">AND NOW… THEM</div>
                    </div>
                    <p class="mt-16 text-3xl font-black leading-tight tracking-tight">
                        This wasn't supposed to be on you alone.
                    </p>
                </div>
            </section>

            <section class="bg-black px-6 py-32 text-center">
                <div class="mx-auto max-w-md">
                    <h2 class="text-4xl font-black italic uppercase leading-tight">HERE'S HOW PEOPLE USUALLY HANDLE THIS</h2>
                    <div class="mt-12 space-y-4">
                        <div class="rounded-[2rem] bg-zinc-800 p-8 text-center shadow-2xl">
                            <h3 class="text-2xl font-black uppercase">Ask around</h3>
                            <p class="mt-3 text-lg text-white/80">→ nothing reliable. Just cousins of neighbors.</p>
                        </div>
                        <div class="rounded-[2rem] bg-zinc-800 p-8 text-center shadow-2xl">
                            <h3 class="text-2xl font-black uppercase">Call agencies</h3>
                            <p class="mt-3 text-lg text-white/80">→ wait days. Fill out 40 forms. Wait more.</p>
                        </div>
                        <div class="rounded-[2rem] bg-zinc-800 p-8 text-center shadow-2xl">
                            <h3 class="text-2xl font-black uppercase">Do it yourself</h3>
                            <p class="mt-3 text-lg text-white/80">→ burnout. Real, crushing burnout.</p>
                        </div>
                    </div>
                    <p class="mt-10 text-3xl font-black uppercase text-[#ff7f50]">None of this works.</p>
                </div>
            </section>

            <section class="bg-white px-6 py-32 text-center text-black">
                <div class="mx-auto max-w-md">
                    <h2 class="text-5xl font-black uppercase leading-[0.9] tracking-[-0.06em]">
                        YOU NEED HELP. <br>
                        WE MAKE IT <br>
                        SIMPLE.
                    </h2>
                    <div class="mt-10 space-y-4 text-2xl font-bold text-black/65">
                        <p>Post what you need.</p>
                        <p>See who's available.</p>
                        <p>Book it.</p>
                    </div>
                    <a href="#how" class="mt-12 inline-flex min-h-14 items-center justify-center rounded-full bg-black px-10 py-4 text-lg font-black text-white transition hover:bg-zinc-800">
                        See How It Works
                    </a>
                </div>
            </section>

            <section class="overflow-hidden bg-zinc-900 px-6 py-24 text-center" id="how">
                <div class="mx-auto max-w-md">
                    <h2 class="text-xl font-black uppercase tracking-[0.24em]">This takes less than a minute</h2>
                    <div class="relative mx-auto mt-12 aspect-[9/16] max-w-[280px] rounded-[3rem] border-[8px] border-zinc-800 bg-black shadow-2xl">
                        <div class="flex h-full flex-col items-center justify-center gap-6 p-8">
                            <div class="w-full rounded-2xl bg-zinc-800 p-4">
                                <div class="mx-auto mb-2 h-4 w-2/3 rounded bg-zinc-700"></div>
                                <div class="mx-auto h-4 w-1/2 rounded bg-zinc-700"></div>
                            </div>
                            <div class="w-full rounded-xl bg-zinc-800 py-4"></div>
                            <div class="w-full rounded-xl bg-zinc-800 py-4"></div>
                            <div class="mt-6 text-5xl font-black text-[#adff2f]">DONE.</div>
                        </div>
                    </div>
                    <a href="{{ route('register') }}" class="mt-12 inline-flex min-h-14 w-full items-center justify-center rounded-full bg-[#adff2f] px-8 py-4 text-lg font-black text-black shadow-2xl transition hover:shadow-[0_0_20px_rgba(173,255,47,0.5)]">
                        Start Now
                    </a>
                </div>
            </section>

            <section class="bg-zinc-100 px-6 py-32 text-center text-black">
                <div class="mx-auto max-w-md space-y-16">
                    <div>
                        <div class="text-6xl font-black text-black/10">01</div>
                        <h3 class="mt-2 text-3xl font-black uppercase tracking-tight">Say what you need</h3>
                        <p class="mt-3 text-xl text-black/60">2–3 taps. That's all it takes to describe the care required.</p>
                    </div>
                    <div>
                        <div class="text-6xl font-black text-black/10">02</div>
                        <h3 class="mt-2 text-3xl font-black uppercase tracking-tight">See real availability</h3>
                        <p class="mt-3 text-xl text-black/60">No waiting. See caregivers ready to work right now.</p>
                    </div>
                    <div>
                        <div class="text-6xl font-black text-black/10">03</div>
                        <h3 class="mt-2 text-3xl font-black uppercase tracking-tight">Choose and move on</h3>
                        <p class="mt-3 text-xl text-black/60">You're covered. Go back to being a son, daughter, or spouse.</p>
                    </div>
                </div>
            </section>

            <section class="bg-blue-600 px-6 py-32 text-center text-white">
                <div class="mx-auto max-w-md">
                    <h2 class="text-5xl font-black uppercase leading-none tracking-[-0.06em]">
                        NO <br>
                        COMPLICATED <br>
                        PRICING.
                    </h2>
                    <p class="mt-8 text-2xl font-bold">One simple rate. <br>Use it when you need it.</p>
                    <div class="mt-10 space-y-4">
                        <div class="rounded-2xl border border-white/20 bg-white/10 p-6 text-2xl font-black uppercase italic">No contracts</div>
                        <div class="rounded-2xl border border-white/20 bg-white/10 p-6 text-2xl font-black uppercase italic">No commitments</div>
                        <div class="rounded-2xl border border-white/20 bg-white/10 p-6 text-2xl font-black uppercase italic">No surprises</div>
                    </div>
                </div>
            </section>

            <section class="bg-white px-6 py-20 text-center text-black">
                <div class="mx-auto flex max-w-md flex-wrap justify-center gap-3">
                    <div class="rounded-full bg-zinc-100 px-6 py-3 text-sm font-black uppercase">Verified Caregivers</div>
                    <div class="rounded-full bg-zinc-100 px-6 py-3 text-sm font-black uppercase">4.9/5 Avg Rating</div>
                    <div class="rounded-full bg-zinc-100 px-6 py-3 text-sm font-black uppercase">Secure Payments</div>
                    <div class="rounded-full bg-zinc-100 px-6 py-3 text-sm font-black uppercase">Full Visibility</div>
                </div>
            </section>

            <section class="bg-zinc-50 px-6 py-40 text-center text-black">
                <div class="mx-auto max-w-md">
                    <h2 class="text-4xl font-black uppercase tracking-tight sm:text-5xl">
                        YOU DON'T HAVE TO CARRY THIS ALONE.
                    </h2>
                    <p class="mt-6 text-xl font-medium text-black/60">
                        Just get the right help in place. <br>
                        So you can go back to being you.
                    </p>
                </div>
            </section>

            <section class="bg-[#adff2f] px-6 py-32 text-center text-black">
                <div class="mx-auto flex max-w-md flex-col items-center">
                    <h2 class="text-7xl font-black uppercase leading-none tracking-[-0.06em]">START <br>NOW.</h2>
                    <a href="{{ route('register') }}" class="mt-12 inline-flex min-h-24 w-full items-center justify-center rounded-full bg-black px-8 py-6 text-3xl font-black text-white transition hover:bg-zinc-800">
                        Find Help
                    </a>
                    <div class="mt-20 text-2xl font-black uppercase italic tracking-tight opacity-30">Home Care — The Modern Way</div>
                </div>
            </section>
        </main>

        <div x-cloak x-show="showSticky" x-transition.opacity.duration.200ms class="fixed inset-x-0 bottom-0 z-50 px-4 py-3 supports-[padding:max(0px)]:pb-[max(0.75rem,env(safe-area-inset-bottom))]">
            <div class="mx-auto flex max-w-sm items-center justify-center">
                <a href="{{ route('register') }}" class="inline-flex min-h-14 w-full items-center justify-center rounded-full bg-[#adff2f] px-8 py-4 text-lg font-black text-black shadow-2xl transition hover:shadow-[0_0_20px_rgba(173,255,47,0.5)]">
                    Get Help Now
                </a>
            </div>
        </div>
    </div>
@endsection
