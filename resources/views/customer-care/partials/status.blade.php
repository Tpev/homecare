@if(session('status'))
    <div role="status" class="rounded-2xl border border-green-200 bg-green-50 p-4 text-sm text-green-900">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div role="alert" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">
        <p class="font-semibold">Please review the following:</p><ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
