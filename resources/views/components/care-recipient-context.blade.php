@props([
    'recipient' => null,
    'snapshot' => null,
    'context' => null,
    'showName' => false,
    'showDescription' => false,
])

@php
    $name = data_get($context, 'name');
    $relationship = data_get($context, 'relationship');
    $isRequester = data_get($context, 'recipient_is_requester');
    $label = data_get($context, 'label');
    $description = data_get($context, 'description');

    if ($recipient) {
        $name = $recipient->full_name;
        $relationship = $recipient->relationship_to_family;
        $isRequester = $recipient->recipient_is_requester;
        $label = method_exists($recipient, 'recipientContextLabel') ? $recipient->recipientContextLabel() : $label;
        $description = method_exists($recipient, 'recipientContextDescription') ? $recipient->recipientContextDescription() : $description;
    }

    if ($snapshot) {
        $name = $name ?: data_get($snapshot, 'full_name');
        $relationship = $relationship ?: data_get($snapshot, 'relationship_to_family');
        $isRequester = $isRequester ?? data_get($snapshot, 'recipient_is_requester');
    }

    $relationship = trim((string) $relationship);
    $name = trim((string) $name);
    $isRequester = (bool) $isRequester || strcasecmp($relationship, 'self') === 0;
    $label = $label ?: ($isRequester ? 'Requester receives care' : 'Family member receives care');

    if (! $description) {
        if ($isRequester) {
            $description = 'The person posting is also receiving care.';
        } elseif ($relationship !== '') {
            $description = 'A family contact is coordinating care for their '.strtolower($relationship).'.';
        } else {
            $description = 'A family contact is coordinating care for someone else.';
        }
    }

    $badgeClasses = $isRequester
        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
        : 'border-[#D8D1F1] bg-[#F5F1FB] text-[#4F3D89]';

    $hasContext = (bool) $recipient || (bool) $snapshot || (bool) $context || $name !== '' || $relationship !== '' || $isRequester;
@endphp

@if ($hasContext)
    <div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2']) }}>
        <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold {{ $badgeClasses }}">
            {{ $label }}
        </span>

        @if ($showName && $name !== '')
            <span class="text-xs font-medium text-[#607080]">{{ $name }}</span>
        @endif

        @if ($showDescription)
            <span class="basis-full text-xs text-[#607080]">{{ $description }}</span>
        @endif
    </div>
@endif
