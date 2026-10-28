@if (session('status'))<div class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-950" role="status">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="rounded-xl bg-rose-50 p-4 text-sm text-rose-950" role="alert">{{ $errors->first() }}</div>@endif
