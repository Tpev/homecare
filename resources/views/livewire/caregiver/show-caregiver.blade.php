<div class="max-w-5xl mx-auto py-8 space-y-6">
    <x-card>
        <h1 class="text-2xl font-semibold">{{ $caregiver->user->name }}</h1>
        <p>{{ $caregiver->user->city }}, {{ $caregiver->user->state }}</p>
        <p class="mt-2">${{ $caregiver->hourly_rate }}/hr</p>

        <div class="mt-2">
            <span>Rating:</span>
            <span>⭐ {{ number_format($caregiver->average_rating, 1) }}</span>
            <span>({{ $caregiver->reviews_count }} reviews)</span>
        </div>

        <p class="mt-4">{{ $caregiver->bio }}</p>

        <hr class="my-4">

        <h2 class="font-semibold">Services & Disclaimer</h2>
        <p class="text-sm text-slate-600">
            Non-medical home care only. No nursing, injections, or clinical procedures.
        </p>

        <h2 class="font-semibold mt-4">Reviews</h2>
        <p class="text-sm text-slate-600">No reviews yet.</p>
    </x-card>
</div>
