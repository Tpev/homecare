<section id="welcome-email" style="scroll-margin-top:90px" class="overflow-hidden rounded-2xl border border-[#D9CEC0] bg-white shadow-sm" wire:poll.20s>
    <header class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
        <div class="flex items-center gap-2">
            <h2 class="text-xl font-bold text-[#173F35]">Welcome email</h2>
            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $savedEnabled ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-100 text-stone-600' }}">{{ $savedEnabled ? 'On' : 'Paused' }}</span>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" wire:click="sendTest" wire:loading.attr="disabled" class="rounded-lg px-3 py-2 text-sm font-semibold text-[#23483F] hover:bg-stone-50">Send test</button>
            @unless($editing)<button type="button" wire:click="$set('editing', true)" class="rounded-lg border border-stone-200 px-3 py-2 text-sm font-semibold text-[#23483F] hover:bg-stone-50">Edit</button>@endunless
        </div>
    </header>

    <div class="flex flex-wrap items-center justify-between gap-2 px-5 pb-4 text-xs text-slate-500">
        <span>New Facebook leads · Immediately</span>
        @if($localCapture)<span class="font-medium text-amber-800">Local preview · No delivery</span>@endif
    </div>
    @if($feedback)<p role="status" class="mx-5 mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-900">{{ $feedback }}</p>@endif
    @error('test')<p role="alert" class="mx-5 mb-4 text-sm text-rose-700">{{ $message }}</p>@enderror

    <div class="grid grid-cols-4 border-y border-stone-100">
        @foreach(['all' => 'Leads', 'sent' => 'Sent', 'account' => 'Accounts', 'request' => 'Requests'] as $key => $label)
            <a href="{{ route('admin.family-acquisition.leads', ['status' => 'all', 'source' => 'facebook', 'welcome' => $key, 'cohort' => $range, 'campaign' => $campaign]) }}" wire:navigate aria-label="{{ $label }}: {{ $counts[$key] }} — view leads" class="px-3 py-3 text-center transition hover:bg-stone-50">
                <span class="block text-xl font-bold text-[#23483F]">{{ $counts[$key] }}</span>
                <span class="text-xs text-slate-500">{{ $label }}</span>
            </a>
        @endforeach
    </div>

    @if($attention->isNotEmpty())
        <details class="border-b border-rose-100 bg-rose-50 px-5 py-3">
            <summary class="cursor-pointer text-sm font-semibold text-rose-900">{{ $attention->count() }} {{ Str::plural('email', $attention->count()) }} need attention</summary>
            @foreach($attention as $message)
                <div class="mt-3 text-xs text-rose-900">
                    <p><strong>{{ $message->lead->name }}</strong> · {{ $message->statusLabel() }}</p>
                    <p class="mt-1">{{ $message->reason ?: 'Check the queue and mail provider before resending.' }}</p>
                    @if($message->status === 'failed' && ! $message->last_attempt_at && ! $message->sent_at && ! $message->previewed_at)<button type="button" wire:click="retry({{ $message->id }})" wire:loading.attr="disabled" class="mt-2 font-bold underline">Retry</button>@endif
                </div>
            @endforeach
        </details>
    @endif

    @if($editing)
        <form wire:submit="save" class="mx-auto max-w-2xl space-y-4 p-5">
            <label class="flex items-center gap-3 text-sm font-semibold text-[#23483F]"><input type="checkbox" wire:model="enabled" class="rounded border-stone-300 text-emerald-800 focus:ring-emerald-700">Send automatically to new leads</label>
            <label class="block"><span class="text-xs font-semibold text-slate-600">Subject</span><input wire:model="subject" class="mt-1.5 w-full rounded-lg border-stone-300 text-sm" maxlength="180">@error('subject')<span class="text-xs text-rose-700">{{ $message }}</span>@enderror</label>
            <label class="block"><span class="text-xs font-semibold text-slate-600">Message</span><textarea wire:model="body" rows="8" class="mt-1.5 w-full rounded-lg border-stone-300 text-sm leading-6"></textarea>@error('body')<span class="text-xs text-rose-700">{{ $message }}</span>@enderror</label>
            <label class="block"><span class="text-xs font-semibold text-slate-600">Reply-to email</span><input type="email" wire:model="replyTo" class="mt-1.5 w-full rounded-lg border-stone-300 text-sm">@error('replyTo')<span class="text-xs text-rose-700">{{ $message }}</span>@enderror</label>
            <div class="flex gap-2"><button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-[#23483F] px-4 py-2.5 text-sm font-semibold text-white">Save</button><button type="button" wire:click="loadSettings" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-600">Cancel</button></div>
        </form>
    @else
        <details open wire:ignore.self>
            <summary class="cursor-pointer px-5 py-4 text-sm font-semibold text-[#23483F]">{{ $subject }}</summary>
            <div class="border-t border-stone-100 bg-[#F7F3EC]">
                <iframe title="Welcome email preview" sandbox="allow-same-origin" srcdoc="{{ $previewHtml }}" x-data x-on:load="$el.style.height = '1px'; $el.style.height = $el.contentDocument.documentElement.scrollHeight + 'px'" x-on:resize.window.debounce.150ms="$el.style.height = '1px'; $el.style.height = $el.contentDocument.documentElement.scrollHeight + 'px'" class="mx-auto block w-full max-w-2xl border-0" style="height:700px"></iframe>
            </div>
        </details>
    @endif
</section>
