<x-app-layout>
    <div class="hc-page max-w-2xl space-y-6 py-8">
        <a href="{{ route('family.care-hours.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold underline">← Prepaid care hours</a>
        <header><h1 class="font-display text-3xl font-semibold">{{ $draft ? 'Review your gift' : 'Claim a gift of care' }}</h1>
            <p class="mt-3 text-[#485E53]">Add hours to your family balance. Use them for anyone you arrange care for.</p>
        </header>
        @include('care-hours.partials.messages')
        @if($draft && $selectedAccount)
            <section class="hc-surface space-y-5 p-6" aria-label="Review gift claim">
                <h2 class="font-display text-2xl font-semibold">Your family balance</h2>
                <dl class="space-y-3">
                    <div class="flex justify-between gap-4"><dt>Gift hours</dt><dd>{{ \App\Models\CareHourAccount::formatMinutes($draft['quote']['paid_minutes']) }}</dd></div>
                    @if($draft['quote']['bonus_minutes'] > 0)<div class="flex justify-between gap-4"><dt>Free welcome hours</dt><dd>{{ \App\Models\CareHourAccount::formatMinutes($draft['quote']['bonus_minutes']) }}</dd></div>@endif
                    <div class="flex justify-between gap-4 border-t border-[#D8E1D7] pt-3 font-semibold"><dt>Hours to add</dt><dd>{{ \App\Models\CareHourAccount::formatMinutes($draft['quote']['total_minutes']) }}</dd></div>
                </dl>
                <form action="{{ route('family.care-hours.claim.confirm') }}" method="POST" class="space-y-4">
                    @csrf<input type="hidden" name="nonce" value="{{ $draft['nonce'] }}">
                    <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="confirm" value="1" required class="mt-1 rounded border-[#D8E1D7]"><span>Add these hours to my family account. Once claimed, the gift cannot be moved to another family account.</span></label>
                    <button type="submit" class="hc-primary-button">{{ ($draft['guided'] ?? false) ? 'Claim my gift' : 'Add gift hours' }}</button>
                </form>
                <form action="{{ route('family.care-hours.claim.reset') }}" method="POST">@csrf<button type="submit" class="text-sm font-semibold underline">Back</button></form>
            </section>
        @else
            <form action="{{ route('family.care-hours.claim.preview') }}" method="POST" class="hc-surface space-y-5 p-6">
                @csrf
                @if($giftIntent)
                    <p class="font-display text-2xl font-semibold">{{ \App\Models\CareHourAccount::formatMinutes($giftIntent['paid_minutes']) }} of care</p>
                    <p>Your gift is saved. No card or care profile needed.</p>
                @else
                    <div><label for="gift-claim-code" class="block font-semibold">Gift code</label><input id="gift-claim-code" name="code" required maxlength="80" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="Enter your gift code" class="mt-2 min-h-12 w-full rounded-xl border-[#D8E1D7] font-mono"></div>
                @endif
                <button type="submit" class="hc-primary-button">Review gift</button>
            </form>
            @if($giftIntent)<form action="{{ route('care-hours.claim.reset') }}" method="POST">@csrf<button type="submit" class="text-sm font-semibold underline">Use a different code</button></form>@endif
        @endif
        <p class="text-sm text-[#485E53]">Claiming is free. The code can be used once, and the hours never expire.</p>
    </div>
</x-app-layout>
