<div class="auth-consent space-y-1">
    <label for="sms_opt_in" class="flex cursor-pointer items-start gap-3 text-sm leading-6 text-[#56655F]">
        <input wire:model="sms_opt_in" id="sms_opt_in" type="checkbox" class="mt-1 shrink-0">
        <span>
            By providing your phone number and checking this box, you consent to receive SMS messages from Lolo Care,Inc. regarding appointment reminders, service updates, and customer support. Message frequency may vary. Standard message and data rates may apply. Text STOP to unsubscribe or HELP for assistance. See our
            <a href="{{ route('legal.show', ['slug' => 'privacy-policy']) }}" class="underline hover:text-slate-900" target="_blank" rel="noopener noreferrer">Privacy Policy</a>
            and
            <a href="{{ route('legal.show', ['slug' => 'platform-terms-of-service']) }}" class="underline hover:text-slate-900" target="_blank" rel="noopener noreferrer">Terms of Service</a>.
            Consent is not required to submit this form.
        </span>
    </label>
    <x-input-error :messages="$errors->get('sms_opt_in')" class="mt-2" />
</div>
