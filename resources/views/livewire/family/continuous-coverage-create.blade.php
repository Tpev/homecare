<div class="hc-page space-y-6 py-6 sm:py-8">
    <header>
        <a href="{{ route('family.continuous-coverage.index') }}" wire:navigate class="hc-link">Back to Continuous Coverage</a>
        <p class="mt-5 text-sm font-semibold uppercase tracking-[0.16em] text-[#2F6F62]">New coverage plan</p>
        <h1 class="mt-1 font-display text-3xl font-semibold text-[#17313F] sm:text-4xl">Plan continuous care step by step.</h1>
        <p class="mt-2 max-w-3xl text-base text-[#607080]">Review the full schedule before creating it. Afterward, you approve the care team and each caregiver chooses what to accept.</p>
    </header>

    <nav aria-label="Coverage setup progress" class="grid grid-cols-4 overflow-hidden rounded-2xl border border-[#DED6CA] bg-white">
        @foreach([1 => 'Who & where', 2 => 'Schedule', 3 => 'Care details', 4 => 'Review'] as $number => $label)
            <div class="border-r border-[#E7E0D8] px-2 py-3 text-center last:border-r-0 {{ $step === $number ? 'bg-[#0F3D3E] text-white' : ($step > $number ? 'bg-[#EAF6F2] text-[#24574E]' : 'text-[#7B8794]') }}" @if($step === $number) aria-current="step" @endif><span class="block text-xs font-semibold uppercase">Step {{ $number }}</span><span class="mt-1 hidden text-sm font-semibold sm:block">{{ $label }}</span></div>
        @endforeach
    </nav>

    <form wire:submit="save" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="space-y-6">
            @if($step === 1)
            <section class="rounded-3xl border border-[#DED6CA] bg-white p-5 sm:p-6">
                <h2 class="font-display text-2xl font-semibold text-[#17313F]">1. Who and where</h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <label class="sm:col-span-2"><span class="text-sm font-semibold text-[#324457]">Plan name</span><input wire:model="title" class="mt-1 min-h-12 w-full rounded-xl border-[#CFC4B5]" placeholder="Example: Mom’s around-the-clock care">@error('title')<span class="mt-1 block text-sm text-rose-700">{{ $message }}</span>@enderror</label>
                    <label><span class="text-sm font-semibold text-[#324457]">Care recipient</span><input wire:model="recipientName" class="mt-1 min-h-12 w-full rounded-xl border-[#CFC4B5]" autocomplete="name">@error('recipientName')<span class="text-sm text-rose-700">{{ $message }}</span>@enderror</label>
                    <label><span class="text-sm font-semibold text-[#324457]">Relationship</span><input wire:model="relationshipToFamily" class="mt-1 min-h-12 w-full rounded-xl border-[#CFC4B5]" placeholder="Loved one"></label>
                    <label class="sm:col-span-2"><span class="text-sm font-semibold text-[#324457]">Street address</span><input wire:model="addressLine1" class="mt-1 min-h-12 w-full rounded-xl border-[#CFC4B5]" autocomplete="street-address">@error('addressLine1')<span class="text-sm text-rose-700">{{ $message }}</span>@enderror</label>
                    <label class="sm:col-span-2"><span class="text-sm font-semibold text-[#324457]">Apartment or unit <span class="font-normal text-[#7B8794]">(optional)</span></span><input wire:model="addressLine2" class="mt-1 min-h-12 w-full rounded-xl border-[#CFC4B5]"></label>
                    <label><span class="text-sm font-semibold text-[#324457]">City</span><input wire:model="city" class="mt-1 min-h-12 w-full rounded-xl border-[#CFC4B5]" autocomplete="address-level2"></label>
                    <div class="grid grid-cols-2 gap-3">
                        <label><span class="text-sm font-semibold text-[#324457]">State</span><input wire:model="state" maxlength="2" class="mt-1 min-h-12 w-full rounded-xl border-[#CFC4B5] uppercase" autocomplete="address-level1"></label>
                        <label><span class="text-sm font-semibold text-[#324457]">ZIP</span><input wire:model="zip" class="mt-1 min-h-12 w-full rounded-xl border-[#CFC4B5]" autocomplete="postal-code"></label>
                    </div>
                </div>
            </section>
            @endif

            @if($step === 2)
            <section class="rounded-3xl border border-[#DED6CA] bg-white p-5 sm:p-6">
                <h2 class="font-display text-2xl font-semibold text-[#17313F]">2. Coverage schedule</h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <label><span class="text-sm font-semibold text-[#324457]">Starts</span><input type="date" wire:model.change="startsOn" class="mt-1 min-h-12 w-full rounded-xl border-[#CFC4B5]">@error('startsOn')<span class="text-sm text-rose-700">{{ $message }}</span>@enderror</label>
                    <label><span class="text-sm font-semibold text-[#324457]">Ends <span class="font-normal text-[#7B8794]">(optional)</span></span><input type="date" wire:model="endsOn" class="mt-1 min-h-12 w-full rounded-xl border-[#CFC4B5]"></label>
                    <label class="sm:col-span-2"><span class="text-sm font-semibold text-[#324457]">Timezone</span><select wire:model="timezone" class="mt-1 min-h-12 w-full rounded-xl border-[#CFC4B5]"><option value="America/New_York">Eastern Time</option><option value="America/Chicago">Central Time</option><option value="America/Denver">Mountain Time</option><option value="America/Los_Angeles">Pacific Time</option></select></label>
                </div>

                <fieldset class="mt-5">
                    <legend class="text-sm font-semibold text-[#324457]">Coverage needed</legend>
                    <div class="mt-2 grid gap-3 sm:grid-cols-3">
                        @foreach ([['24_7','24/7','Every hour, every day'],['overnight','Overnight','A daily overnight window'],['custom','Custom','Choose each weekly window']] as [$value,$label,$help])
                            <label class="cursor-pointer rounded-2xl border p-4 {{ $coveragePattern === $value ? 'border-[#2F6F62] bg-[#EAF6F2]' : 'border-[#DED6CA] bg-white' }}">
                                <input type="radio" wire:model.live="coveragePattern" value="{{ $value }}" class="text-[#0F3D3E]">
                                <span class="ml-2 font-semibold text-[#17313F]">{{ $label }}</span>
                                <span class="mt-1 block text-sm text-[#607080]">{{ $help }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                @if ($coveragePattern === '24_7')
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <label><span class="text-sm font-semibold text-[#324457]">Shift length</span><select wire:model.live="shiftLengthChoice" class="mt-1 min-h-12 w-full rounded-xl border-[#CFC4B5]"><option value="720">12 hours · 2 shifts/day</option><option value="480">8 hours · 3 shifts/day</option><option value="360">6 hours · 4 shifts/day</option><option value="custom">Custom shift length</option></select>@error('shiftLengthChoice')<span class="mt-1 block text-sm text-rose-700">{{ $message }}</span>@enderror</label>
                        <label><span class="text-sm font-semibold text-[#324457]">Daily handoff anchor</span><input type="time" wire:model.change="coverageStartTime" class="mt-1 min-h-12 w-full rounded-xl border-[#CFC4B5]"></label>
                    </div>
                    @if($shiftLengthChoice === 'custom')
                        <label class="mt-4 block max-w-md"><span class="text-sm font-semibold text-[#324457]">Custom shift length in hours</span><input type="number" min="1" max="12" step="any" inputmode="decimal" wire:model.change="customShiftLengthHours" class="mt-1 min-h-12 w-full rounded-xl border-[#CFC4B5]"><span class="mt-1 block text-xs text-[#607080]">Use a length that divides 24 hours evenly, such as 4, 3, 2, or 1.5 hours.</span>@error('customShiftLengthHours')<span class="mt-1 block text-sm text-rose-700">{{ $message }}</span>@enderror</label>
                    @endif
                @elseif ($coveragePattern === 'overnight')
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <label><span class="text-sm font-semibold text-[#324457]">Starts nightly</span><input type="time" wire:model.change="coverageStartTime" class="mt-1 min-h-12 w-full rounded-xl border-[#CFC4B5]"></label>
                        <label><span class="text-sm font-semibold text-[#324457]">Ends next morning</span><input type="time" wire:model.change="coverageEndTime" class="mt-1 min-h-12 w-full rounded-xl border-[#CFC4B5]"></label>
                    </div>
                @else
                    <div class="mt-5 space-y-3">
                        @foreach ($customWindows as $index => $window)
                            <div wire:key="coverage-window-{{ $index }}" class="grid gap-3 rounded-2xl bg-[#F7F2EA] p-3 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-end">
                                <label><span class="text-xs font-semibold text-[#526474]">Day</span><select wire:model.live="customWindows.{{ $index }}.day" class="mt-1 min-h-11 w-full rounded-xl border-[#CFC4B5]">@foreach(['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $dayIndex => $day)<option value="{{ $dayIndex }}">{{ $day }}</option>@endforeach</select></label>
                                <label><span class="text-xs font-semibold text-[#526474]">Start</span><input type="time" wire:model.change="customWindows.{{ $index }}.start" class="mt-1 min-h-11 w-full rounded-xl border-[#CFC4B5]"></label>
                                <label><span class="text-xs font-semibold text-[#526474]">End</span><input type="time" wire:model.change="customWindows.{{ $index }}.end" class="mt-1 min-h-11 w-full rounded-xl border-[#CFC4B5]"></label>
                                <button type="button" wire:click="removeWindow({{ $index }})" class="min-h-11 rounded-xl border border-rose-200 px-3 text-sm font-semibold text-rose-700">Remove</button>
                            </div>
                        @endforeach
                        @error('customWindows')<p class="text-sm text-rose-700">{{ $message }}</p>@enderror
                        <button type="button" wire:click="addWindow" class="min-h-11 rounded-xl border border-[#2F6F62] px-4 font-semibold text-[#2F6F62]">Add coverage window</button>
                    </div>
                @endif
            </section>
            @endif

            @if($step === 3)
            <section class="rounded-3xl border border-[#DED6CA] bg-white p-5 sm:p-6">
                <h2 class="font-display text-2xl font-semibold text-[#17313F]">3. Care and replacement preferences</h2>
                <label class="mt-5 block"><span class="text-sm font-semibold text-[#324457]">Care activities and expectations</span><textarea wire:model="careNotes" rows="5" class="mt-1 w-full rounded-xl border-[#CFC4B5]" placeholder="Describe what caregivers should know and do during these shifts."></textarea></label>
                <fieldset class="mt-5"><legend class="text-sm font-semibold text-[#324457]">Requested activities</legend><div class="mt-2 grid gap-2 sm:grid-cols-2">@foreach($taskOptions as $task)<label class="flex min-h-11 items-center gap-3 rounded-xl border border-[#E4DDD3] px-3"><input type="checkbox" wire:model="taskIds" value="{{ $task->id }}" class="rounded text-[#0F3D3E]"><span class="text-sm text-[#324457]">{{ $task->name }}</span></label>@endforeach</div></fieldset>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl border border-[#DED6CA] bg-[#F7F2EA] p-4"><p class="text-sm font-semibold text-[#324457]">Family rate</p><p class="mt-1 text-xl font-semibold text-[#17313F]">${{ number_format($hourlyRate, 2) }}/hour</p><p class="mt-1 text-xs text-[#607080]">Set by your existing LoLo Care pricing. Final charges follow the normal visit workflow.</p></div>
                    <label><span class="text-sm font-semibold text-[#324457]">When an approved backup accepts</span><select wire:model="replacementConfirmationMode" class="mt-1 min-h-12 w-full rounded-xl border-[#CFC4B5]"><option value="family_confirmation">Ask me to confirm</option><option value="approved_backup_auto">Confirm my approved backup automatically</option></select></label>
                </div>
                <label class="mt-5 flex min-h-12 items-start gap-3 rounded-2xl border border-[#DED6CA] bg-[#F7F2EA] p-4">
                    <input type="checkbox" wire:model="marketplaceApplicationsEnabled" class="mt-1 rounded border-[#AFA79B] text-[#2F6F62] focus:ring-[#2F6F62]">
                    <span><span class="block font-semibold text-[#17313F]">Allow caregivers to apply</span><span class="mt-1 block text-sm text-[#526474]">Optional. Caregivers see only the service area, schedule, activities, and rate. Your family must approve an applicant before they can join the care team or accept coverage.</span></span>
                </label>
            </section>
            @endif

            @if($step === 4)
                <section class="rounded-3xl border border-[#DED6CA] bg-white p-5 sm:p-6">
                    <p class="text-sm font-semibold uppercase tracking-wide text-[#2F6F62]">Final review</p>
                    <h2 class="mt-1 font-display text-2xl font-semibold text-[#17313F]">Confirm the plan before creating it</h2>
                    <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl bg-[#F7F2EA] p-4"><dt class="text-xs font-semibold text-[#7B8794]">Care recipient</dt><dd class="mt-1 font-semibold">{{ $recipientName }}</dd><dd class="text-sm text-[#526474]">{{ $relationshipToFamily }} · {{ $city }}, {{ strtoupper($state) }}</dd></div>
                        <div class="rounded-2xl bg-[#F7F2EA] p-4"><dt class="text-xs font-semibold text-[#7B8794]">Coverage</dt><dd class="mt-1 font-semibold">{{ $coveragePattern === '24_7' ? '24/7' : ucfirst($coveragePattern) }} · {{ number_format($weeklyHours, 1) }} hours/week</dd><dd class="text-sm text-[#526474]">{{ $shiftCount }} recurring shifts · begins {{ \Illuminate\Support\Carbon::parse($startsOn)->format('F j, Y') }}{{ $coveragePattern === '24_7' ? ' at '.\Illuminate\Support\Carbon::parse($coverageStartTime)->format('g:i A') : '' }} in {{ $timezone }}</dd></div>
                        <div class="rounded-2xl bg-[#F7F2EA] p-4"><dt class="text-xs font-semibold text-[#7B8794]">Rate</dt><dd class="mt-1 font-semibold">${{ number_format($hourlyRate, 2) }}/hour</dd><dd class="text-sm text-[#526474]">Final charges follow the existing visit and payment workflow.</dd></div>
                        <div class="rounded-2xl bg-[#F7F2EA] p-4"><dt class="text-xs font-semibold text-[#7B8794]">Replacement choice</dt><dd class="mt-1 font-semibold">{{ $replacementConfirmationMode === 'family_confirmation' ? 'Family confirms accepted backups' : 'Accepted approved backups confirm automatically' }}</dd></div>
                        <div class="rounded-2xl bg-[#F7F2EA] p-4"><dt class="text-xs font-semibold text-[#7B8794]">Caregiver applications</dt><dd class="mt-1 font-semibold">{{ $marketplaceApplicationsEnabled ? 'Open for applications' : 'Invitation only' }}</dd><dd class="text-sm text-[#526474]">The family still approves every caregiver before care-team membership.</dd></div>
                    </dl>
                    <div class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900"><p class="font-semibold">Creating the plan does not assign or notify a caregiver.</p><p class="mt-1">You will explicitly approve the care team next. Each caregiver separately decides whether to join and accept recurring coverage.</p></div>
                </section>
            @endif
        </div>

        <aside class="space-y-4 xl:sticky xl:top-24 xl:self-start">
            <section class="rounded-3xl bg-[#0F3D3E] p-5 text-white shadow-lg">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#CFC6F7]">Schedule preview</p>
                <p class="mt-3 font-display text-4xl font-semibold">{{ number_format($weeklyHours, 1) }}</p>
                <p class="text-sm text-[#F7F1E8]/80">coverage hours per week</p>
                <div class="mt-4 border-t border-white/20 pt-4"><p class="text-2xl font-semibold">{{ $shiftCount }}</p><p class="text-sm text-[#F7F1E8]/80">recurring shifts per week</p></div>
                @if ($scheduleAnalysis['has_overlaps'])
                    <p class="mt-4 rounded-2xl border border-amber-200/60 bg-amber-50 p-3 text-sm font-semibold text-amber-950">Some coverage windows overlap. Adjust them before creating the plan.</p>
                @elseif ($coveragePattern === '24_7' && ! $scheduleAnalysis['has_gaps'])
                    <p class="mt-4 rounded-2xl bg-emerald-100/15 p-3 text-sm font-semibold text-emerald-50">All 168 weekly hours are covered with no schedule gaps.</p>
                @elseif ($coveragePattern !== '24_7')
                    <p class="mt-4 rounded-2xl bg-white/10 p-3 text-sm text-[#F7F1E8]">Coverage applies to the selected weekly windows. Times outside them are intentionally not scheduled.</p>
                @endif
                <p class="mt-4 rounded-2xl bg-white/10 p-3 text-sm text-[#F7F1E8]">No caregiver is assigned yet. After creation, you approve the care team and caregivers choose which coverage to accept.</p>
            </section>
            <div class="grid gap-2 {{ $step > 1 ? 'grid-cols-2' : '' }}">
                @if($step > 1)<button type="button" wire:click="previousStep" class="min-h-14 rounded-xl border border-[#CFC4B5] bg-white px-4 font-semibold text-[#324457]">Back</button>@endif
                @if($step < 4)
                    <button type="button" wire:click="nextStep" wire:loading.attr="disabled" class="min-h-14 rounded-xl bg-[#0F3D3E] px-5 py-3 text-base font-semibold text-white shadow-sm disabled:opacity-60">Continue</button>
                @else
                    <button type="submit" wire:loading.attr="disabled" class="min-h-14 rounded-xl bg-[#0F3D3E] px-5 py-3 text-base font-semibold text-white shadow-sm disabled:opacity-60"><span wire:loading.remove wire:target="save">Create coverage plan</span><span wire:loading wire:target="save">Creating schedule…</span></button>
                @endif
            </div>
            <p class="text-center text-xs text-[#7B8794]">Creating this plan does not notify caregivers. You choose who to approve next.</p>
        </aside>
    </form>
</div>
