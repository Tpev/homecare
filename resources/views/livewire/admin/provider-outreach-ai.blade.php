<div class="hc-page py-8 space-y-6">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    @error('call')
        <x-alert color="red">{{ $message }}</x-alert>
    @enderror

    <x-card>
        <x-slot:header>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Provider relations</p>
                    <h1 class="mt-1 text-2xl font-semibold text-slate-950">Julie AI provider outreach</h1>
                    <p class="mt-2 max-w-3xl text-sm text-slate-600">
                        Call social workers, discharge planners, care managers, senior centers, and office managers with a soft resource-first script.
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-2 text-center text-xs sm:grid-cols-4">
                    <div class="rounded-xl border border-blue-200 bg-blue-50 px-3 py-2">
                        <p class="font-semibold text-blue-900">{{ number_format($summary['queued']) }}</p>
                        <p class="text-blue-700">Queued/live</p>
                    </div>
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2">
                        <p class="font-semibold text-emerald-900">{{ number_format($summary['completed']) }}</p>
                        <p class="text-emerald-700">Completed</p>
                    </div>
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2">
                        <p class="font-semibold text-amber-900">{{ number_format($summary['resource_requested']) }}</p>
                        <p class="text-amber-700">Resource</p>
                    </div>
                    <div class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2">
                        <p class="font-semibold text-rose-900">{{ number_format($summary['do_not_call']) }}</p>
                        <p class="text-rose-700">Do not call</p>
                    </div>
                </div>
            </div>
        </x-slot:header>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(22rem,0.8fr)]">
            <form wire:submit.prevent="startCall" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-input label="Practice / organization" placeholder="Triangle Primary Care" wire:model="targetForm.practice_name" />
                    <x-input label="Person to ask for" placeholder="Office manager, social worker, or name" wire:model="targetForm.contact_name" />
                    <x-select.styled label="Role" wire:model="targetForm.contact_role" :options="$roleOptions" />
                    <x-input label="Phone number" placeholder="+19195551234" wire:model="targetForm.phone" />
                    <x-input type="email" label="Email if known" placeholder="office@example.com" wire:model="targetForm.email" />
                    <x-input label="Fax if known" placeholder="919-555-0100" wire:model="targetForm.fax" />
                    <div class="md:col-span-2">
                        <x-input label="Location" placeholder="Raleigh, NC" wire:model="targetForm.location" />
                    </div>
                    <div class="md:col-span-2">
                        <x-textarea label="Internal context for Julie" placeholder="Example: Ask for discharge planning or care coordination. Mention Wake County if helpful." wire:model="targetForm.notes" />
                    </div>
                </div>

                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                    <p class="font-semibold">Guardrails Julie will follow</p>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        <p>No referral fees, gifts, commissions, or exclusivity.</p>
                        <p>No patient names, health details, or protected information.</p>
                        <p>Immediate do-not-call handling if requested.</p>
                        <p>Position LoLo as non-medical support, not clinical care.</p>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-xs text-slate-500">
                        <p><span class="font-semibold text-slate-800">From:</span> {{ $voiceFrom ?: 'Not configured' }}</p>
                        <p>
                            @if($twilioBypass)
                                Twilio bypass is on, so the call will be simulated.
                            @else
                                Twilio connects this call to the existing Deepgram voice bridge.
                            @endif
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <x-button type="button" color="slate" light wire:click="resetTargetForm">Clear</x-button>
                        <x-button type="submit" color="green">Start Julie call</x-button>
                    </div>
                </div>

                <p class="text-xs text-slate-500">
                    Voice bridge:
                    {{ $voiceAgentCallbackUrl !== '' ? $voiceAgentCallbackUrl : 'Configure TWILIO_VOICE_AGENT_CALLBACK_URL' }}
                </p>
            </form>

            <div class="space-y-4">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Julie opening</p>
                    <div class="mt-2 space-y-2 text-sm leading-6 text-slate-800">
                        <p>1. Hi, this is Julie calling from LoLo Care. Did I catch you at an okay time for a quick question?</p>
                        <p>2. If they give room: Thank you. We are a new home care service based in Raleigh. We help older adults and families arrange non-medical support at home, like companionship, errands, rides, meal prep, respite, light household help, and check-ins.</p>
                        <p>3. I am trying to understand the best way to make our services available as an option for your patients, residents, or families when they ask about help at home. Who would be the right person to speak with about that?</p>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-950">Recent referral sources</p>
                            <p class="text-xs text-slate-500">Pick one to refill the call form.</p>
                        </div>
                        <a href="{{ route('admin.crm.index', ['pipeline' => \App\Models\Lead::TYPE_REFERRAL]) }}" wire:navigate class="text-xs font-semibold text-blue-700 hover:underline">Open CRM</a>
                    </div>
                    <div class="mt-3 space-y-2">
                        @forelse($referralLeads as $lead)
                            <button type="button" wire:click="fillFromLead({{ $lead->id }})" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-left transition hover:border-blue-200 hover:bg-blue-50">
                                <span class="block text-sm font-semibold text-slate-900">{{ $lead->company ?: $lead->name }}</span>
                                <span class="block text-xs text-slate-500">{{ $lead->name }}{{ $lead->phone ? ' | '.$lead->phone : '' }}</span>
                            </button>
                        @empty
                            <p class="rounded-xl border border-dashed border-slate-300 px-3 py-4 text-sm text-slate-500">No referral sources yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </x-card>

    <x-card>
        <x-slot:header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold">Provider outreach call log</h2>
                    <p class="mt-1 text-sm text-slate-600">Latest Julie calls, outcomes, transcripts, and local audio recordings.</p>
                </div>
                <x-button color="slate" light sm wire:click="$refresh">Refresh</x-button>
            </div>
        </x-slot:header>

        <div class="space-y-4">
            @forelse($calls as $call)
                @php
                    $tone = match ($call->status) {
                        \App\Models\VoiceAiCall::STATUS_COMPLETED => 'green',
                        \App\Models\VoiceAiCall::STATUS_IN_PROGRESS, \App\Models\VoiceAiCall::STATUS_RINGING, \App\Models\VoiceAiCall::STATUS_QUEUED => 'blue',
                        \App\Models\VoiceAiCall::STATUS_FAILED, \App\Models\VoiceAiCall::STATUS_BUSY, \App\Models\VoiceAiCall::STATUS_NO_ANSWER, \App\Models\VoiceAiCall::STATUS_CANCELLED => 'red',
                        default => 'slate',
                    };
                    $recordingUrl = data_get($call->metadata, 'recording_url');
                    $recordingMimeType = data_get($call->metadata, 'recording_mime_type', 'audio/wav');
                    $recordingError = data_get($call->metadata, 'recording_error');
                    $targetOrg = data_get($call->metadata, 'target_organization') ?: $call->gathered_name ?: $call->to_phone;
                    $outcome = data_get($call->metadata, 'provider_outreach.outcome');
                @endphp
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold text-slate-900">{{ $targetOrg }}</p>
                                <x-badge :text="strtoupper((string) $call->status)" :color="$tone" />
                                @if($outcome)
                                    <x-badge :text="str($outcome)->replace('_', ' ')->title()" color="cyan" />
                                @endif
                            </div>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ optional($call->created_at)->format('M d, Y H:i') }}
                                | {{ $call->to_phone }}
                                @if($call->admin)
                                    | {{ $call->admin->name }}
                                @endif
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-slate-500">Contact</p>
                                <p class="font-semibold text-slate-900">{{ data_get($call->metadata, 'target_name') ?: '-' }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-slate-500">Role</p>
                                <p class="font-semibold text-slate-900">{{ data_get($call->metadata, 'target_role') ?: '-' }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-slate-500">Duration</p>
                                <p class="font-semibold text-slate-900">{{ $call->duration_seconds !== null ? $call->duration_seconds.'s' : '-' }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-slate-500">Twilio</p>
                                <p class="font-semibold text-slate-900">{{ $call->twilio_status ?: '-' }}</p>
                            </div>
                        </div>
                    </div>

                    @if($call->summary)
                        <p class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">{{ $call->summary }}</p>
                    @endif

                    <div class="mt-4 grid gap-3 lg:grid-cols-3">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Outcome</p>
                            <dl class="mt-2 space-y-1 text-slate-700">
                                <div><span class="font-semibold text-slate-900">Resource:</span> {{ data_get($call->metadata, 'provider_outreach.resource_requested') ? 'Requested' : '-' }}</div>
                                <div><span class="font-semibold text-slate-900">Email:</span> {{ data_get($call->metadata, 'provider_outreach.email') ?: data_get($call->metadata, 'target_email') ?: '-' }}</div>
                                <div><span class="font-semibold text-slate-900">Fax:</span> {{ data_get($call->metadata, 'provider_outreach.fax') ?: data_get($call->metadata, 'target_fax') ?: '-' }}</div>
                                <div><span class="font-semibold text-slate-900">Follow-up:</span> {{ data_get($call->metadata, 'provider_outreach.best_follow_up') ?: '-' }}</div>
                                <div><span class="font-semibold text-slate-900">Detection:</span>
                                    @if(data_get($call->metadata, 'provider_outreach.voicemail_detected')) voicemail
                                    @elseif(data_get($call->metadata, 'provider_outreach.ivr_detected')) IVR
                                    @elseif(data_get($call->metadata, 'provider_outreach.ai_detected')) AI
                                    @else person/unknown
                                    @endif
                                </div>
                            </dl>
                        </div>

                        <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Transcript and recording</p>
                            @if($recordingUrl)
                                <div class="mt-2 rounded-lg border border-blue-200 bg-white px-3 py-2">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <p class="text-sm font-semibold text-slate-900">Local audio recording</p>
                                        <a href="{{ $recordingUrl }}" target="_blank" rel="noopener" class="text-sm font-semibold text-blue-700 hover:underline">Open WAV</a>
                                    </div>
                                    <audio controls preload="none" class="mt-2 w-full">
                                        <source src="{{ $recordingUrl }}" type="{{ $recordingMimeType }}">
                                    </audio>
                                </div>
                            @elseif($recordingError)
                                <p class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                                    Recording issue: {{ $recordingError }}
                                </p>
                            @endif

                            @if($call->transcript_text)
                                <pre class="mt-2 max-h-64 overflow-auto whitespace-pre-wrap text-sm leading-6 text-slate-800">{{ $call->transcript_text }}</pre>
                            @else
                                <p class="mt-2 text-sm text-slate-500">No transcript yet.</p>
                            @endif
                            @if($call->last_error)
                                <p class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $call->last_error }}</p>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center">
                    <p class="font-semibold text-slate-900">No Julie outreach calls yet.</p>
                    <p class="mt-1 text-sm text-slate-600">Enter a provider target above to queue the first call.</p>
                </div>
            @endforelse
        </div>
    </x-card>
</div>
