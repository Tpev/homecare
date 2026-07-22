<div
    class="hc-page py-6"
    x-data="{
        paused: false,
        timer: null,
        init() {
            this.timer = setInterval(() => {
                if (! this.paused) this.$wire.refreshThread()
            }, 5000)
        },
        destroy() {
            if (this.timer) clearInterval(this.timer)
        },
    }"
    x-on:support-compose-focus.window="paused = true"
    x-on:support-compose-blur.window="paused = false"
    x-on:support-message-sent.window="paused = false"
>
    @php
        $ticket = $this->ticket;
        $messages = $this->messages;
        $isClosed = $ticket->status === \App\Models\SupportTicket::STATUS_CLOSED;
        $isResolved = $ticket->status === \App\Models\SupportTicket::STATUS_RESOLVED;
    @endphp

    <div class="mx-auto max-w-5xl space-y-5">
        @if (session('status'))
            <x-alert color="green">{{ session('status') }}</x-alert>
        @endif

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <a href="{{ route('support.index') }}" wire:navigate class="text-sm font-semibold text-[#0F6B5B] hover:underline">
                    &larr; Back to Support Center
                </a>
                <p class="mt-3 text-xs font-semibold uppercase tracking-[0.16em] text-[#7A8091]">Support ticket #{{ $ticket->id }}</p>
                <h1 class="mt-1 font-display text-2xl font-semibold text-[#17313F]">{{ $ticket->subject }}</h1>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-badge :text="strtoupper($ticket->status)" color="blue" />
                <x-badge :text="strtoupper($ticket->priority)" color="slate" />
                <x-badge :text="strtoupper($ticket->category)" color="slate" />
            </div>
        </div>

        @if ($ticket->careRequest || $ticket->careBooking)
            <div class="rounded-2xl border border-[#D8E1D7] bg-[#F2F8F4] px-4 py-3 text-sm text-[#355048]">
                @if ($ticket->careRequest)
                    <p><span class="font-semibold">Related request:</span> #{{ $ticket->careRequest->id }} {{ $ticket->careRequest->title }}</p>
                @endif
                @if ($ticket->careBooking)
                    <p><span class="font-semibold">Related booking:</span> #{{ $ticket->careBooking->id }}</p>
                @endif
            </div>
        @endif

        <section class="overflow-hidden rounded-[28px] border border-[#DED6CA] bg-[#FFFCF8] shadow-lg shadow-[#0F3D3E]/10">
            <div class="border-b border-[#DED6CA] bg-[#F8F4ED] px-5 py-4">
                <h2 class="font-display text-lg font-semibold text-[#17313F]">Conversation with support</h2>
                <p class="mt-1 text-sm text-[#5B6472]">
                    {{ $ticket->assignedAdmin?->name ? 'Assigned to '.$ticket->assignedAdmin->name : 'A support team member will respond here.' }}
                </p>
            </div>

            <div
                class="max-h-[58vh] min-h-96 overflow-y-auto bg-gradient-to-b from-[#FFFCF8] to-[#F5F1EB]/60 px-4 py-5 sm:px-6"
                x-data="{ jump() { this.$el.scrollTop = this.$el.scrollHeight } }"
                x-init="$nextTick(() => jump())"
                x-on:support-message-sent.window="$nextTick(() => jump())"
            >
                @if ($this->hasOlderMessages)
                    <div class="mb-4 text-center">
                        <button type="button" wire:click="loadMore" class="hc-secondary-button">Load older messages</button>
                    </div>
                @endif

                <div class="space-y-4">
                    <div class="flex justify-end">
                        <article class="max-w-[88%] rounded-2xl bg-[#0F3D3E] px-4 py-3 text-white shadow-sm md:max-w-[72%]">
                            <p class="whitespace-pre-line text-sm">{{ $ticket->description }}</p>
                            <div class="mt-2 flex items-center justify-between gap-4 text-[11px] text-[#DDEEEA]">
                                <span>{{ $ticket->opener?->name ?: 'You' }} · Ticket opened</span>
                                <span>{{ $ticket->created_at?->format('M j, g:i A') }}</span>
                            </div>
                        </article>
                    </div>

                    @if ($ticket->admin_note)
                        <div class="flex justify-start">
                            <article class="max-w-[88%] rounded-2xl border border-[#C9D9D4] bg-white px-4 py-3 text-[#223043] shadow-sm md:max-w-[72%]">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-[#0F6B5B]">Previous admin response</p>
                                <p class="mt-2 whitespace-pre-line text-sm">{{ $ticket->admin_note }}</p>
                                <p class="mt-2 text-[11px] text-[#7A8091]">Legacy response preserved from this ticket</p>
                            </article>
                        </div>
                    @endif

                    @foreach ($messages as $message)
                        @php $mine = (int) $message->sender_user_id === (int) auth()->id(); @endphp
                        <div wire:key="support-message-{{ $message->id }}" class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                            <article class="max-w-[88%] rounded-2xl px-4 py-3 shadow-sm md:max-w-[72%] {{ $mine ? 'bg-[#0F3D3E] text-white' : 'border border-[#DED6CA] bg-white text-[#223043]' }}">
                                <p class="whitespace-pre-line text-sm">{{ $message->body }}</p>
                                <div class="mt-2 flex items-center justify-between gap-4 text-[11px] {{ $mine ? 'text-[#DDEEEA]' : 'text-[#7A8091]' }}">
                                    <span>
                                        {{ $mine
                                            ? 'You · '.str((string) ($message->sender?->role ?: 'user'))->replace('_', ' ')->title()
                                            : ($message->sender?->name ?: 'Support').' · Support' }}
                                    </span>
                                    <span>{{ $message->created_at?->format('M j, g:i A') }}</span>
                                </div>
                            </article>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="border-t border-[#DED6CA] bg-white p-4 sm:p-5">
                @if ($isClosed)
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-700">
                        <p class="font-semibold text-slate-900">This ticket is closed and read-only.</p>
                        <p class="mt-1">If you need more help, please create a new support ticket.</p>
                        <a href="{{ route('support.index') }}" wire:navigate class="mt-3 inline-flex font-semibold text-[#0F6B5B] hover:underline">Create a new ticket</a>
                    </div>
                @else
                    @if ($isResolved)
                        <div class="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                            This ticket is resolved. Sending a reply will reopen it.
                        </div>
                    @endif

                    <form wire:submit="sendMessage" class="space-y-3">
                        <textarea
                            wire:model="messageBody"
                            rows="4"
                            placeholder="Write a reply to support..."
                            x-on:focus="$dispatch('support-compose-focus')"
                            x-on:blur="$dispatch('support-compose-blur')"
                            class="w-full rounded-2xl border border-[#DED6CA] bg-[#FFFCF8] px-4 py-3 text-sm text-[#223043] shadow-sm outline-none transition focus:border-[#0F6B5B] focus:ring-2 focus:ring-[#CDE7DF]"
                        ></textarea>
                        @error('messageBody') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror

                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs text-[#7A8091]">Your reply is visible to the support team.</p>
                            <button type="submit" wire:loading.attr="disabled" wire:target="sendMessage" class="hc-primary-button disabled:cursor-wait disabled:opacity-60">
                                <span wire:loading.remove wire:target="sendMessage">Send reply</span>
                                <span wire:loading wire:target="sendMessage">Sending...</span>
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </section>
    </div>
</div>
