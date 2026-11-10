            @if(! $careRequest->staff_archived_at)
                <dialog x-data x-init="if (@js($errors->any() && old('action') === 'archive')) $el.showModal()" @open-modal.window="if ($event.detail === 'remove-managed-request') $el.showModal()" @close-modal.window="if ($event.detail === 'remove-managed-request') $el.close()" class="m-auto max-h-[90dvh] w-[calc(100%_-_2rem)] max-w-lg overflow-y-auto rounded-3xl border border-[#E3D6C5] bg-[#FFFDFA] p-0 text-[#24302D] shadow-xl backdrop:bg-black/40" aria-labelledby="remove-request-title" aria-describedby="remove-request-description">
                    <form method="POST" action="{{ route('customer-care.action', $careRequest) }}" class="space-y-5 p-6">
                        @csrf<input type="hidden" name="action" value="archive">
                        <div><p class="hc-care-eyebrow">Request #{{ $careRequest->id }}</p><h2 id="remove-request-title" class="mt-2 text-2xl font-semibold">Remove this request?</h2></div>
                        <p id="remove-request-description" class="hc-care-subtitle">This closes an open posting and removes the request from active staff lists. Caregivers with active applications are informed that the posting has closed. The customer is not notified.</p>
                        <p class="hc-care-meta">Visit, payment, and conversation history stays available in Archived requests. Finish or cancel active visits and end recurring care first.</p>
                        @if(old('action') === 'archive' && $errors->any())<div role="alert" class="rounded-xl bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div>@endif
                        <label class="block text-sm font-semibold">Reason for removal<textarea class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="reason" required minlength="5" maxlength="2000" rows="3">{{ old('action') === 'archive' ? old('reason') : '' }}</textarea></label>
                        <div class="flex flex-wrap justify-end gap-3"><button type="button" class="hc-secondary-button" @click="$dispatch('close-modal', 'remove-managed-request')">Keep request</button><button class="hc-primary-button">Remove request</button></div>
                    </form>
                </dialog>
            @endif