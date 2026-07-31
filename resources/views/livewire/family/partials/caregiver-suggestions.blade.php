@if ($suggestedCaregivers->isNotEmpty())
    <div class="grid grid-cols-1 gap-3 xl:grid-cols-3">
        @foreach ($suggestedCaregivers as $suggestion)
            @php
                $suggestionPhotoUrl = !empty($suggestion['profile_photo_path'])
                    ? \Illuminate\Support\Facades\Storage::disk('public')->url($suggestion['profile_photo_path'])
                    : null;
                $suggestionAverageRating = !empty($suggestion['average_rating'])
                    ? (float) $suggestion['average_rating']
                    : null;
                $suggestionReviewsCount = (int) ($suggestion['reviews_count'] ?? 0);
            @endphp
            <div class="rounded-xl border border-[#BDD4F7] bg-white p-3">
                <div class="flex items-start gap-3">
                    <div class="shrink-0">
                        @if ($suggestionPhotoUrl)
                            <img
                                src="{{ $suggestionPhotoUrl }}"
                                alt="{{ $suggestion['name'] }}"
                                class="h-11 w-11 rounded-full border border-[#DED6CA] object-cover"
                            >
                        @else
                            <div class="flex h-11 w-11 items-center justify-center rounded-full border border-[#DED6CA] bg-[#F5F1EB] text-sm font-semibold text-[#0F3D3E]">
                                {{ \Illuminate\Support\Str::of($suggestion['name'])->trim()->explode(' ')->take(2)->map(fn ($part) => \Illuminate\Support\Str::substr($part, 0, 1))->implode('') }}
                            </div>
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="font-display font-semibold text-[#17313F]">{{ $suggestion['name'] }}</p>
                        <p class="text-xs text-[#607080]">{{ $suggestion['proximity'] }} - Match score {{ $suggestion['score'] }}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-[#607080]">
                            @if ($suggestionAverageRating && $suggestionReviewsCount > 0)
                                <span class="inline-flex items-center gap-1 font-medium text-[#17313F]">
                                    <svg viewBox="0 0 20 20" class="h-4 w-4 text-amber-400" fill="currentColor" aria-hidden="true">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.176 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81H7.03a1 1 0 00.951-.69l1.07-3.292z" />
                                    </svg>
                                    {{ number_format($suggestionAverageRating, 1) }}
                                </span>
                                <span>{{ $suggestionReviewsCount }} review{{ $suggestionReviewsCount === 1 ? '' : 's' }}</span>
                            @else
                                <span class="text-[#7B8794]">No reviews yet</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="mt-2 flex flex-wrap gap-1">
                    @if ($suggestion['identity_verified'])
                        <span class="inline-flex rounded-full bg-[#E8F0FF] px-2 py-1 text-[11px] font-medium text-[#4F6FAF]">Identity verified</span>
                    @endif
                    @if ($suggestion['background_check'])
                        <span class="inline-flex rounded-full bg-emerald-100 px-2 py-1 text-[11px] font-medium text-emerald-700">Background check</span>
                    @endif
                    @if ($suggestion['top_caregiver'])
                        <span class="inline-flex rounded-full bg-amber-100 px-2 py-1 text-[11px] font-medium text-amber-700">Top caregiver</span>
                    @endif
                </div>

                <p class="mt-2 text-xs text-[#7B8794]">{{ implode(' - ', array_slice($suggestion['reasons'], 0, 2)) }}</p>

                <div class="mt-3">
                    <x-button color="blue" light wire:click="beginCaregiverInvitation({{ $suggestion['user_id'] }})" class="w-full">
                        Invite {{ \Illuminate\Support\Str::of($suggestion['name'])->before(' ') }}
                    </x-button>
                </div>
            </div>
        @endforeach
    </div>
@else
    <div class="rounded-lg border border-dashed border-[#BDD4F7] bg-white px-3 py-3 text-sm text-[#607080]">
        No auto-suggestions yet. Caregivers who reply will appear here.
    </div>
@endif
