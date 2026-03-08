<div class="max-w-7xl mx-auto py-8 grid grid-cols-1 lg:grid-cols-4 gap-6">
    <x-card class="lg:col-span-1">
        <div class="space-y-4">
            <x-input label="City" wire:model.live="city" />
            <x-input label="State (2 letters)" wire:model.live="state" maxlength="2" />

            <x-select.styled
                wire:model.live="taskIds"
                multiple
                label="Tasks"
                :options="collect($taskOptions)->map(fn($item)=>['label'=>$item['name'],'value'=>$item['id']])->values()->all()"
            />

            <x-select.styled
                label="Job type"
                wire:model.live="requestType"
                :options="[
                    ['label' => 'All types', 'value' => 'all'],
                    ['label' => 'One-time', 'value' => \App\Models\CareRequest::TYPE_ONE_TIME],
                    ['label' => 'Recurring', 'value' => \App\Models\CareRequest::TYPE_RECURRING],
                ]"
            />

            <x-select.styled
                label="When"
                wire:model.live="when"
                :options="[
                    ['label' => 'Any date', 'value' => 'any'],
                    ['label' => 'Today', 'value' => 'today'],
                    ['label' => 'This week', 'value' => 'this_week'],
                ]"
            />

            <x-select.styled
                label="Sort"
                wire:model.live="sort"
                :options="[
                    ['label' => 'Newest', 'value' => 'newest'],
                    ['label' => 'Soonest start', 'value' => 'start_soon'],
                    ['label' => 'Highest budget', 'value' => 'budget_high'],
                ]"
            />
        </div>
    </x-card>

    <div class="lg:col-span-3 space-y-4">
        @if (session('status'))
            <x-alert color="green">{{ session('status') }}</x-alert>
        @endif

        @forelse ($requests as $request)
            <x-card>
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-1">
                        <p class="font-semibold">{{ $request->title }}</p>
                        <p class="text-sm text-slate-600">
                            {{ $request->city }}, {{ $request->state }}
                            @if ($request->request_type === \App\Models\CareRequest::TYPE_ONE_TIME)
                                - {{ optional($request->requested_start_at)->format('M d, Y H:i') }}
                            @else
                                - Recurring
                            @endif
                        </p>
                        <p class="text-sm text-slate-600">
                            Recipient: {{ $request->recipient?->full_name ?? 'Unknown' }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if ($request->request_type === \App\Models\CareRequest::TYPE_RECURRING)
                            <x-badge text="RECURRING" color="green" />
                        @endif
                        <x-badge text="OPEN" color="blue" />
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($request->tasks as $task)
                        <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-700">{{ $task->name }}</span>
                    @endforeach
                </div>

                <div class="mt-4 flex items-center justify-between">
                    @php $existingApplication = $request->applications->first(); @endphp
                    @if ($existingApplication)
                        <p class="text-sm text-slate-600">You already applied ({{ strtoupper($existingApplication->status) }})</p>
                    @else
                        <p class="text-sm text-slate-600">No application sent yet</p>
                    @endif

                    <a href="{{ route('care-requests.apply', $request->id) }}" wire:navigate class="text-sm underline text-blue-700">
                        {{ $existingApplication ? 'Update application' : 'Apply' }}
                    </a>
                </div>

                @if ($existingApplication && in_array($existingApplication->status, ['shortlisted', 'hired'], true))
                    <div class="mt-3">
                        @if ($existingApplication->conversation)
                            <a href="{{ route('messages.show', $existingApplication->conversation->id) }}" wire:navigate>
                                <x-button color="indigo" light>Open chat with family</x-button>
                            </a>
                        @else
                            <a href="{{ route('care-requests.apply', $request->id) }}" wire:navigate class="text-sm underline text-indigo-700">
                                Open application to start chat
                            </a>
                        @endif
                    </div>
                @endif

                @php $invitation = $request->invitations->first(); @endphp
                @if ($invitation && $invitation->status === \App\Models\CareRequestInvitation::STATUS_PENDING)
                    <div class="mt-3 rounded-md border border-cyan-200 bg-cyan-50 px-3 py-2 text-xs text-cyan-900">
                        You were invited by this family. Respond in the Invitations page.
                    </div>
                @endif
            </x-card>
        @empty
            <x-card>
                <p class="text-sm text-slate-600">No open requests match your current filters.</p>
            </x-card>
        @endforelse

        <div>{{ $requests->links() }}</div>
    </div>
</div>
