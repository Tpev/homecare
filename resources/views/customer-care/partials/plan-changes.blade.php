<details class="mt-5">
<summary class="cursor-pointer font-semibold">Change future schedule or request an extra visit</summary>
<form method="POST" action="{{ route('customer-care.action', $careRequest) }}" class="mt-4 space-y-3" x-data="{ planAction: 'extra' }">
@csrf
<input type="hidden" name="action" value="plan">
<label class="block text-sm font-semibold">Change<select class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="plan_action" x-model="planAction">
<option value="extra">Request an extra visit</option>
<option value="schedule">Change recurring schedule</option>
</select>
</label>
<p class="hc-care-subtitle">The caregiver receives the proposal and can accept it. The customer is not contacted.</p>
<div x-show="planAction === 'extra'" class="grid gap-3 sm:grid-cols-2">
<label class="block text-sm font-semibold">Visit start<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" type="datetime-local" name="start" :required="planAction === 'extra'" :disabled="planAction !== 'extra'">
</label>
<label class="block text-sm font-semibold">Visit end<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" type="datetime-local" name="end" :required="planAction === 'extra'" :disabled="planAction !== 'extra'">
</label>
</div>
<div x-show="planAction === 'schedule'" class="space-y-3">
<fieldset>
<legend class="text-sm font-semibold">New days</legend>
<div class="flex flex-wrap gap-3">
@foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day => $label)<label class="block text-sm font-semibold">
<input class="rounded border focus:ring-[#23483F]" name="schedule_days[]" type="checkbox" value="{{ $day }}" :disabled="planAction !== 'schedule'"> {{ $label }}</label>
@endforeach
</div>
</fieldset>
<div class="grid gap-3 sm:grid-cols-2">
<label class="block text-sm font-semibold">Start time<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" type="time" name="schedule_start_time" :required="planAction === 'schedule'" :disabled="planAction !== 'schedule'">
</label>
<label class="block text-sm font-semibold">End time<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" type="time" name="schedule_end_time" :required="planAction === 'schedule'" :disabled="planAction !== 'schedule'">
</label>
<label class="block text-sm font-semibold">Starts / effective on<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" type="date" name="starts_on" :required="planAction === 'schedule'" :disabled="planAction !== 'schedule'">
</label>
<label class="block text-sm font-semibold">Ends on (optional)<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" type="date" name="ends_on" :disabled="planAction !== 'schedule'">
</label>
</div>
</div>
<label class="block text-sm font-semibold">Reason<textarea class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="reason" required minlength="5" maxlength="2000">
</textarea>
</label>
<button class="hc-primary-button">Send proposal to caregiver</button>
</form>
</details>