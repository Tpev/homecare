<div {{ $attributes->class('flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-slate-600') }}>
    <a href="{{ route('legal.show', ['slug' => 'platform-terms-of-service']) }}" class="hover:text-slate-900 hover:underline">Terms of Service</a>
    <a href="{{ route('legal.show', ['slug' => 'privacy-policy']) }}" class="hover:text-slate-900 hover:underline">Privacy Policy</a>
    <a href="{{ route('legal.show', ['slug' => 'payment-terms-and-cancellation-policy']) }}" class="hover:text-slate-900 hover:underline">Payments & Cancellation</a>
    <a href="{{ route('legal.show', ['slug' => 'caregiver-terms']) }}" class="hover:text-slate-900 hover:underline">Caregiver Terms</a>
    <a href="{{ route('legal.show', ['slug' => 'client-and-family-terms']) }}" class="hover:text-slate-900 hover:underline">Family Terms</a>
    <a href="{{ route('legal.show', ['slug' => 'acceptable-use-and-safety-policy']) }}" class="hover:text-slate-900 hover:underline">Safety Policy</a>
    <a href="{{ route('legal.index') }}" class="hover:text-slate-900 hover:underline">All Legal Documents</a>
</div>

