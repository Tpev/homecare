@php($leadFormErrors = collect($errors->get('leadForm.*'))->flatten())
@if ($leadFormErrors->isNotEmpty())
    <div role="alert" class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900">
        <p class="font-semibold">Lead changes were not saved.</p>
        <ul class="mt-1 list-inside list-disc space-y-1">
            @foreach ($leadFormErrors as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
