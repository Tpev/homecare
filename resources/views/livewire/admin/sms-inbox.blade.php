<div class="hc-page py-8 space-y-6">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    @error('send')
        <x-alert color="red">{{ $message }}</x-alert>
    @enderror

    <x-card>
        <x-slot:header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold">Text Messages</h1>
                    <p class="mt-1 text-sm text-slate-600">Read inbound SMS and send replies from the admin console.</p>
                </div>
                <div class="grid grid-cols-3 gap-2 text-center text-xs">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="font-semibold text-slate-900">{{ number_format($summary['incoming']) }}</p>
                        <p class="text-slate-500">Received</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="font-semibold text-slate-900">{{ number_format($summary['outgoing']) }}</p>
                        <p class="text-slate-500">Sent</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="font-semibold text-slate-900">{{ number_format($summary['failed']) }}</p>
                        <p class="text-slate-500">Failed</p>
                    </div>
                </div>
            </div>
        </x-slot:header>

        <form wire:submit.prevent="sendMessage" class="grid grid-cols-1 gap-4 lg:grid-cols-12">
            <div class="lg:col-span-3">
                <x-input
                    label="To"
                    placeholder="+19195551234"
                    wire:model="toPhone"
                />
                @error('toPhone')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="lg:col-span-3">
                <x-input
                    label="From"
                    placeholder="+19195550000"
                    wire:model="fromPhone"
                />
                @error('fromPhone')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="lg:col-span-6">
                <x-textarea
                    label="Message"
                    placeholder="Type the text message..."
                    wire:model="messageBody"
                    rows="3"
                />
                @error('messageBody')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="lg:col-span-12 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-slate-500">Messaging webhook: {{ url('/webhooks/twilio/sms') }}</p>
                <x-button type="submit" color="blue" class="justify-center">
                    Send text message
                </x-button>
            </div>
        </form>
    </x-card>

    <x-card>
        <x-slot:header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold">Message Log</h2>
                    <p class="mt-1 text-sm text-slate-600">Inbound and admin-sent SMS across the Twilio number.</p>
                </div>
                <x-button color="slate" light sm wire:click="$refresh" class="justify-center">Refresh</x-button>
            </div>
        </x-slot:header>

        <div class="grid grid-cols-1 gap-3 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <x-input
                    label="Search"
                    placeholder="Phone, body, or Twilio SID"
                    wire:model.live.debounce.300ms="q"
                />
            </div>
            <x-select.styled label="Direction" wire:model.live="direction" :options="$directionOptions" />
            <x-select.styled
                label="Rows"
                wire:model.live="perPage"
                :options="[
                    ['label' => '25', 'value' => 25],
                    ['label' => '50', 'value' => 50],
                    ['label' => '100', 'value' => 100],
                ]"
            />
        </div>

        <div class="mt-5 space-y-3 md:hidden">
            @forelse($messages as $sms)
                @php
                    $isIncoming = $sms->direction === \App\Models\SmsMessage::DIRECTION_INCOMING;
                    $tone = in_array($sms->status, [\App\Models\SmsMessage::STATUS_FAILED, \App\Models\SmsMessage::STATUS_UNDELIVERED], true)
                        ? 'red'
                        : ($isIncoming ? 'blue' : 'green');
                @endphp
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-900">{{ $isIncoming ? $sms->from_phone : $sms->to_phone }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ optional($sms->created_at)->format('M d, Y H:i') }}
                            </p>
                        </div>
                        <x-badge :text="strtoupper((string) $sms->direction)" :color="$tone" />
                    </div>

                        <p class="mt-3 whitespace-pre-wrap text-sm text-slate-700">{{ $sms->body }}</p>

                        <div class="mt-4 flex flex-col gap-2">
                            @if($isIncoming)
                            <x-button color="blue" light class="w-full justify-center" wire:click="replyTo(@js($sms->from_phone))">Reply</x-button>
                        @endif
                        <p class="text-xs text-slate-500">
                            {{ $sms->from_phone }} to {{ $sms->to_phone }}
                            @if($sms->twilio_sid)
                                | {{ $sms->twilio_sid }}
                            @endif
                            @if($sms->error_code)
                                | Error {{ $sms->error_code }}
                            @endif
                        </p>
                        @if($sms->error_message)
                            <p class="text-xs text-red-600">{{ $sms->error_message }}</p>
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                    No text messages found.
                </div>
            @endforelse
        </div>

        <div class="mt-4 hidden overflow-x-auto rounded-xl border border-slate-200 md:block">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Direction</th>
                        <th class="px-4 py-3">From / To</th>
                        <th class="px-4 py-3">Message</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($messages as $sms)
                        @php
                            $isIncoming = $sms->direction === \App\Models\SmsMessage::DIRECTION_INCOMING;
                            $tone = in_array($sms->status, [\App\Models\SmsMessage::STATUS_FAILED, \App\Models\SmsMessage::STATUS_UNDELIVERED], true)
                                ? 'red'
                                : ($isIncoming ? 'blue' : 'green');
                        @endphp
                        <tr>
                            <td class="px-4 py-3 align-top text-slate-700">
                                {{ optional($sms->created_at)->format('M d, Y H:i') }}
                            </td>
                            <td class="px-4 py-3 align-top">
                                <x-badge :text="strtoupper((string) $sms->direction)" :color="$tone" />
                            </td>
                            <td class="px-4 py-3 align-top text-slate-700">
                                <p><span class="text-slate-500">From:</span> {{ $sms->from_phone ?: '-' }}</p>
                                <p><span class="text-slate-500">To:</span> {{ $sms->to_phone ?: '-' }}</p>
                                @if($sms->sentBy)
                                    <p class="mt-1 text-xs text-slate-500">Admin: {{ $sms->sentBy->name }}</p>
                                @endif
                            </td>
                            <td class="max-w-xl px-4 py-3 align-top">
                                <p class="whitespace-pre-wrap text-slate-900">{{ $sms->body }}</p>
                                @if($sms->twilio_sid)
                                    <p class="mt-2 text-xs text-slate-500">{{ $sms->twilio_sid }}</p>
                                @endif
                                @if($sms->error_message)
                                    <p class="mt-2 text-xs text-red-600">{{ $sms->error_message }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-top text-slate-700">
                                {{ strtoupper((string) $sms->status) }}
                                @if($sms->error_code)
                                    <p class="mt-1 text-xs text-red-600">Error {{ $sms->error_code }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-top text-right">
                                @if($isIncoming)
                                    <x-button color="blue" light sm wire:click="replyTo(@js($sms->from_phone))">Reply</x-button>
                                @else
                                    <span class="text-xs text-slate-500">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-500">No text messages found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:footer>
            <div class="pt-2">
                {{ $messages->links() }}
            </div>
        </x-slot:footer>
    </x-card>
</div>
