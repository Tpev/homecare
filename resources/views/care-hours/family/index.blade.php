<x-app-layout>
    <div class="hc-page max-w-3xl space-y-6 py-8">
        <a href="{{ route('family.requests.index') }}" wire:navigate class="hc-care-text-link">← Back to Care</a>
        <h1 class="font-display text-3xl font-semibold text-[#23483F]">Care hours</h1>
        <p class="text-[#485E53]">Buy care in advance for yourself or someone you care for.</p>
        @include('care-hours.partials.messages')
        @include('care-hours.partials.gift-links')
        @foreach ($accounts as $account)
            <a href="{{ route('family.care-hours.show', $account) }}" wire:navigate class="flex min-h-20 items-center justify-between gap-4 rounded-2xl border border-[#D8E1D7] bg-white p-5">
                <span class="font-semibold">{{ $account->display_name }}</span>
                <span>{{ \App\Models\CareHourAccount::formatMinutes($balances[$account->id]['available_minutes']) }} available →</span>
            </a>
        @endforeach
        @if ($profiles->isNotEmpty() || $recipients->isNotEmpty())
            <form action="{{ route('family.care-hours.enroll') }}" method="POST" class="hc-surface space-y-4 p-5">
                @csrf
                <label for="care-hours-recipient" class="block font-semibold">{{ $accounts->isEmpty() ? 'Who are these hours for?' : 'Choose another care recipient' }}</label>
                <select name="recipient" id="care-hours-recipient" required class="min-h-12 w-full rounded-xl border-[#D8E1D7]">
                    <option value="">Choose a person</option>
                    @foreach ($profiles as $profile)<option value="profile:{{ $profile->id }}">{{ $profile->displayName() }}</option>@endforeach
                    @foreach ($recipients as $recipient)<option value="recipient:{{ $recipient->id }}">{{ $recipient->full_name }} · care request #{{ $recipient->care_request_id }}</option>@endforeach
                </select>
                @if($accounts->isNotEmpty())<label for="care-hours-balance-target" class="block font-semibold">Is this the same person as an existing care balance?</label><select name="balance_target" id="care-hours-balance-target" required class="min-h-12 w-full rounded-xl border-[#D8E1D7]"><option value="">Choose a care balance</option>@foreach($accounts as $existing)<option value="{{ $existing->id }}">Use {{ $existing->display_name }}’s existing hours</option>@endforeach<option value="new">Different person — create a separate balance</option></select><p class="text-sm text-[#485E53]">Choose the existing balance when a new request is for the same person.</p>@endif
                <button class="hc-primary-button" type="submit">Continue</button>
            </form>
        @elseif ($accounts->isEmpty())
            <p class="rounded-2xl border border-[#D8E1D7] bg-white p-5">Care hours are available for existing care recipients. Once you have a care recipient on LoLo, you can buy hours here.</p>
        @endif
    </div>
</x-app-layout>
