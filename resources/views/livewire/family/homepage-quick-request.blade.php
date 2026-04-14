@php

    $stepLabels = [

        1 => 'Who needs help',

        2 => 'When and where',

        3 => 'Create account to publish',

    ];



    $selectedTaskNames = collect($taskOptions)

        ->keyBy('id')

        ->only(collect($selectedTasks)->map(fn ($id) => (int) $id)->all())

        ->pluck('name')

        ->values();

@endphp



<section id="quick-request" class="rounded-[1.75rem] border border-slate-200 bg-white p-4 shadow-[0_24px_60px_-28px_rgba(15,23,42,0.25)] sm:rounded-[2rem] sm:p-6">

    <div class="flex items-start justify-between gap-3">

        <div>

            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-blue-600">Quick request</p>

            <h2 class="mt-1 text-xl font-black tracking-tight text-slate-950 sm:text-2xl">Start your care request now.</h2>

            <p class="mt-2 max-w-xl text-sm font-medium leading-6 text-slate-500">

                Tell us who needs help, when support is needed, and where care happens. We save everything, then ask you to create an account before the request goes live.

            </p>

        </div>

        <div class="hidden rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-right sm:block">

            <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Step {{ $step }} of 3</p>

            <p class="mt-1 text-sm font-semibold text-slate-900">{{ $stepLabels[$step] ?? 'Quick request' }}</p>
        </div>

    </div>



    <div class="mt-4 hidden grid-cols-3 gap-2 sm:grid">

        @foreach ($stepLabels as $index => $label)

            <div class="rounded-2xl border px-3 py-3 text-center {{ $step >= $index ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-200 bg-slate-50 text-slate-500' }}">

                <p class="text-[11px] font-black uppercase tracking-[0.12em]">{{ $index }}</p>

                <p class="mt-1 text-xs font-semibold leading-4 sm:text-sm">{{ $label }}</p>

            </div>

        @endforeach

    </div>



    @if ($step === 1)

        <div class="mt-6 space-y-5">

            <div class="grid gap-4">

                <div>

                    <label for="recipient_name" class="text-sm font-bold text-slate-900">Who needs help?</label>

                    <input id="recipient_name" type="text" wire:model.live="recipient_name" placeholder="Example: My mom, Margaret Johnson"

                        class="mt-2 block min-h-12 w-full rounded-2xl border border-slate-200 px-4 text-base shadow-sm focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100">

                    @error('recipient_name') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

                </div>



                <div>

                    <p class="text-sm font-bold text-slate-900">What kind of help do they need?</p>

                    <div class="mt-3 flex flex-wrap gap-2">

                        @foreach ($taskOptions as $task)

                            @php $active = in_array((int) $task['id'], collect($selectedTasks)->map(fn($id)=>(int)$id)->all(), true); @endphp

                            <button

                                type="button"

                                wire:key="homepage-task-chip-{{ $task['id'] }}"

                                wire:click="toggleTask({{ (int) $task['id'] }})"

                                class="min-h-11 rounded-full border px-4 py-2 text-sm font-semibold transition {{ $active ? 'border-blue-600 bg-blue-600 text-white shadow-md' : 'border-slate-200 bg-white text-slate-700 hover:border-blue-300 hover:bg-blue-50' }}"

                            >

                                {{ $task['name'] }}

                            </button>

                        @endforeach

                    </div>

                    @error('selectedTasks') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

                </div>



                <div>

                    <label for="additional_info" class="text-sm font-bold text-slate-900">Anything important we should know? <span class="text-slate-400 font-medium">(optional)</span></label>

                    <textarea id="additional_info" rows="3" wire:model.live="additional_info" placeholder="Example: She mostly needs companionship, lunch prep, and someone around while I'm at work."

                        class="mt-2 block w-full rounded-2xl border border-slate-200 px-4 py-3 text-base shadow-sm focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100"></textarea>

                    @error('additional_info') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

                </div>

            </div>

        </div>

    @elseif ($step === 2)

        <div class="mt-6 space-y-5">

            <div class="grid gap-4 sm:grid-cols-2">

                <div>

                    <label for="requested_start_at" class="text-sm font-bold text-slate-900">Start</label>

                    <input id="requested_start_at" type="datetime-local" wire:model.live="requested_start_at"

                        class="mt-2 block min-h-12 w-full rounded-2xl border border-slate-200 px-4 text-base shadow-sm focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100">

                    @error('requested_start_at') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

                </div>

                <div>

                    <label for="requested_end_at" class="text-sm font-bold text-slate-900">End</label>

                    <input id="requested_end_at" type="datetime-local" wire:model.live="requested_end_at"

                        class="mt-2 block min-h-12 w-full rounded-2xl border border-slate-200 px-4 text-base shadow-sm focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100">

                    @error('requested_end_at') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

                </div>

            </div>



            <div class="grid gap-4">

                <div>

                    <label for="address_line1" class="text-sm font-bold text-slate-900">Care location</label>

                    <input id="address_line1" type="text" wire:model.live="address_line1" placeholder="Street address"

                        class="mt-2 block min-h-12 w-full rounded-2xl border border-slate-200 px-4 text-base shadow-sm focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100">

                    @error('address_line1') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

                </div>



                <div class="grid gap-4 sm:grid-cols-[1.1fr_0.9fr_0.7fr]">

                    <div>

                        <label for="city" class="text-sm font-bold text-slate-900">City</label>

                        <input id="city" type="text" wire:model.live="city"

                            class="mt-2 block min-h-12 w-full rounded-2xl border border-slate-200 px-4 text-base shadow-sm focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100">

                        @error('city') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

                    </div>

                    <div>

                        <label for="state" class="text-sm font-bold text-slate-900">State</label>

                        <select id="state" wire:model.live="state"

                            class="mt-2 block min-h-12 w-full rounded-2xl border border-slate-200 px-4 text-base shadow-sm focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100">

                            @foreach ($usStates as $value => $label)

                                <option value="{{ $value }}">{{ $label }}</option>

                            @endforeach

                        </select>

                        @error('state') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

                    </div>

                    <div>

                        <label for="zip" class="text-sm font-bold text-slate-900">ZIP</label>

                        <input id="zip" type="text" wire:model.live="zip"

                            class="mt-2 block min-h-12 w-full rounded-2xl border border-slate-200 px-4 text-base shadow-sm focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100">

                        @error('zip') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

                    </div>

                </div>

            </div>

        </div>

    @else

        <div class="mt-6 space-y-5">

            <div class="rounded-[1.5rem] border border-blue-200 bg-blue-50 p-5">

                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-blue-700">Before this request goes live</p>

                <h3 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Create your account to publish it.</h3>

                <p class="mt-2 text-sm leading-6 text-slate-600">

                    We've saved the request details below. Once you create your account, you'll land on a review screen and can publish with one final tap.

                </p>

            </div>



            <div class="grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">

                <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-5">

                    <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Request summary</p>

                    <dl class="mt-4 space-y-4 text-sm text-slate-700">

                        <div>

                            <dt class="font-bold text-slate-900">Recipient</dt>

                            <dd class="mt-1">{{ $recipient_name }}</dd>

                        </div>

                        <div>

                            <dt class="font-bold text-slate-900">Services</dt>

                            <dd class="mt-1">{{ $selectedTaskNames->implode(', ') }}</dd>

                        </div>

                        <div>

                            <dt class="font-bold text-slate-900">Schedule</dt>

                            <dd class="mt-1">

                                {{ \Illuminate\Support\Carbon::parse($requested_start_at)->format('M j, g:i A') }}

                                to

                                {{ \Illuminate\Support\Carbon::parse($requested_end_at)->format('g:i A') }}

                            </dd>

                        </div>

                        <div>

                            <dt class="font-bold text-slate-900">Location</dt>

                            <dd class="mt-1">{{ $address_line1 }}, {{ $city }}, {{ $state }} {{ $zip }}</dd>

                        </div>

                        @if (filled($additional_info))

                            <div>

                                <dt class="font-bold text-slate-900">Notes</dt>

                                <dd class="mt-1 leading-6">{{ $additional_info }}</dd>

                            </div>

                        @endif

                    </dl>

                </div>



                <div class="rounded-[1.5rem] border border-emerald-200 bg-emerald-50 p-5">

                    <p class="text-[11px] font-black uppercase tracking-[0.16em] text-emerald-700">Estimated shift cost</p>

                    @if ($this->estimatedHours !== null && $this->estimatedCost !== null)

                        <p class="mt-3 text-3xl font-black tracking-tight text-slate-950">${{ number_format($this->estimatedCost, 2) }}</p>

                        <p class="mt-2 text-sm font-medium text-emerald-900">

                            {{ number_format($this->estimatedHours, 2) }} hours at $30/hr

                        </p>

                    @endif

                    <p class="mt-4 text-sm leading-6 text-slate-600">

                        This is an estimate based on your requested time window. Final total is based on confirmed worked time.

                    </p>

                </div>

            </div>

        </div>

    @endif



    <div class="mt-6 rounded-[1.5rem] border border-slate-200 bg-white/90 p-3 shadow-sm sm:p-4">

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">

            <button

                type="button"

                wire:click="previousStep"

                @disabled($step === 1)

                class="inline-flex min-h-12 items-center justify-center rounded-2xl border border-slate-200 px-5 text-sm font-bold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"

            >

                Back

            </button>



            @if ($step < 3)

                <button

                    type="button"

                    wire:click="nextStep"

                    class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-slate-950 px-6 text-sm font-bold text-white shadow-lg shadow-slate-900/20 transition hover:bg-blue-600"

                >

                    Continue

                </button>

            @else

                <div class="grid w-full gap-3 sm:w-auto sm:min-w-[280px]">

                    <button

                        type="button"

                        wire:click="startAccountHandoff"

                        class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-blue-600 px-6 text-sm font-bold text-white shadow-lg shadow-blue-600/20 transition hover:bg-blue-700"

                    >

                        {{ auth()->check() && auth()->user()?->role === 'family' ? 'Review before publish' : 'Create account to publish' }}

                    </button>

                    @guest

                        <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center justify-center rounded-2xl border border-slate-200 px-5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">

                            Already have an account? Sign in

                        </a>

                    @endguest

                </div>

            @endif

        </div>

    </div>

</section>

