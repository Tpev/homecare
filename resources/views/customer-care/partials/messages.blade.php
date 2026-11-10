<section class="hc-surface p-5 sm:p-6">
    <div class="mb-6"><p class="hc-care-eyebrow">Caregiver conversations</p><h2 class="mt-2 text-2xl font-semibold">Keep everyone on the care team aligned</h2><p class="hc-care-subtitle">Send messages as yourself. The customer receives no notifications.</p></div>
    <div class="space-y-4">
        @forelse($conversations as $conversation)
            <details class="rounded-2xl border border-[#E3D6C5] p-4 sm:p-5" @if(request()->integer('conversation') ? request()->integer('conversation') === $conversation->id : $loop->first) open @endif>
                <summary class="cursor-pointer font-semibold">{{ $conversation->caregiver?->name }} <span class="hc-care-meta ml-2">{{ $conversation->last_message_at?->diffForHumans() ?? 'Start a conversation' }}</span></summary>
                <div class="my-5 max-h-96 space-y-4 overflow-y-auto pr-2" aria-label="Conversation with {{ $conversation->caregiver?->name }}">
                    @forelse($conversation->messages as $message)
                        <div class="flex {{ $message->sender_user_id === $conversation->caregiver_user_id ? 'justify-start' : 'justify-end' }}">
                            <div class="max-w-[90%] rounded-2xl p-4 {{ $message->sender_user_id === $conversation->caregiver_user_id ? 'bg-[#F8F0E2]' : 'bg-[#E5EDE3]' }}">
                                <p class="hc-care-meta">{{ $message->sender?->name }} · {{ $message->created_at->format('M j, g:i A') }}</p>
                                <p class="mt-2 whitespace-pre-line break-words text-sm leading-6">{{ $message->body }}</p>
                            </div>
                        </div>
                    @empty<p class="hc-care-subtitle">No messages yet. Introduce yourself and confirm the care arrangements.</p>@endforelse
                </div>
                @if(! $careRequest->staff_archived_at && app(\App\Services\Messaging\CareRequestChatService::class)->closedReason($conversation->careRequest, $conversation->caregiver_user_id) === null)
                    <form method="POST" action="{{ route('customer-care.action', $careRequest) }}" class="space-y-3 border-t border-[#E3D6C5] pt-5">
                        @csrf<input type="hidden" name="action" value="message"><input type="hidden" name="caregiver_id" value="{{ $conversation->caregiver_user_id }}"><input type="hidden" name="conversation_id" value="{{ $conversation->id }}">
                        <label class="block text-sm font-semibold">Message to {{ $conversation->caregiver?->name }}<textarea class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="body" required maxlength="3000" rows="3" placeholder="Write a message to the caregiver…">{{ (int) old('conversation_id') === $conversation->id ? old('body') : '' }}</textarea></label>
                        <div class="flex flex-wrap items-center justify-between gap-3"><span class="hc-care-meta">Sending as {{ auth()->user()->name }}</span><button class="hc-primary-button">Send message</button></div>
                    </form>
                @else<p class="hc-care-meta border-t border-[#E3D6C5] pt-4">This conversation is closed. Its history remains available.</p>@endif
            </details>
        @empty
            <div class="hc-care-soft p-8 text-center"><h3 class="text-lg font-semibold">No conversations yet</h3><p class="hc-care-subtitle">Conversations appear when caregivers apply or are hired.</p><a class="hc-care-text-link mt-2" href="{{ route('customer-care.show', ['careRequest' => $careRequest, 'tab' => 'caregivers']) }}">View caregivers</a></div>
        @endforelse
    </div>
</section>
