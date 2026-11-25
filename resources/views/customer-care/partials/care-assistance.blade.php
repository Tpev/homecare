<section id="care-assistance" class="hc-surface p-5 sm:p-6 space-y-4" aria-labelledby="care-assistance-heading">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><p class="hc-care-eyebrow">Account program</p><h2 id="care-assistance-heading" class="text-xl font-semibold">Care Assistance</h2></div>
        <span class="hc-care-status">{{ $assistance ? 'Active for new requests' : 'Not active for new requests' }}</span>
    </div>
    <p class="text-sm"><strong>Customer: $23.00/hour total.</strong> Caregiver: $20.00/hour before Stripe processing fees. LoLo: $3.00/hour.</p>
    <p class="hc-care-subtitle">Applies to every caregiver on new requests for this family account. Existing requests, drafts, visits and recurring plans keep their agreed terms when participation starts or ends. Use the family's saved card; prepaid care hours cannot fund Care Assistance work.</p>
    @if($assistance)
        <p class="text-sm">Activated {{ $assistance->created_at->format('M j, Y g:i A T') }} by {{ $assistance->creator?->name ?? 'Former staff member' }}.
            @if($assistance->care_assistance_application_id)<a class="hc-care-text-link" href="{{ route('admin.care-assistance.show', $assistance->care_assistance_application_id) }}">Application #{{ $assistance->care_assistance_application_id }}</a>@else Approval recorded outside the application form.@endif
        </p>
    @endif
    <details @if($errors->hasAny(['assistance', 'application_id', 'approval_confirmed', 'expected_agreement_id'])) open @endif>
        <summary class="hc-care-text-link cursor-pointer">{{ $assistance ? 'End participation for new requests' : 'Activate Care Assistance' }}</summary>
        <form method="POST" action="{{ route('customer-care.care-assistance', $customer) }}" class="mt-4 space-y-4 max-w-2xl">
            @csrf
            <input type="hidden" name="action" value="{{ $assistance ? 'end' : 'activate' }}">
            <input type="hidden" name="expected_agreement_id" value="{{ $assistance?->id ?? 0 }}">
            @unless($assistance)
                <label class="block text-sm font-semibold">Approved application
                    <select name="application_id" class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal">
                        <option value="">Approval recorded outside the application form</option>
                        @foreach($approvedApplications as $application)
                            <option value="{{ $application->id }}" @selected((string) old('application_id') === (string) $application->id)>#{{ $application->id }} · {{ $application->name }} · {{ $application->email }}</option>
                        @endforeach
                    </select>
                </label>
                <p class="hc-care-subtitle">Choose the approved applicant even if their email differs from the payer. <a class="hc-care-text-link" href="{{ route('admin.care-assistance.index') }}">Review applications</a>. If approval was recorded elsewhere, explain where in the reason below.</p>
                <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="approval_confirmed" value="1" required class="mt-1 rounded" @checked(old('approval_confirmed'))><span>I verified the approval belongs to {{ $customer->name }}'s family account.</span></label>
            @endunless
            <label class="block text-sm font-semibold">{{ $assistance ? 'Reason for ending participation' : 'Approval reference / reason' }}
                <textarea name="reason" minlength="5" maxlength="1000" required class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal">{{ old('reason') }}</textarea>
            </label>
            <p class="hc-care-subtitle">Saved in the staff activity history. No customer email or text is sent.</p>
            <button class="hc-primary-button">{{ $assistance ? 'End Care Assistance for new requests' : 'Activate Care Assistance' }}</button>
        </form>
    </details>
    @if($assistanceHistory->isNotEmpty())
        <details><summary class="hc-care-text-link cursor-pointer">Participation history</summary>
            <ul class="mt-3 space-y-3 text-sm">
                @foreach($assistanceHistory as $event)
                    <li class="border-t pt-3"><strong>{{ $event->action === 'care_assistance_activated' ? 'Activated' : 'Ended' }}</strong> · {{ $event->created_at->format('M j, Y g:i A T') }} · {{ $event->actor?->name ?? 'Former staff member' }}<p class="hc-care-subtitle">{{ data_get($event->metadata, 'reason') }}</p></li>
                @endforeach
            </ul>
        </details>
    @endif
</section>
