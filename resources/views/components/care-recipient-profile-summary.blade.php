@props([
    'snapshot' => null,
    'compact' => false,
    'showSharingFooter' => true,
])

@if (is_array($snapshot) && ! empty($snapshot['preferred_name']))
    @php
        $name = $snapshot['preferred_name'];
        $sections = (array) ($snapshot['sections'] ?? []);
        $labels = [
            'at_a_glance' => 'At a glance',
            'communication' => 'Communication',
            'support_and_mobility' => 'Support and mobility',
            'routine_and_comfort' => 'Routine and comfort',
            'important_for_safety' => 'Important for safety',
            'family_expectations' => 'What the family is looking for',
        ];
        $fieldLabels = [
            'about' => null,
            'interests_and_comforts' => 'Interests and comforts',
            'good_visit' => 'A good visit',
            'everyday_health_context' => 'Everyday health or memory context',
            'preferences' => null,
            'notes' => null,
            'support_areas' => null,
            'support_details' => 'Support details',
            'mobility' => 'Mobility',
            'mobility_notes' => null,
            'routine' => 'Usual routine',
            'food_and_drink' => 'Food and drink',
            'personal_care' => 'Personal-care preferences',
            'sleep_and_overnight' => 'Sleep and overnight',
            'comfort_needs' => 'Comfort and reassurance',
            'may_feel_uncomfortable_when' => 'May feel uncomfortable when',
            'what_helps' => 'What helps',
            'items' => null,
            'qualities' => 'Helpful caregiver qualities',
            'please_do' => 'Please do',
            'please_avoid' => 'Please avoid',
        ];
    @endphp

    <article {{ $attributes->class(['rounded-2xl border border-[#DCCFBE] bg-white shadow-sm', 'p-4 sm:p-5' => ! $compact, 'p-4' => $compact]) }} aria-label="Care profile for {{ $name }}">
        <header class="flex flex-col gap-3 border-b border-[#EEE5DA] pb-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="font-display text-xl font-semibold text-[#17313F]">About {{ $name }}</h2>
                    @if (! empty($snapshot['_is_updated']))
                        <span class="rounded-full bg-[#FFF0C7] px-2.5 py-1 text-xs font-bold text-[#7A5312]">Updated</span>
                    @endif
                </div>
                <div class="mt-2 flex flex-wrap gap-2 text-xs font-semibold text-[#526474]">
                    @foreach (array_filter([$snapshot['relationship_context'] ?? null, $snapshot['age_range'] ?? null, $snapshot['pronouns'] ?? null]) as $context)
                        <span class="rounded-full bg-[#F4EFE8] px-2.5 py-1">{{ $context }}</span>
                    @endforeach
                </div>
            </div>
        </header>

        <div class="mt-4 space-y-4">
            @foreach ($labels as $sectionKey => $sectionLabel)
                @php($section = (array) ($sections[$sectionKey] ?? []))
                @if ($section !== [])
                    <section class="rounded-xl {{ $sectionKey === 'important_for_safety' ? 'border border-amber-300 bg-amber-50' : 'bg-[#FAF7F2]' }} p-4" aria-labelledby="profile-{{ $sectionKey }}-{{ md5((string) $name) }}">
                        <h3 id="profile-{{ $sectionKey }}-{{ md5((string) $name) }}" class="font-display text-base font-semibold {{ $sectionKey === 'important_for_safety' ? 'text-amber-950' : 'text-[#17313F]' }}">{{ $sectionLabel }}</h3>
                        <div class="mt-3 space-y-3 text-sm leading-6 text-[#445762]">
                            @foreach ($section as $field => $value)
                                @if (is_array($value) && $value !== [])
                                    @if ($field === 'support_details')
                                        <div>
                                            @if ($fieldLabels[$field] ?? null)<p class="font-semibold text-[#263C48]">{{ $fieldLabels[$field] }}</p>@endif
                                            <dl class="mt-1 space-y-1">
                                                @foreach ($value as $detailLabel => $detail)
                                                    <div><dt class="inline font-semibold">{{ $detailLabel }}:</dt> <dd class="inline">{{ $detail }}</dd></div>
                                                @endforeach
                                            </dl>
                                        </div>
                                    @else
                                        <div>
                                            @if ($fieldLabels[$field] ?? null)<p class="font-semibold text-[#263C48]">{{ $fieldLabels[$field] }}</p>@endif
                                            <ul class="mt-1 flex flex-wrap gap-2" role="list">
                                                @foreach ($value as $item)
                                                    <li class="rounded-full border border-[#DED6CA] bg-white px-2.5 py-1 text-xs font-semibold">{{ $item }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                @elseif (filled($value))
                                    <div>
                                        @if ($fieldLabels[$field] ?? null)<p class="font-semibold text-[#263C48]">{{ $fieldLabels[$field] }}</p>@endif
                                        <p class="whitespace-pre-line">{{ $value }}</p>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach

            @if (! empty($snapshot['contacts_and_care_coordination']))
                @php($contact = $snapshot['contacts_and_care_coordination'])
                <section class="rounded-xl border border-[#CFE1D8] bg-[#F2F8F4] p-4" aria-label="Contacts and care coordination">
                    <h3 class="font-display text-base font-semibold text-[#17313F]">Contacts and care coordination</h3>
                    @if (! empty($snapshot['full_name']))<p class="mt-2 text-sm"><span class="font-semibold">Full name:</span> {{ $snapshot['full_name'] }}</p>@endif
                    @if (! empty($contact['name']))
                        <p class="mt-2 text-sm font-semibold text-[#263C48]">{{ $contact['name'] }}@if(!empty($contact['relationship'])) &middot; {{ $contact['relationship'] }}@endif</p>
                    @endif
                    <div class="mt-1 space-y-1 text-sm text-[#445762]">
                        @if (! empty($contact['phone']))<p><a class="font-semibold underline" href="tel:{{ $contact['phone'] }}">{{ $contact['phone'] }}</a></p>@endif
                        @if (! empty($contact['email']))<p><a class="font-semibold underline" href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></p>@endif
                        @if (! empty($contact['escalation_note']))<p class="mt-2 whitespace-pre-line">{{ $contact['escalation_note'] }}</p>@endif
                    </div>
                </section>
            @elseif (! empty($snapshot['full_name']))
                <p class="text-sm text-[#526474]"><span class="font-semibold">Full name:</span> {{ $snapshot['full_name'] }}</p>
            @endif
        </div>

        @if ($showSharingFooter)
            <footer class="mt-4 border-t border-[#EEE5DA] pt-3 text-xs text-[#6A7784]">
                @if(!empty($snapshot['last_reviewed_at']))
                    Shared by {{ $name }}'s family &middot; last reviewed {{ \Illuminate\Support\Carbon::parse($snapshot['last_reviewed_at'])->format('F j, Y') }}.
                @else
                    Shared by {{ $name }}'s family.
                @endif
            </footer>
        @endif
    </article>
@endif
