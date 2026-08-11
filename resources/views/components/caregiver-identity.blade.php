@props([
    'caregiver',
    'label' => 'Assigned caregiver',
])

@php
    $caregiverName = trim((string) ($caregiver?->name ?? ''));
    $photoPath = $caregiver?->caregiverProfile?->profile_photo_path;
    $photoUrl = $photoPath
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($photoPath)
        : null;
    $initials = collect(preg_split('/\s+/', $caregiverName) ?: [])
        ->filter()
        ->map(fn ($part) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr((string) $part, 0, 1)))
        ->take(2)
        ->join('');
    $initials = $initials !== '' ? $initials : 'CG';
@endphp

@if ($caregiverName !== '')
    <div {{ $attributes->class(['flex min-w-0 items-center gap-2.5']) }}>
        <div class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full border border-[#DED6CA] bg-[#17313F] text-xs font-semibold text-white">
            @if ($photoUrl)
                <img
                    src="{{ $photoUrl }}"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    class="h-full w-full object-cover"
                >
            @else
                <span aria-hidden="true">{{ $initials }}</span>
            @endif
        </div>
        <div class="min-w-0">
            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-[#7B8794]">{{ $label }}</p>
            <p class="truncate text-sm font-semibold text-[#17313F]">{{ $caregiverName }}</p>
        </div>
    </div>
@endif
