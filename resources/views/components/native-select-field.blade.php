@props([
    'label',
    'options' => [],
    'id' => null,
])

@php
    $fieldId = $id ?: 'select-'.\Illuminate\Support\Str::slug((string) $label).'-'.substr(md5(json_encode($options)), 0, 6);
@endphp

<div>
    <label for="{{ $fieldId }}" class="block text-sm font-medium text-[#324457]">{{ $label }}</label>
    <select
        id="{{ $fieldId }}"
        {{ $attributes->merge(['class' => 'mt-1 h-11 w-full rounded-xl border border-[#DED6CA] bg-white px-3 text-sm font-medium text-[#17313F] shadow-sm outline-none transition focus:border-[#4F6FAF] focus:ring-2 focus:ring-[#4F6FAF]/20']) }}
    >
        @foreach ($options as $option)
            <option value="{{ data_get($option, 'value') }}">{{ data_get($option, 'label') }}</option>
        @endforeach
    </select>
</div>
