<div class="hc-page py-8 space-y-6">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    @error('call')
        <x-alert color="red">{{ $message }}</x-alert>
    @enderror

    <x-card>
        <x-slot:header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold">Voice AI Test Calls</h1>
                    <p class="mt-1 text-sm text-slate-600">Start a Twilio voice call, answer basic questions, collect care details, and store the transcript.</p>
                </div>
                <div class="grid grid-cols-4 gap-2 text-center text-xs">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="font-semibold text-slate-900">{{ number_format($summary['total']) }}</p>
                        <p class="text-slate-500">Total</p>
                    </div>
                    <div class="rounded-xl border border-blue-200 bg-blue-50 px-3 py-2">
                        <p class="font-semibold text-blue-900">{{ number_format($summary['in_progress']) }}</p>
                        <p class="text-blue-700">Live</p>
                    </div>
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2">
                        <p class="font-semibold text-emerald-900">{{ number_format($summary['completed']) }}</p>
                        <p class="text-emerald-700">Done</p>
                    </div>
                    <div class="rounded-xl border border-red-200 bg-red-50 px-3 py-2">
                        <p class="font-semibold text-red-900">{{ number_format($summary['failed']) }}</p>
                        <p class="text-red-700">Needs check</p>
                    </div>
                </div>
            </div>
        </x-slot:header>

        <form wire:submit.prevent="startCall" class="grid grid-cols-1 gap-4 lg:grid-cols-12">
            <div class="lg:col-span-5">
                <x-input
                    label="Phone number to call"
                    placeholder="+19195551234"
                    wire:model="phoneNumber"
                />
                @error('phoneNumber')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="lg:col-span-4">
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    <p><span class="font-semibold text-slate-900">From:</span> {{ $voiceFrom ?: 'Not configured' }}</p>
                    <p class="mt-1 text-xs text-slate-500">
                        @if($twilioBypass)
                            Twilio bypass is on. Calls are simulated locally.
                        @else
                            Twilio will call the number and connect it to the Deepgram voice agent.
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex items-end lg:col-span-3">
                <x-button type="submit" color="blue" class="w-full justify-center">
                    Call
                </x-button>
            </div>

            <div class="lg:col-span-12 text-xs text-slate-500">
                Deepgram callback bridge:
                {{ $voiceAgentCallbackUrl !== '' ? $voiceAgentCallbackUrl : 'Configure TWILIO_VOICE_AGENT_CALLBACK_URL' }}
                <span class="mx-1">|</span>
                Twilio status callback: {{ url('/webhooks/twilio/voice/{call}/status') }}
            </div>
        </form>
    </x-card>

    <x-card>
        <x-slot:header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold">Voice Call Log</h2>
                    <p class="mt-1 text-sm text-slate-600">Latest 25 test calls with transcript and gathered intake fields.</p>
                </div>
                <x-button color="slate" light sm wire:click="$refresh" class="justify-center">Refresh</x-button>
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
                @endphp
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold text-slate-900">{{ $call->to_phone }}</p>
                                <x-badge :text="strtoupper((string) $call->status)" :color="$tone" />
                                @if($call->callback_requested)
                                    <x-badge text="CALLBACK" color="amber" />
                                @endif
                                @if($call->signup_link_requested)
                                    <x-badge text="SIGNUP LINK" color="cyan" />
                                @endif
                            </div>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ optional($call->created_at)->format('M d, Y H:i') }}
                                @if($call->admin)
                                    | {{ $call->admin->name }}
                                @endif
                                @if($call->twilio_call_sid)
                                    | {{ $call->twilio_call_sid }}
                                @endif
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-slate-500">From</p>
                                <p class="font-semibold text-slate-900">{{ $call->from_phone ?: '-' }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-slate-500">Duration</p>
                                <p class="font-semibold text-slate-900">{{ $call->duration_seconds !== null ? $call->duration_seconds.'s' : '-' }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-slate-500">Step</p>
                                <p class="font-semibold text-slate-900">{{ $call->current_step ?: '-' }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-slate-500">Twilio</p>
                                <p class="font-semibold text-slate-900">{{ $call->twilio_status ?: '-' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-3">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Gathered info</p>
                            <dl class="mt-2 space-y-1 text-slate-700">
                                <div><span class="font-semibold text-slate-900">Name:</span> {{ $call->gathered_name ?: '-' }}</div>
                                <div><span class="font-semibold text-slate-900">Relationship:</span> {{ $call->gathered_relationship ?: '-' }}</div>
                                <div><span class="font-semibold text-slate-900">Needs:</span> {{ $call->gathered_care_needs ?: '-' }}</div>
                                <div><span class="font-semibold text-slate-900">Location:</span> {{ $call->gathered_location ?: '-' }}</div>
                                <div><span class="font-semibold text-slate-900">Timing:</span> {{ $call->gathered_urgency ?: '-' }}</div>
                                <div><span class="font-semibold text-slate-900">Callback:</span> {{ $call->gathered_callback_time ?: ($call->callback_requested ? 'Requested' : '-') }}</div>
                            </dl>
                        </div>

                        <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Transcript</p>
                            @if($call->transcript_text)
                                <pre class="mt-2 max-h-64 overflow-auto whitespace-pre-wrap text-sm leading-6 text-slate-800">{{ $call->transcript_text }}</pre>
                            @else
                                <p class="mt-2 text-sm text-slate-500">No transcript yet. It will appear as Twilio sends speech callbacks.</p>
                            @endif
                            @if($call->summary)
                                <p class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">{{ $call->summary }}</p>
                            @endif
                            @if($call->last_error)
                                <p class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $call->last_error }}</p>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center">
                    <p class="font-semibold text-slate-900">No test calls yet.</p>
                    <p class="mt-1 text-sm text-slate-600">Enter a phone number above and press Call to start one.</p>
                </div>
            @endforelse
        </div>
    </x-card>
</div>
