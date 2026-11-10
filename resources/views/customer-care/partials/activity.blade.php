<div class="grid items-start gap-6 lg:grid-cols-3">
    @if(! $careRequest->staff_archived_at)
        <section class="hc-surface p-5 sm:p-6">
            <h2 class="text-xl font-semibold">Leave a team note</h2><p class="hc-care-subtitle">Keep handoffs and follow-up in one place. Only your team sees these notes.</p>
            <form method="POST" action="{{ route('customer-care.action', $careRequest) }}" class="mt-5 space-y-4">
                @csrf<input type="hidden" name="action" value="note"><label class="block text-sm font-semibold">Internal note<textarea class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="reason" required minlength="5" maxlength="2000" rows="5" placeholder="What should the next team member know?">{{ old('action') === 'note' ? old('reason') : '' }}</textarea></label><button class="hc-primary-button">Save internal note</button>
            </form>
        </section>
    @endif
    <section class="hc-surface p-5 sm:p-6 {{ $careRequest->staff_archived_at ? 'lg:col-span-3' : 'lg:col-span-2' }}">
        <h2 class="text-xl font-semibold">Team activity</h2><p class="hc-care-subtitle">A record of decisions, care updates, and follow-up.</p>
        <ol class="mt-6 space-y-6">
            @forelse($history as $entry)
                <li class="relative border-l-2 border-[#E3D6C5] pl-5">
                    <span class="absolute -left-[5px] top-1 h-2 w-2 rounded-full bg-[#23483F]" aria-hidden="true"></span>
                    <p class="text-sm font-semibold">{{ ucfirst(str_replace('_', ' ', $entry->action)) }}</p>
                    <p class="hc-care-meta mt-1">{{ $entry->actor_name }} · {{ \Carbon\Carbon::parse($entry->created_at)->format('M j, Y · g:i A') }}</p>
                    @if($entry->note)<p class="mt-3 whitespace-pre-line text-sm leading-6">{{ $entry->note }}</p>@endif
                </li>
            @empty<li class="hc-care-subtitle">No team activity recorded yet.</li>@endforelse
        </ol>
    </section>
</div>
