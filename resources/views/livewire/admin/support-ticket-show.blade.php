<div
    class="min-h-screen bg-slate-50 px-4 py-6 sm:px-6 lg:px-8"
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
    @endphp

    <div class="mx-auto max-w-7xl space-y-5">
        @if (session('status'))
            <x-alert color="green">{{ session('status') }}</x-alert>
        @endif

        <header class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <a href="{{ route('admin.support.tickets') }}" wire:navigate class="text-sm font-semibold text-emerald-700 hover:underline">&larr; Back to support queue</a>
                <p class="mt-3 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Support ticket #{{ $ticket->id }}</p>
                <h1 class="mt-1 text-3xl font-extrabold tracking-tight text-slate-950">{{ $ticket->subject }}</h1>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-badge :text="strtoupper($ticket->status)" color="blue" />
                <x-badge :text="strtoupper($ticket->priority)" color="slate" />
                <x-badge :text="strtoupper($ticket->category)" color="slate" />
            </div>
        </header>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-lg font-bold text-slate-950">Conversation with {{ $ticket->opener?->name ?: 'user' }}</h2>
                    <p class="mt-1 text-sm text-slate-500">Public replies notify the user. Internal notes stay with the admin team.</p>
                </div>

                <div
                    class="max-h-[62vh] min-h-[32rem] overflow-y-auto bg-slate-50 px-4 py-5 sm:px-6"
                    x-data="{ jump() { this.$el.scrollTop = this.$el.scrollHeight } }"
                    x-init="$nextTick(() => jump())"
                    x-on:support-message-sent.window="$nextTick(() => jump())"
                >
                    @if ($this->hasOlderMessages)
                        <div class="mb-4 text-center">
                            <button type="button" wire:click="loadMore" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Load older messages</button>
                        </div>
                    @endif

                    <div class="space-y-4">
                        <div class="flex justify-start">
                            <article class="max-w-[88%] rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm md:max-w-[72%]">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">Ticket opened</p>
                                <p class="mt-2 whitespace-pre-line text-sm">{{ $ticket->description }}</p>
                                <div class="mt-2 flex items-center justify-between gap-4 text-[11px] text-slate-500">
                                    <span>{{ $ticket->opener?->name ?: 'User' }} · {{ $ticket->opener?->role }}</span>
                                    <span>{{ $ticket->created_at?->format('M j, g:i A') }}</span>
                                </div>
                            </article>
                        </div>

                        @if ($ticket->admin_note)
                            <div class="flex justify-end">
                                <article class="max-w-[88%] rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sky-950 shadow-sm md:max-w-[72%]">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-sky-700">Previous public admin response</p>
                                    <p class="mt-2 whitespace-pre-line text-sm">{{ $ticket->admin_note }}</p>
                                    <p class="mt-2 text-[11px] text-sky-700">Legacy response — still visible to the user</p>
                                </article>
                            </div>
                        @endif

                        @foreach ($messages as $message)
                            @php
                                $internal = $message->isInternalNote();
                                $fromAdmin = $message->sender?->isAdministrator() ?? false;
                            @endphp
                            <div wire:key="admin-support-message-{{ $message->id }}" class="flex {{ $internal || $fromAdmin ? 'justify-end' : 'justify-start' }}">
                                <article class="max-w-[88%] rounded-2xl px-4 py-3 shadow-sm md:max-w-[72%] {{ $internal ? 'border border-amber-200 bg-amber-50 text-amber-950' : ($fromAdmin ? 'bg-emerald-800 text-white' : 'border border-slate-200 bg-white text-slate-800') }}">
                                    @if ($internal)
                                        <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-amber-700">Internal note · Admin only</p>
                                    @endif
                                    <p class="{{ $internal ? 'mt-2 ' : '' }}whitespace-pre-line text-sm">{{ $message->body }}</p>
                                    <div class="mt-2 flex items-center justify-between gap-4 text-[11px] {{ $internal ? 'text-amber-700' : ($fromAdmin ? 'text-emerald-100' : 'text-slate-500') }}">
                                        <span>{{ $message->sender?->name ?: 'Former user' }} · {{ $message->sender?->role ?: 'unknown' }}</span>
                                        <span>{{ $message->created_at?->format('M j, g:i A') }}</span>
                                    </div>
                                </article>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="border-t border-slate-200 bg-white p-4 sm:p-5">
                    @if ($isClosed)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                            This ticket is closed and read-only. Change its status before adding a reply or internal note.
                        </div>
                    @else
                        <div class="mb-3 grid grid-cols-2 gap-2 rounded-xl bg-slate-100 p-1">
                            <label class="cursor-pointer">
                                <input type="radio" wire:model.live="messageKind" value="public" class="peer sr-only">
                                <span class="flex min-h-10 items-center justify-center rounded-lg px-3 text-sm font-semibold text-slate-600 peer-checked:bg-white peer-checked:text-emerald-800 peer-checked:shadow-sm">Reply to user</span>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" wire:model.live="messageKind" value="internal_note" class="peer sr-only">
                                <span class="flex min-h-10 items-center justify-center rounded-lg px-3 text-sm font-semibold text-slate-600 peer-checked:bg-amber-50 peer-checked:text-amber-800 peer-checked:shadow-sm">Internal note</span>
                            </label>
                        </div>

                        <form wire:submit="sendMessage" class="space-y-3">
                            <textarea
                                wire:model="messageBody"
                                rows="4"
                                placeholder="{{ $messageKind === 'internal_note' ? 'Add a private note for admins...' : 'Write a reply to the user...' }}"
                                x-on:focus="$dispatch('support-compose-focus')"
                                x-on:blur="$dispatch('support-compose-blur')"
                                class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm shadow-sm outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100"
                            ></textarea>
                            @error('messageBody') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror

                            <div class="flex items-center justify-between gap-3">
                                <p class="text-xs text-slate-500">
                                    {{ $messageKind === 'internal_note' ? 'Only admins can see this note.' : 'The user will be notified of this reply.' }}
                                </p>
                                <button type="submit" wire:loading.attr="disabled" wire:target="sendMessage" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-700 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:cursor-wait disabled:opacity-60">
                                    <span wire:loading.remove wire:target="sendMessage">{{ $messageKind === 'internal_note' ? 'Add note' : 'Send reply' }}</span>
                                    <span wire:loading wire:target="sendMessage">Saving...</span>
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </section>

            <aside class="space-y-4">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">User</p>
                    <p class="mt-2 font-bold text-slate-950">{{ $ticket->opener?->name ?: 'Unknown user' }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $ticket->opener?->email }}</p>
                    @if ($ticket->opener?->phone)
                        <p class="mt-1 text-sm text-slate-600">{{ $ticket->opener->phone }}</p>
                    @endif
                    <p class="mt-2 text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">{{ $ticket->opener?->role }}</p>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="font-bold text-slate-950">Ticket controls</h2>

                    <form wire:submit="updateStatus" class="mt-4 space-y-2">
                        <label class="block text-sm font-medium text-slate-700" for="ticket-status">Status</label>
                        <select id="ticket-status" wire:model="status" class="min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            <option value="open">Open</option>
                            <option value="in_progress">In progress</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                        </select>
                        <button type="submit" class="min-h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Update status</button>
                    </form>

                    <form wire:submit="updateAssignment" class="mt-5 space-y-2">
                        <label class="block text-sm font-medium text-slate-700" for="ticket-assignee">Assigned admin</label>
                        <select id="ticket-assignee" wire:model="assignedAdminId" class="min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            <option value="">Unassigned</option>
                            @foreach ($this->adminOptions as $adminId => $adminName)
                                <option value="{{ $adminId }}">{{ $adminName }}</option>
                            @endforeach
                        </select>
                        @error('assignedAdminId') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                        <button type="submit" class="min-h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Update assignment</button>
                    </form>
                </section>

                @if ($ticket->careRequest || $ticket->careBooking)
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Related care</p>
                        @if ($ticket->careRequest)
                            <a href="{{ route('admin.requests.show', $ticket->careRequest) }}" wire:navigate class="mt-2 block font-semibold text-emerald-700 hover:underline">
                                Request #{{ $ticket->careRequest->id }}
                            </a>
                            <p class="mt-1 text-sm text-slate-600">{{ $ticket->careRequest->title }}</p>
                        @endif
                        @if ($ticket->careBooking)
                            <p class="mt-3 text-sm text-slate-600">Booking #{{ $ticket->careBooking->id }} · {{ $ticket->careBooking->status }}</p>
                        @endif
                    </section>
                @endif

                <section class="rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-600 shadow-sm">
                    <p><span class="font-semibold text-slate-800">Opened:</span> {{ $ticket->created_at?->format('M j, Y g:i A') }}</p>
                    <p class="mt-2"><span class="font-semibold text-slate-800">Last public activity:</span> {{ $ticket->last_public_message_at?->diffForHumans() ?: 'Legacy ticket' }}</p>
                    @if ($ticket->resolved_at)
                        <p class="mt-2"><span class="font-semibold text-slate-800">Resolved:</span> {{ $ticket->resolved_at->format('M j, Y g:i A') }}</p>
                    @endif
                </section>
            </aside>
        </div>
    </div>
</div>
