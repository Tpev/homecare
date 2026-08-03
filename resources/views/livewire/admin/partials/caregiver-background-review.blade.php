@php
    $showActions = $showActions ?? true;
@endphp

<section class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4" aria-label="Caregiver experience and credentials">
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-semibold text-slate-900">Care experience</h3>
                @if ($profile->care_experience_answered_at)
                    <span class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-slate-600">Answered {{ $profile->care_experience_answered_at->format('M j, Y') }}</span>
                @elseif (! $profile->requiresCareBackground())
                    <span class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-slate-500">Legacy · not answered</span>
                @endif
            </div>

            @if ($profile->careExperiences->isNotEmpty())
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($profile->careExperiences->sortBy('sort_order') as $experience)
                        <span class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs text-slate-700">{{ $experience->label }}</span>
                    @endforeach
                </div>
            @elseif ($profile->care_experience_answered_at)
                <p class="mt-2 text-sm text-slate-600">Caregiver selected no specialized care experience yet.</p>
            @else
                <p class="mt-2 text-sm text-slate-500">No answer has been recorded.</p>
            @endif

            @if (filled($profile->care_experience_notes))
                <div class="mt-3 rounded-xl border border-slate-200 bg-white px-3 py-2">
                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">General note</p>
                    <p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $profile->care_experience_notes }}</p>
                </div>
            @endif
        </div>

        <div>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-semibold text-slate-900">Credentials & training</h3>
                @if ($profile->certifications_answered_at)
                    <span class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-slate-600">Answered {{ $profile->certifications_answered_at->format('M j, Y') }}</span>
                @endif
            </div>

            @if ($profile->certifications->isEmpty())
                <p class="mt-2 text-sm text-slate-600">{{ $profile->certifications_answered_at ? 'Caregiver selected no current certifications.' : 'No answer has been recorded.' }}</p>
            @else
                <div class="mt-2 space-y-3">
                    @foreach ($profile->certifications->sortBy(fn ($credential) => $credential->type?->sort_order ?? 999) as $credential)
                        @php
                            $expired = $credential->isExpired();
                            $statusLabel = $expired ? 'Expired' : str($credential->verification_status)->headline();
                            $statusTone = match (true) {
                                $expired => 'bg-amber-100 text-amber-900',
                                $credential->isCurrentlyVerified() => 'bg-emerald-100 text-emerald-800',
                                $credential->verification_status === \App\Models\CaregiverCertification::STATUS_REJECTED => 'bg-rose-100 text-rose-800',
                                $credential->verification_status === \App\Models\CaregiverCertification::STATUS_PENDING => 'bg-blue-100 text-blue-800',
                                default => 'bg-slate-100 text-slate-700',
                            };
                        @endphp
                        <div class="rounded-xl border border-slate-200 bg-white p-3">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="font-medium text-slate-900">{{ $credential->displayName() }}</p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $credential->issuer ?: 'Issuer not provided' }}
                                        {{ $credential->issuing_state ? ' · '.$credential->issuing_state : '' }}
                                        {{ $credential->expires_at ? ' · '.($expired ? 'Expired ' : 'Expires ').$credential->expires_at->format('M j, Y') : '' }}
                                    </p>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusTone }}">{{ $statusLabel }}</span>
                            </div>

                            @if ($credential->verified_at)
                                <p class="mt-2 text-xs text-slate-500">Verified {{ $credential->verified_at->format('M j, Y g:i A') }}{{ $credential->verifier ? ' by '.$credential->verifier->name : '' }}</p>
                            @endif
                            @if ($credential->rejection_reason)
                                <p class="mt-2 rounded-lg bg-rose-50 px-2.5 py-2 text-xs text-rose-800">Internal review note: {{ $credential->rejection_reason }}</p>
                            @endif

                            <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                                @if ($credential->document_path)
                                    <a href="{{ route('caregiver.certifications.document', $credential) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Open evidence</a>
                                @else
                                    <span class="inline-flex min-h-11 items-center rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">No evidence uploaded</span>
                                @endif

                                @if ($showActions)
                                    <button type="button" wire:click="verifyCertification({{ $credential->id }})" @disabled(! $credential->document_path || $expired) class="min-h-11 rounded-xl bg-emerald-700 px-3 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-45">Verify credential</button>
                                @endif
                            </div>

                            @if ($showActions)
                                <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
                                    <label class="sr-only" for="certification-reason-{{ $credential->id }}">Reason for returning {{ $credential->displayName() }}</label>
                                    <input id="certification-reason-{{ $credential->id }}" type="text" wire:model="certificationRejectionReasons.{{ $credential->id }}" placeholder="Internal reason for returning this credential" class="min-h-11 rounded-xl border border-slate-300 px-3 py-2 text-sm">
                                    <button type="button" wire:click="rejectCertification({{ $credential->id }})" class="min-h-11 rounded-xl border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Return for attention</button>
                                </div>
                            @endif

                            @error('certification_'.$credential->id) <p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                            @error('certification_'.$credential->id.'_reason') <p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
