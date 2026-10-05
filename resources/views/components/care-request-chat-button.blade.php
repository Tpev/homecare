@props(['careRequestId', 'caregiverId', 'label' => 'Message family'])

<form method="POST" action="{{ route('messages.open', ['careRequest' => $careRequestId, 'caregiver' => $caregiverId]) }}">
    @csrf
    <button type="submit" {{ $attributes->class(['hc-secondary-button min-h-11']) }}>{{ $label }}</button>
</form>
