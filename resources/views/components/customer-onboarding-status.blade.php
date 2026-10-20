@props(['state' => 'pending', 'label'])
<span {{ $attributes->class(['co-status', 'co-status--'.$state]) }}>
    <span class="co-status__mark" aria-hidden="true">{{ $state === 'complete' ? '✓' : ($state === 'attention' ? '!' : '·') }}</span>
    {{ $label }}
</span>
