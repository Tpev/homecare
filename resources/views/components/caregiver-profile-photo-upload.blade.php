@props([
    'currentPath' => null,
    'label' => 'Profile photo',
    'property' => 'profile_photo',
    'temporaryPhoto' => null,
])

@php
    $processor = app(\App\Services\Images\CaregiverProfilePhotoProcessor::class);
    $currentPhotoUrl = $currentPath ? \Illuminate\Support\Facades\Storage::disk('public')->url($currentPath) : null;
    $temporaryPhotoUrl = null;

    if ($temporaryPhoto) {
        try {
            $temporaryPhotoUrl = $temporaryPhoto->temporaryUrl();
        } catch (\Throwable) {
            $temporaryPhotoUrl = null;
        }
    }

    $displayPhotoUrl = $temporaryPhotoUrl ?: $currentPhotoUrl;
@endphp

<div
    x-data="caregiverProfilePhotoUpload({
        property: @js($property),
        initialUrl: @js($displayPhotoUrl),
        maxUploadMegabytes: @js($processor->maxUploadMegabytes()),
        maxDimension: @js($processor->maxDimension()),
        quality: @js($processor->quality() / 100),
    })"
    class="rounded-2xl border border-[#E4DDD3] bg-[#FCFAF7] p-4"
>
    <div class="flex items-start gap-4">
        <div class="shrink-0">
            <img
                x-show="previewUrl"
                x-cloak
                :src="previewUrl"
                alt="Profile photo preview"
                class="h-20 w-20 rounded-full border border-[#D9D1C5] object-cover shadow-sm"
            >
            <div
                x-show="!previewUrl"
                class="flex h-20 w-20 items-center justify-center rounded-full border border-dashed border-[#CFC4B7] bg-white text-xs font-semibold uppercase tracking-[0.12em] text-[#7B8794]"
            >
                Photo
            </div>
        </div>

        <div class="min-w-0 flex-1 space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <input
                    x-ref="file"
                    type="file"
                    class="sr-only"
                    accept="image/jpeg,image/png,image/webp,image/heic,image/heif,image/avif,image/gif,image/bmp,image/tiff,.jpg,.jpeg,.png,.webp,.heic,.heif,.avif,.gif,.bmp,.tif,.tiff"
                    x-on:change="select($event)"
                >

                <button
                    type="button"
                    class="inline-flex min-h-10 items-center justify-center rounded-xl bg-[#23483F] px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#19352F] disabled:cursor-not-allowed disabled:opacity-60"
                    x-on:click="$refs.file.click()"
                    :disabled="busy"
                    x-text="previewUrl ? 'Change photo' : @js($label)"
                ></button>

                <span x-show="uploadedName" x-cloak class="max-w-full truncate text-xs font-medium text-[#607080]" x-text="uploadedName"></span>
            </div>

            <p class="text-xs text-[#607080]">
                JPG, PNG, WEBP, HEIC, HEIF, AVIF, GIF, BMP, or TIFF up to {{ $processor->maxUploadMegabytes() }} MB.
            </p>

            <div x-show="busy" x-cloak class="space-y-1.5">
                <div class="h-1.5 overflow-hidden rounded-full bg-[#E4DDD3]">
                    <div class="h-1.5 rounded-full bg-[#4F6FAF] transition-all" :style="`width: ${progress}%`"></div>
                </div>
                <p class="text-xs text-[#607080]" x-text="status"></p>
            </div>

            <p x-show="error" x-cloak class="text-sm text-red-600" x-text="error"></p>

            @error($property)
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>
