<div {{ $attributes->class('flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-[#6E746F]') }}>
    <a href="{{ route('legal.show', ['slug' => 'platform-terms-of-service']) }}" class="transition hover:text-[#0F3D3E] hover:underline">Terms of Service</a>
    <a href="{{ route('legal.show', ['slug' => 'privacy-policy']) }}" class="transition hover:text-[#0F3D3E] hover:underline">Privacy Policy</a>
    <a href="{{ route('legal.show', ['slug' => 'payment-terms-and-cancellation-policy']) }}" class="transition hover:text-[#0F3D3E] hover:underline">Payments & Cancellation</a>
    <a href="{{ route('legal.show', ['slug' => 'caregiver-terms']) }}" class="transition hover:text-[#0F3D3E] hover:underline">Caregiver Terms</a>
    <a href="{{ route('legal.show', ['slug' => 'client-and-family-terms']) }}" class="transition hover:text-[#0F3D3E] hover:underline">Family Terms</a>
    <a href="{{ route('legal.show', ['slug' => 'acceptable-use-and-safety-policy']) }}" class="transition hover:text-[#0F3D3E] hover:underline">Safety Policy</a>
    <a href="{{ route('legal.index') }}" class="transition hover:text-[#0F3D3E] hover:underline">All Legal Documents</a>
</div>


