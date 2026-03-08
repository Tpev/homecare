<div class="hc-page py-8">
    <div class="mb-6 overflow-hidden rounded-3xl bg-gradient-to-r from-cyan-700 via-blue-700 to-emerald-600 p-6 text-white shadow-xl">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-white/80">AI Care Request Copilot</div>
                <h1 class="mt-2 text-3xl font-display font-semibold leading-tight">Describe your care needs naturally. I will structure everything for you.</h1>
                <p class="mt-2 text-sm text-white/85">Non-medical care only. You confirm the final request before publish.</p>
            </div>
            <div class="min-w-[220px] rounded-2xl bg-white/15 p-4 backdrop-blur">
                <p class="text-xs font-medium uppercase tracking-wide text-white/80">Quality score</p>
                <p class="mt-1 text-3xl font-bold">{{ $qualityScore }}%</p>
                <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-white/30">
                    <div class="h-full rounded-full bg-white transition-all" style="width: {{ max(4, min(100, $qualityScore)) }}%"></div>
                </div>
                <p class="mt-2 text-xs text-white/85">{{ $status === \App\Models\AiRequestSession::STATUS_READY_FOR_REVIEW ? 'Ready for review' : 'Still capturing details' }}</p>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-12">
        <section class="lg:col-span-7">
            <x-card class="overflow-hidden">
                <x-slot:header>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-display font-semibold text-slate-900">Conversation</p>
                            <p class="text-xs text-slate-500">One question at a time. Clear and fast.</p>
                        </div>
                        <x-badge text="{{ strtoupper($status) }}" color="blue" />
                    </div>
                </x-slot:header>

                <div class="space-y-3 max-h-[560px] overflow-y-auto pr-1">
                    @foreach($messages as $message)
                        <div class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-[88%] rounded-2xl px-4 py-3 shadow-sm {{ $message['role'] === 'user' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-800' }}">
                                <div class="text-xs font-semibold uppercase tracking-wide {{ $message['role'] === 'user' ? 'text-blue-100' : 'text-slate-500' }}">
                                    {{ $message['role'] === 'user' ? 'You' : 'Copilot' }}
                                </div>
                                <p class="mt-1 text-sm leading-relaxed">{{ $message['content'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($quickReplies !== [])
                    <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                        @foreach($quickReplies as $reply)
                            <x-button color="slate" sm light wire:click="useQuickReply({{ \Illuminate\Support\Js::from($reply) }})">{{ $reply }}</x-button>
                        @endforeach
                    </div>
                @endif

                @if($qualityHints !== [])
                    <div class="mt-4 space-y-1 rounded-xl bg-amber-50 p-3 text-xs text-amber-800 ring-1 ring-amber-200">
                        @foreach($qualityHints as $hint)
                            <p>• {{ $hint }}</p>
                        @endforeach
                    </div>
                @endif

                <form wire:submit="send" class="mt-4 border-t border-slate-100 pt-4">
                    <div class="flex items-end gap-3">
                        <div class="flex-1">
                            <x-textarea
                                wire:model="input"
                                rows="3"
                                placeholder="Example: I need someone for my mom in Raleigh tomorrow from 3pm to 7pm for companionship and meal prep."
                            />
                        </div>
                        <x-button color="blue" type="submit" loading="send" icon="paper-airplane" position="right">
                            {{ $isProcessing ? 'Thinking...' : 'Send' }}
                        </x-button>
                    </div>
                </form>
            </x-card>
        </section>

        <aside class="lg:col-span-5 space-y-4">
            <x-card>
                <x-slot:header>
                    <div>
                        <p class="font-display font-semibold text-slate-900">Live Request Draft</p>
                        <p class="text-xs text-slate-500">Edit any field directly, then sync.</p>
                    </div>
                </x-slot:header>

                @php
                    $labels = [
                        'request_type' => 'Request type',
                        'title' => 'Title',
                        'additional_info' => 'Additional info',
                        'scope_of_work' => 'Scope of work',
                        'time_expectations' => 'Time expectations',
                        'home_access_notes' => 'Home access notes',
                        'address_line1' => 'Address',
                        'city' => 'City',
                        'state' => 'State',
                        'zip' => 'ZIP',
                        'task_ids' => 'Services',
                        'requested_start_at' => 'Start date/time',
                        'requested_end_at' => 'End date/time',
                        'recurring_days' => 'Recurring days',
                        'recurring_start_time' => 'Recurring start time',
                        'recurring_end_time' => 'Recurring end time',
                        'recurring_starts_on' => 'Recurring start date',
                        'recipient.full_name' => 'Recipient name',
                        'recipient.relationship_to_family' => 'Relationship to recipient',
                        'recipient.care_notes' => 'Recipient care notes',
                        'third_party_contact.full_name' => 'Third-party name',
                        'third_party_contact.relationship_to_recipient' => 'Third-party relationship',
                        'third_party_contact.phone' => 'Third-party phone',
                    ];
                @endphp

                @if($missingRequired !== [])
                    <div class="mb-4 rounded-xl bg-rose-50 p-3 text-xs text-rose-700 ring-1 ring-rose-200">
                        <p class="font-semibold">Missing required fields:</p>
                        <div class="mt-1 flex flex-wrap gap-2">
                            @foreach($missingRequired as $field)
                                <span class="rounded-full bg-white px-2 py-1 ring-1 ring-rose-200">{{ $labels[$field] ?? $field }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="space-y-3">
                    <x-select.styled
                        label="Request type"
                        wire:model.live="draft.request_type"
                        :options="[
                            ['label' => 'One-time', 'value' => \App\Models\CareRequest::TYPE_ONE_TIME],
                            ['label' => 'Recurring', 'value' => \App\Models\CareRequest::TYPE_RECURRING],
                        ]"
                    />

                    <x-input label="Title" wire:model.live="draft.title" />
                    <x-textarea label="Scope of work" rows="2" wire:model.live="draft.scope_of_work" />
                    <x-textarea label="Additional info" rows="2" wire:model.live="draft.additional_info" />
                    <x-textarea label="Time expectations" rows="2" wire:model.live="draft.time_expectations" />
                    <x-textarea label="Home access notes" rows="2" wire:model.live="draft.home_access_notes" />

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <x-input label="Address line 1" wire:model.live="draft.address_line1" />
                        <x-input label="Address line 2 (optional)" wire:model.live="draft.address_line2" />
                        <x-input label="City" wire:model.live="draft.city" />
                        <x-select.styled
                            label="State"
                            wire:model.live="draft.state"
                            :options="collect($usStates)->map(fn($label,$value)=>['label'=>$label,'value'=>$value])->values()->all()"
                        />
                        <x-input label="ZIP" wire:model.live="draft.zip" />
                        <x-input label="Preferred response (hours)" type="number" min="1" max="72" wire:model.live="draft.preferred_response_hours" />
                    </div>

                    <x-select.styled
                        label="Services needed"
                        wire:model.live="draft.task_ids"
                        multiple
                        :options="collect($taskOptions)->map(fn($task)=>['label'=>$task['name'],'value'=>(int) $task['id']])->values()->all()"
                    />

                    @if(($draft['request_type'] ?? null) === \App\Models\CareRequest::TYPE_ONE_TIME)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <x-input label="Start" type="datetime-local" wire:model.live="draft.requested_start_at" />
                            <x-input label="End" type="datetime-local" wire:model.live="draft.requested_end_at" />
                        </div>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <x-select.styled
                                label="Recurring days"
                                multiple
                                wire:model.live="draft.recurring_days"
                                :options="[
                                    ['label' => 'Sun', 'value' => 0], ['label' => 'Mon', 'value' => 1],
                                    ['label' => 'Tue', 'value' => 2], ['label' => 'Wed', 'value' => 3],
                                    ['label' => 'Thu', 'value' => 4], ['label' => 'Fri', 'value' => 5],
                                    ['label' => 'Sat', 'value' => 6],
                                ]"
                            />
                            <x-input label="Starts on" type="date" wire:model.live="draft.recurring_starts_on" />
                            <x-input label="Start time" type="time" wire:model.live="draft.recurring_start_time" />
                            <x-input label="End time" type="time" wire:model.live="draft.recurring_end_time" />
                        </div>
                    @endif

                    <x-input label="Recipient full name" wire:model.live="draft.recipient.full_name" />
                    <x-input label="Relationship to recipient" wire:model.live="draft.recipient.relationship_to_family" />
                    <x-textarea label="Recipient care notes" rows="2" wire:model.live="draft.recipient.care_notes" />
                </div>

                <div class="mt-5 flex flex-wrap gap-2">
                    <x-button color="slate" light wire:click="syncDraft">Sync Draft</x-button>
                    <x-button color="blue" wire:click="publish" :disabled="$status !== \App\Models\AiRequestSession::STATUS_READY_FOR_REVIEW">
                        Publish Request
                    </x-button>
                    <x-button color="slate" outline wire:click="fallbackToManual">Use manual form</x-button>
                </div>

                @error('publish')
                    <p class="mt-2 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </x-card>
        </aside>
    </div>
</div>
