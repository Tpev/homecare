<section @if($hasPendingRecruitment) wire:poll.10s="refreshRecruitment" @endif aria-labelledby="recruitment-heading">
    <x-card>
        <x-slot:header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 id="recruitment-heading" class="text-lg font-semibold text-slate-900">Lolo caregiver outreach</h2>
                    <p class="mt-1 text-sm font-medium text-slate-800">{{ $careRequest->title ?: 'Untitled request' }} · #{{ $careRequest->id }}</p>
                    <p class="mt-1 text-sm text-slate-600">Email caregivers a personal invitation from Lolo with a direct link to this job.</p>
                </div>
                @if(!$showRecruitmentPanel && !$recruitmentUnavailableReason)
                    <button type="button" wire:click="openRecruitmentPanel" wire:loading.attr="disabled" class="shrink-0 rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800 disabled:opacity-50">Invite caregivers to apply</button>
                @elseif($showRecruitmentPanel)
                    <button type="button" wire:click="closeRecruitmentPanel" wire:loading.attr="disabled" class="shrink-0 rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50">Close selection</button>
                @endif
            </div>
        </x-slot:header>

        @if(session('recruitmentStatus'))
            <div role="status" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('recruitmentStatus') }}</div>
        @endif

        @if($recruitmentUnavailableReason)
            <p class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">{{ $recruitmentUnavailableReason }}</p>
        @endif

        @error('recruitment')
            <p role="alert" class="mt-3 text-sm text-rose-700">{{ $message }}</p>
        @enderror

        @if($showRecruitmentPanel && !$recruitmentUnavailableReason)
            <div class="space-y-4">
                <div>
                    <label for="recruitment-search" class="block text-sm font-semibold text-slate-800">Find caregivers</label>
                    <input id="recruitment-search" type="search" wire:model.live.debounce.300ms="recruitmentSearch" maxlength="200" placeholder="Search name, email, city or ZIP" class="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-cyan-600 focus:ring-cyan-600" />
                    <p class="mt-2 text-xs text-slate-500">Eligible caregivers who have not applied or already been invited. Selections stay selected across searches and pages.</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="mr-auto text-sm text-slate-600">{{ number_format($recruitmentCandidates->total()) }} matching caregivers</span>
                    <button type="button" wire:click="selectRecruitmentPage" wire:loading.attr="disabled" @disabled($recruitmentCandidates->isEmpty() || count($selectedRecruitmentCaregivers) >= $recruitmentBatchLimit) class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50">Select this page</button>
                    <button type="button" wire:click="selectMatchingRecruitmentCaregivers" wire:loading.attr="disabled" @disabled($recruitmentCandidates->total() === 0) class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50">Select up to {{ $recruitmentBatchLimit }} matching</button>
                    <button type="button" wire:click="clearRecruitmentSelection" wire:loading.attr="disabled" @disabled(count($selectedRecruitmentCaregivers) === 0) class="rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-50">Clear selection</button>
                </div>

                <div class="divide-y divide-slate-100 overflow-hidden rounded-lg border border-slate-200">
                    @forelse($recruitmentCandidates as $caregiver)
                        @php($isSelected = in_array((string) $caregiver->id, $selectedRecruitmentCaregivers))
                        <label wire:key="recruitment-caregiver-{{ $caregiver->id }}" class="flex items-start gap-3 p-3 hover:bg-slate-50 {{ $isSelected ? 'bg-cyan-50' : '' }}">
                            <input type="checkbox" wire:model.live="selectedRecruitmentCaregivers" value="{{ $caregiver->id }}" wire:loading.attr="disabled" wire:target="sendRecruitmentInvitations,selectRecruitmentPage,selectMatchingRecruitmentCaregivers,clearRecruitmentSelection" @disabled(!$isSelected && count($selectedRecruitmentCaregivers) >= $recruitmentBatchLimit) class="mt-1 rounded border-slate-300 text-cyan-700 focus:ring-cyan-600" />
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-slate-900">{{ $caregiver->name }}</span>
                                <span class="block break-all text-xs text-slate-600">{{ $caregiver->email }}</span>
                                <span class="mt-1 block text-xs text-slate-500">{{ collect([$caregiver->city, $caregiver->state, $caregiver->caregiverProfile?->service_area_zip])->filter()->join(', ') ?: 'Location not provided' }}</span>
                            </span>
                        </label>
                    @empty
                        <p class="p-4 text-sm text-slate-500">No eligible caregivers match this search. Try a different name or location.</p>
                    @endforelse
                </div>

                {{ $recruitmentCandidates->links() }}

                @error('selectedRecruitmentCaregivers')
                    <p role="alert" class="text-sm text-rose-700">{{ $message }}</p>
                @enderror
                @error('selectedRecruitmentCaregivers.*')
                    <p role="alert" class="text-sm text-rose-700">Select valid caregivers from the list.</p>
                @enderror

                <div class="flex flex-col gap-3 rounded-lg bg-slate-50 p-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ count($selectedRecruitmentCaregivers) }} selected <span class="font-normal text-slate-500">/ {{ $recruitmentBatchLimit }} per send</span></p>
                        <p class="mt-1 text-xs text-slate-600">Sends the standard Lolo email. Caregivers choose whether to apply.</p>
                    </div>
                    <button type="button" wire:click="sendRecruitmentInvitations" wire:loading.attr="disabled" @disabled(count($selectedRecruitmentCaregivers) === 0 || count($selectedRecruitmentCaregivers) > $recruitmentBatchLimit) class="shrink-0 rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800 disabled:opacity-50">
                        <span wire:loading.remove wire:target="sendRecruitmentInvitations">Send invitations ({{ count($selectedRecruitmentCaregivers) }})</span>
                        <span wire:loading wire:target="sendRecruitmentInvitations">Queueing invitations…</span>
                    </button>
                </div>
            </div>
        @endif

        @if($recruitmentHistory->isNotEmpty())
            <div class="{{ $showRecruitmentPanel || $recruitmentUnavailableReason ? 'mt-5 border-t border-slate-200 pt-4' : '' }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-slate-900">Recent Lolo outreach</h3>
                    <p class="text-xs text-slate-500">{{ $recruitmentCounts->sum() }} total · {{ $recruitmentCounts->get('queued', 0) + $recruitmentCounts->get('sending', 0) }} pending · {{ $recruitmentCounts->get('sent', 0) }} sent · {{ $recruitmentCounts->get('failed', 0) + $recruitmentCounts->get('queue_failed', 0) }} failed · {{ $recruitmentCounts->get('skipped', 0) }} skipped</p>
                </div>
                <div class="mt-3 divide-y divide-slate-100">
                    @foreach($recruitmentHistory as $outreach)
                        <div wire:key="recruitment-history-{{ $outreach->id }}" class="flex flex-wrap items-start justify-between gap-2 py-2">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-800">{{ $outreach->caregiver?->name ?: 'Deleted caregiver' }}</p>
                                <p class="break-all text-xs text-slate-500">{{ $outreach->caregiver?->email }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $outreach->created_at?->format('M d, Y H:i') }} · {{ $outreach->inviter?->name ?: 'Former admin' }}</p>
                                @if($outreach->status_reason)
                                    <p class="mt-1 text-xs text-slate-600">{{ $outreach->status_reason }}</p>
                                @endif
                            </div>
                            <span class="rounded-full px-2 py-1 text-xs font-semibold {{ match($outreach->status) { 'sent' => 'bg-emerald-50 text-emerald-700', 'failed', 'queue_failed' => 'bg-rose-50 text-rose-700', 'skipped' => 'bg-slate-100 text-slate-600', default => 'bg-cyan-50 text-cyan-800' } }}">{{ $outreach->status === 'queue_failed' ? 'Not queued' : ucfirst($outreach->status) }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-slate-500">Showing the latest {{ $recruitmentHistory->count() }} invitations. Sent means the email provider accepted the message.</p>
            </div>
        @elseif(!$showRecruitmentPanel && !$recruitmentUnavailableReason)
            <p class="text-sm text-slate-500">No Lolo invitations sent for this job yet.</p>
        @endif
    </x-card>
</section>
