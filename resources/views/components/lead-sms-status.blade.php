@props(['activity'])
@if($sms = $activity->smsMessage)
    <div class="mt-2 space-y-1 text-xs">
        <p class="font-semibold {{ $sms->awaitingProviderConfirmation() ? 'text-amber-800' : (in_array($sms->status, ['failed', 'undelivered', 'canceled'], true) ? 'text-rose-700' : 'text-emerald-800') }}">{{ $sms->direction === 'incoming' ? 'Status' : 'Delivery' }}: {{ $sms->deliveryLabel() }}</p>
        <p class="text-slate-500">From {{ $sms->from_phone }} · To {{ $sms->to_phone }}</p>
        @if($sms->error_code || $sms->error_message)<p class="{{ $sms->awaitingProviderConfirmation() ? 'text-amber-800' : 'text-rose-700' }}">{{ $sms->error_code ? 'Error '.$sms->error_code.': ' : '' }}{{ $sms->error_message }}</p>@endif
        @foreach($sms->media ?? [] as $media)
            @if(preg_match('/^https:\/\//i', $media['url'] ?? ''))
                <a href="{{ $media['url'] }}" target="_blank" rel="noopener noreferrer" class="block text-emerald-800 underline">View attachment {{ $loop->iteration }}</a>
            @endif
        @endforeach
    </div>
@endif
