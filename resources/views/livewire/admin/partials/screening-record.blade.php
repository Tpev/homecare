@php
    $fieldLabels = $fieldLabels ?? [];
    $recordTable = $record['table'] ?? ($recordTable ?? null);
@endphp
<dl class="mt-3 space-y-2 text-xs">
    @foreach ($record as $key => $value)
        <div class="border-l-2 border-slate-100 pl-3">
            <dt class="font-semibold text-slate-600">{{ is_numeric($key) ? 'Record '.($key + 1) : ($fieldLabels[$recordTable][$key] ?? str($key)->headline()) }}@if (isset($fieldLabels[$recordTable][$key])) <span class="font-normal text-slate-400">({{ $key }})</span>@endif</dt>
            <dd class="mt-0.5 break-words text-slate-800">
                @if (is_array($value) && $depth < 5)
                    @include('livewire.admin.partials.screening-record', ['record' => $value, 'depth' => $depth + 1, 'fieldLabels' => $fieldLabels, 'recordTable' => $recordTable])
                @elseif (is_array($value))
                    <pre class="max-h-60 overflow-auto whitespace-pre-wrap break-all">{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                @elseif (is_bool($value))
                    {{ $value ? 'Yes' : 'No' }}
                @else
                    {{ $value ?? 'Not supplied' }}
                @endif
            </dd>
        </div>
    @endforeach
</dl>
