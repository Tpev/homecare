@props(['careRequest', 'application', 'unavailable' => false, 'id', 'staffWorkspace' => false])

@if($staffWorkspace)
    <div id="{{ $id }}" class="rounded-2xl border border-[#D5E1D8] bg-[#F3F7F3] p-4 text-left" data-testid="staff-prepaid-prompt">
        <p class="text-sm font-semibold">Add prepaid hours to hire</p>
        <p class="mt-1 text-sm">This family’s prepaid balance must cover the visit before you confirm the caregiver.</p>
        <a class="hc-primary-button mt-3" href="{{ route('customer-care.customer', $careRequest->family_user_id) }}">Manage prepaid hours</a>
    </div>
@else
    <x-family-hire-payment-prompt :care-request="$careRequest" :application="$application" :unavailable="$unavailable" :id="$id" />
@endif
