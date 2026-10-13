<div class="mt-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs text-amber-950" role="note" aria-label="{{ $coverageHeading }}">
    <p class="font-semibold">{{ $coverageHeading }}</p>
    @if (isset($coverage['searchable_profiles'], $coverage['profile_count']))
        <p class="mt-1">{{ number_format($coverage['searchable_profiles']) }} of {{ number_format($coverage['profile_count']) }} source IDs have an available name for matching.</p>
    @endif
    @if (($coverage['source_only_profiles'] ?? 0) > 0)
        <p class="mt-1">{{ number_format($coverage['source_only_profiles']) }} source {{ (int) $coverage['source_only_profiles'] === 1 ? 'ID has' : 'IDs have' }} supporting records but no primary individual record. Available aliases are included in name matching.</p>
    @endif
    @if (($coverage['profiles_without_names'] ?? 0) > 0)
        <p class="mt-1 font-semibold">{{ number_format($coverage['profiles_without_names']) }} source {{ (int) $coverage['profiles_without_names'] === 1 ? 'ID has' : 'IDs have' }} no available name and cannot be matched by name. The source records are retained for reference.</p>
    @endif
    @foreach ($coverage['limitations'] ?? [] as $limitation)
        <p class="mt-1">{{ $limitation }}</p>
    @endforeach
    <p class="mt-2">No Match is limited to the available names in this imported source version.</p>
</div>
