<x-app-layout>
    <div class="hc-page max-w-2xl space-y-6 py-8">
        <a href="{{ route('family.care-hours.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold underline">← Care hours</a>
        <header><h1 class="font-display text-3xl font-semibold">{{ $giftIntent ? ($draft ? 'Review your gift' : 'Who is this gift for?') : 'Claim a gift of care' }}</h1>
            <p class="mt-3 text-[#485E53]">{{ $giftIntent ? 'Your gift is saved. No card or care request needed.' : 'Add your gift for yourself or someone you care for. The hours will be ready for future visits.' }}</p>
        </header>
        @include('care-hours.partials.messages')
        @if($draft && $selectedAccount)
            <section class="hc-surface space-y-5 p-6" aria-label="Review gift claim">
                <h2 class="font-display text-2xl font-semibold">{{ ($draft['for_self'] ?? false) ? 'For myself' : 'For '.$selectedAccount->display_name }}</h2>
                @if($draft['for_self'] ?? false)<p class="text-[#485E53]">{{ $selectedAccount->display_name }}</p>@endif
                <dl class="space-y-3">
                    <div class="flex justify-between gap-4"><dt>Gift hours</dt><dd>{{ \App\Models\CareHourAccount::formatMinutes($draft['quote']['paid_minutes']) }}</dd></div>
                    @if($draft['quote']['bonus_minutes'] > 0)<div class="flex justify-between gap-4"><dt>Free welcome hours</dt><dd>{{ \App\Models\CareHourAccount::formatMinutes($draft['quote']['bonus_minutes']) }}</dd></div>@endif
                    <div class="flex justify-between gap-4 border-t border-[#D8E1D7] pt-3 font-semibold"><dt>Hours to add</dt><dd>{{ \App\Models\CareHourAccount::formatMinutes($draft['quote']['total_minutes']) }}</dd></div>
                </dl>
                <form action="{{ route('family.care-hours.claim.confirm') }}" method="POST" class="space-y-4">
                    @csrf<input type="hidden" name="nonce" value="{{ $draft['nonce'] }}">
                    <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="confirm" value="1" required class="mt-1 rounded border-[#D8E1D7]"><span>These hours are for {{ $selectedAccount->display_name }}. Once added, they cannot be moved to another recipient.</span></label>
                    <button type="submit" class="hc-primary-button">{{ ($draft['guided'] ?? false) ? 'Claim my gift' : 'Add gift hours' }}</button>
                </form>
                <form action="{{ route('family.care-hours.claim.reset') }}" method="POST">@csrf<button type="submit" class="text-sm font-semibold underline">{{ $giftIntent ? 'Choose someone else' : 'Change recipient or code' }}</button></form>
            </section>
        @elseif($giftIntent)
            <section class="hc-surface space-y-5 p-6">
                <p class="font-display text-2xl font-semibold">{{ \App\Models\CareHourAccount::formatMinutes($giftIntent['paid_minutes']) }} of care</p>
                <form action="{{ route('family.care-hours.claim.preview') }}" method="POST" class="space-y-3">
                    @csrf<input type="hidden" name="recipient" value="self">
                    <p>{{ auth()->user()->name }}</p>
                    <button type="submit" class="hc-primary-button w-full justify-center">For myself</button>
                </form>
                @if($accounts->isNotEmpty() || $profiles->isNotEmpty() || $recipients->isNotEmpty())
                    <details class="border-t border-[#D8E1D7] pt-4">
                        <summary class="cursor-pointer py-2 text-sm font-semibold underline">For someone I care for</summary>
                        <form action="{{ route('family.care-hours.claim.preview') }}" method="POST" class="mt-4 space-y-4">
                            @csrf
                            <label for="guided-gift-recipient" class="block font-semibold">Choose a care recipient</label>
                            <select id="guided-gift-recipient" name="recipient" required class="min-h-12 w-full rounded-xl border-[#D8E1D7]">
                                <option value="">Choose a care recipient</option>
                                @foreach($accounts as $account)<option value="account:{{ $account->id }}">{{ $account->display_name }}</option>@endforeach
                                @foreach($profiles as $profile)<option value="profile:{{ $profile->id }}">{{ $profile->preferred_name ?: $profile->full_name }}</option>@endforeach
                                @foreach($recipients as $recipient)<option value="recipient:{{ $recipient->id }}">{{ $recipient->full_name }} · care request #{{ $recipient->care_request_id }}</option>@endforeach
                            </select>
                            <button type="submit" class="hc-secondary-button">Review gift</button>
                        </form>
                    </details>
                @endif
            </section>
            <form action="{{ route('care-hours.claim.reset') }}" method="POST">@csrf<button type="submit" class="text-sm font-semibold underline">Use a different code</button></form>
        @elseif($accounts->isNotEmpty() || $profiles->isNotEmpty() || $recipients->isNotEmpty())
            <form action="{{ route('family.care-hours.claim.preview') }}" method="POST" class="hc-surface space-y-5 p-6">
                @csrf
                <div><label for="gift-claim-code" class="block font-semibold">Gift code</label><input id="gift-claim-code" name="code" required maxlength="80" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="Enter your gift code" class="mt-2 min-h-12 w-full rounded-xl border-[#D8E1D7] font-mono"></div>
                <div><label for="gift-recipient" class="block font-semibold">Who is this gift for?</label>
                    <select id="gift-recipient" name="recipient" required class="mt-2 min-h-12 w-full rounded-xl border-[#D8E1D7]">
                        <option value="">Choose a care recipient</option>
                        @foreach($accounts as $account)<option value="account:{{ $account->id }}">{{ $account->display_name }}</option>@endforeach
                        @foreach($profiles as $profile)<option value="profile:{{ $profile->id }}">{{ $profile->preferred_name ?: $profile->full_name }}</option>@endforeach
                        @foreach($recipients as $recipient)<option value="recipient:{{ $recipient->id }}">{{ $recipient->full_name }} · care request #{{ $recipient->care_request_id }}</option>@endforeach
                    </select>
                </div>
                <button type="submit" class="hc-primary-button">Review gift</button>
            </form>
        @else
            <form action="{{ route('care-hours.claim.start') }}" method="POST" class="hc-surface space-y-5 p-6">
                @csrf
                <p>Enter your code to claim care for yourself. No card or care request needed.</p>
                <div><label for="gift-entry-code" class="block font-semibold">Gift code</label><input id="gift-entry-code" name="code" required maxlength="80" autocomplete="off" autocapitalize="characters" spellcheck="false" class="mt-2 min-h-12 w-full rounded-xl border-[#D8E1D7] font-mono"></div>
                <button type="submit" class="hc-primary-button">Continue</button>
                <p class="text-sm text-[#485E53]">Your gift does not expire.</p>
            </form>
        @endif
        <p class="text-sm text-[#485E53]">Claiming is free. The code can be used once, and the hours never expire.</p>
    </div>
</x-app-layout>
