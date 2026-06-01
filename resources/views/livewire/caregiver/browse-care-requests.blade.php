<div class="max-w-7xl mx-auto py-8 grid grid-cols-1 lg:grid-cols-4 gap-6">
    <x-card class="lg:col-span-1">
        <div class="space-y-4">
            <x-input label="City" wire:model.live="city" />
            <x-input label="State (2 letters)" wire:model.live="state" maxlength="2" />

            <div>
                <p class="text-sm font-medium text-[#324457]">Tasks</p>
                <div class="mt-2 max-h-48 space-y-2 overflow-y-auto rounded-xl border border-[#DED6CA] bg-[#FFFCF8] p-2">
                    @foreach ($taskOptions as $task)
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-2 text-sm text-[#324457] hover:bg-white" wire:key="request-task-filter-{{ $task['id'] }}">
                            <input type="checkbox" value="{{ $task['id'] }}" wire:model.live="taskIds" class="rounded border-[#B7ADA0] text-[#0F3D3E] focus:ring-[#4F6FAF]">
                            <span>{{ $task['name'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <x-native-select-field
                label="Job type"
                wire:model.live="requestType"
                :options="[
                    ['label' => 'All types', 'value' => 'all'],
                    ['label' => 'One-time', 'value' => \App\Models\CareRequest::TYPE_ONE_TIME],
                    ['label' => 'Recurring', 'value' => \App\Models\CareRequest::TYPE_RECURRING],
                ]"
            />

            <x-native-select-field
                label="When"
                wire:model.live="when"
                :options="[
                    ['label' => 'Any date', 'value' => 'any'],
                    ['label' => 'Today', 'value' => 'today'],
                    ['label' => 'This week', 'value' => 'this_week'],
                ]"
            />

            <x-native-select-field
                label="Sort"
                wire:model.live="sort"
                :options="[
                    ['label' => 'Newest', 'value' => 'newest'],
                    ['label' => 'Soonest start', 'value' => 'start_soon'],
                    ['label' => 'Highest budget', 'value' => 'budget_high'],
                ]"
            />

            <button type="button" wire:click="clearFilters" class="w-full rounded-xl border border-[#DED6CA] bg-white px-3 py-2 text-sm font-semibold text-[#0F3D3E] transition hover:bg-[#F5F1EB]">
                Clear filters
            </button>
        </div>
    </x-card>

    <div class="lg:col-span-3 space-y-4">
        @if (session('status'))
            <x-alert color="green">{{ session('status') }}</x-alert>
        @endif

        @if (!empty($prelaunchMode))
            <x-alert color="yellow">
                Caregiver pre-launch mode is active. Keep building your profile and we will notify you when applications open.
            </x-alert>
        @endif

        @forelse ($requests as $request)
            <x-card>
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-1">
                        <p class="font-semibold">{{ $request->title }}</p>
                        <p class="text-sm text-[#607080]">
                            {{ $request->city }}, {{ $request->state }}
                            @if ($request->request_type === \App\Models\CareRequest::TYPE_ONE_TIME)
                                - {{ optional($request->requested_start_at)->format('M d, Y H:i') }}
                            @else
                                - Recurring
                            @endif
                        </p>
                        <x-care-recipient-context :recipient="$request->recipient" :show-name="true" class="mt-2" />
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
                        <span class="inline-flex rounded-full bg-[#F0E9E1] px-3 py-1 text-xs text-[#4B5B6B]">{{ $task->name }}</span>
                    @endforeach
                </div>

                <div class="mt-4 flex items-center justify-between">
                    @php $existingApplication = $request->applications->first(); @endphp
                    @if ($existingApplication)
                        <p class="text-sm text-[#607080]">You already applied ({{ strtoupper($existingApplication->status) }})</p>
                    @else
                        <p class="text-sm text-[#607080]">No application sent yet</p>
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
                    <div class="mt-3 rounded-md border border-[#BDD4F7] bg-[#EEF5FF] px-3 py-2 text-xs text-[#0F3D3E]">
                        You were invited by this family. Respond in the Invitations page.
                    </div>
                @endif
            </x-card>
        @empty
            <x-card>
                <p class="text-sm text-[#607080]">
                    {{ !empty($prelaunchMode) ? 'Applications are paused during pre-launch. You will be notified when Raleigh goes live.' : 'No open requests match your current filters.' }}
                </p>
            </x-card>
        @endforelse

        <div>{{ $requests->links() }}</div>
    </div>
</div>

