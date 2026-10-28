<x-app-layout><div class="hc-page max-w-2xl space-y-6 py-8">
    <a href="{{ route('family.care-hours.show', $purchase->care_hour_account_id) }}" wire:navigate class="hc-care-text-link">← Care hours</a>
    @include('care-hours.partials.purchase')
</div></x-app-layout>
