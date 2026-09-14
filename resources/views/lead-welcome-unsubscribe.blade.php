<x-guest-layout>
    <div class="space-y-5">
        <h1 class="text-2xl font-semibold">{{ $done ? 'You’re unsubscribed' : 'Email preferences' }}</h1>
        <p class="text-sm leading-6 text-slate-600">{{ $done ? 'You will no longer receive these signup invitation emails from LoLo Care.' : 'You can unsubscribe from signup invitation emails below.' }}</p>
        @unless($done)
            <form method="POST" action="{{ request()->fullUrl() }}">@csrf
                <button class="rounded-xl bg-[#23483F] px-5 py-3 font-bold text-white">Unsubscribe from these emails</button>
            </form>
        @endunless
    </div>
</x-guest-layout>
