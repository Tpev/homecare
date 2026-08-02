<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }
}; ?>

<nav x-data="{ open: false }" class="sticky top-0 z-40 border-b border-[#E3D6C5]/80 bg-[rgba(255,253,250,0.94)] backdrop-blur">
    @php
        $user = auth()->user();
        $isCaregiver = $user?->role === 'caregiver';
        $isFamily = $user?->role === 'family';
        $isAdmin = $user && ($user->role === 'admin' || strtolower($user->email) === 'test@test.com');
        $isSales = $user?->role === 'sales';
        $isSdr = $user?->role === 'sdr';
        $canAccessCrm = $isAdmin || $isSales;
        $canAccessSdr = $isAdmin || $isSales || $isSdr;
        $caregiverProfile = $isCaregiver ? $user?->caregiverProfile : null;
        $caregiverOnboardingState = $isCaregiver
            ? app(\App\Support\CaregiverOnboardingState::class)->build($user)
            : null;
        $caregiverOnboardingMode = $isCaregiver && (($caregiverOnboardingState['onboarding_mode'] ?? false) === true);
        $identityStatus = $isCaregiver ? (string) ($caregiverProfile?->identity_verification_status ?? 'not_started') : '';
        $identityApproved = $isCaregiver
            ? ((bool) $caregiverProfile?->identity_verified_at || $identityStatus === 'approved')
            : false;

        $messageUnread = 0;
        $invitationUnread = 0;
        $notificationUnread = 0;
        $supportUnread = 0;
        $supportUnreadTickets = collect();

        if (\Illuminate\Support\Facades\Schema::hasTable('care_request_conversations')) {
            if ($isFamily) {
                $messageUnread = \App\Models\CareRequestConversation::query()
                    ->where('family_user_id', $user->id)
                    ->whereNotNull('last_message_at')
                    ->where('last_message_sender_id', '!=', $user->id)
                    ->where(function ($query) {
                        $query->whereNull('family_last_read_at')
                            ->orWhereColumn('last_message_at', '>', 'family_last_read_at');
                    })
                    ->count();
            } elseif ($isCaregiver) {
                $messageUnread = \App\Models\CareRequestConversation::query()
                    ->where('caregiver_user_id', $user->id)
                    ->whereNotNull('last_message_at')
                    ->where('last_message_sender_id', '!=', $user->id)
                    ->where(function ($query) {
                        $query->whereNull('caregiver_last_read_at')
                            ->orWhereColumn('last_message_at', '>', 'caregiver_last_read_at');
                    })
                    ->count();

                if (\Illuminate\Support\Facades\Schema::hasTable('care_request_invitations')) {
                    $invitationUnread = \App\Models\CareRequestInvitation::query()
                        ->where('caregiver_user_id', $user->id)
                        ->where('status', \App\Models\CareRequestInvitation::STATUS_PENDING)
                        ->where(function ($query) {
                            $query->whereNull('expires_at')->orWhere('expires_at', '>=', now());
                        })
                        ->count();
                }

                if (\Illuminate\Support\Facades\Schema::hasTable('care_plans')) {
                    $invitationUnread += \App\Models\CarePlan::query()
                        ->where('caregiver_user_id', $user->id)
                        ->where('status', \App\Models\CarePlan::STATUS_PENDING_CAREGIVER)
                        ->count();
                }

            }

            if (($isCaregiver || $isFamily || $isAdmin) && \Illuminate\Support\Facades\Schema::hasTable('notifications')) {
                $notificationUnread = $user->unreadNotifications()->count();
            }
        }

        if ($user
            && \Illuminate\Support\Facades\Schema::hasTable('support_tickets')
            && \Illuminate\Support\Facades\Schema::hasColumn('support_tickets', 'last_public_message_at')
        ) {
            $supportUnreadQuery = \App\Models\SupportTicket::query()
                ->whereNotNull('last_public_message_at');

            if ($isAdmin) {
                $adminSupportUnreadQuery = $supportUnreadQuery
                    ->whereColumn('last_public_message_sender_id', 'opener_user_id')
                    ->where(function ($query) {
                        $query->whereNull('admin_last_read_at')
                            ->orWhereColumn('last_public_message_at', '>', 'admin_last_read_at');
                    });

                $supportUnread = (clone $adminSupportUnreadQuery)->count();
                $supportUnreadTickets = $adminSupportUnreadQuery
                    ->with([
                        'opener:id,name',
                        'assignedAdmin:id,name',
                        'latestPublicMessage.sender:id,name,role',
                    ])
                    ->orderByDesc('last_public_message_at')
                    ->limit(5)
                    ->get();
            } elseif ($isFamily || $isCaregiver) {
                $supportUnread = $supportUnreadQuery
                    ->where('opener_user_id', $user->id)
                    ->where('last_public_message_sender_id', '!=', $user->id)
                    ->where(function ($query) {
                        $query->whereNull('opener_last_read_at')
                            ->orWhereColumn('last_public_message_at', '>', 'opener_last_read_at');
                    })
                    ->count();
            }
        }

        $myProfileHref = $isCaregiver ? route('caregiver.profile.edit') : route('profile');
        $myProfileLabel = $isCaregiver ? 'My Caregiver Profile' : ($isFamily ? 'My Family Profile' : 'My Profile');
        $securityHref = route('profile').'#password-security';
        $publicProfileHref = $isCaregiver && $caregiverProfile?->slug ? route('caregivers.show', $caregiverProfile->slug) : null;

        $avatarUrl = null;
        if ($isCaregiver && $caregiverProfile?->profile_photo_path) {
            $avatarUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($caregiverProfile->profile_photo_path);
        }

        $nameParts = preg_split('/\s+/', trim((string) ($user?->name ?? 'Guest')));
        $initials = collect($nameParts)->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('');
        $initials = $initials !== '' ? $initials : strtoupper(substr((string) ($user?->name ?? 'G'), 0, 1));

        $adminCrmLink = [
            'label' => 'CRM',
            'href' => route('admin.crm.index'),
            'active' => request()->routeIs('admin.crm.*') || request()->routeIs('admin.leads.*'),
        ];

        $sdrCallLink = [
            'label' => 'Call queue',
            'href' => route('sdr.calling'),
            'active' => request()->routeIs('sdr.*'),
        ];

        $adminNavGroups = [
            [
                'label' => 'Care ops',
                'active' => request()->routeIs('admin.requests.*')
                    || request()->routeIs('admin.care-plans.*')
                    || request()->routeIs('admin.caregivers.reviews')
                    || request()->routeIs('admin.support.tickets*')
                    || request()->routeIs('admin.notifications.*'),
                'items' => [
                    [
                        'label' => 'Admin Requests',
                        'href' => route('admin.requests.index'),
                        'active' => request()->routeIs('admin.requests.*'),
                    ],
                    [
                        'label' => 'Regular Care',
                        'href' => route('admin.care-plans.index'),
                        'active' => request()->routeIs('admin.care-plans.*'),
                    ],
                    [
                        'label' => 'Admin Reviews',
                        'href' => route('admin.caregivers.reviews'),
                        'active' => request()->routeIs('admin.caregivers.reviews'),
                    ],
                    [
                        'label' => $supportUnread > 0 ? "Admin Support ($supportUnread)" : 'Admin Support',
                        'href' => route('admin.support.tickets'),
                        'active' => request()->routeIs('admin.support.tickets*'),
                    ],
                    [
                        'label' => $notificationUnread > 0 ? "Notifications ($notificationUnread)" : 'Notifications',
                        'href' => route('admin.notifications.index'),
                        'active' => request()->routeIs('admin.notifications.*'),
                    ],
                ],
            ],
            [
                'label' => 'People',
                'active' => request()->routeIs('admin.users.*')
                    || request()->routeIs('admin.caregivers.moderation_logs'),
                'items' => [
                    [
                        'label' => 'Admin Users',
                        'href' => route('admin.users.index'),
                        'active' => request()->routeIs('admin.users.index'),
                    ],
                    [
                        'label' => 'Moderation Logs',
                        'href' => route('admin.caregivers.moderation_logs'),
                        'active' => request()->routeIs('admin.caregivers.moderation_logs'),
                    ],
                ],
            ],
            [
                'label' => 'Comms & money',
                'active' => request()->routeIs('admin.sms.*')
                    || request()->routeIs('admin.voice-ai.*')
                    || request()->routeIs('admin.provider-outreach-ai.*')
                    || request()->routeIs('admin.sdr-outreach.*')
                    || request()->routeIs('admin.payments.ops'),
                'items' => [
                    [
                        'label' => 'Admin SMS',
                        'href' => route('admin.sms.index'),
                        'active' => request()->routeIs('admin.sms.*'),
                    ],
                    [
                        'label' => 'Voice AI Test',
                        'href' => route('admin.voice-ai.index'),
                        'active' => request()->routeIs('admin.voice-ai.*'),
                    ],
                    [
                        'label' => 'Provider AI Outreach',
                        'href' => route('admin.provider-outreach-ai.index'),
                        'active' => request()->routeIs('admin.provider-outreach-ai.*'),
                    ],
                    [
                        'label' => 'SDR Outreach',
                        'href' => route('admin.sdr-outreach.index'),
                        'active' => request()->routeIs('admin.sdr-outreach.*'),
                    ],
                    [
                        'label' => 'Admin Payments',
                        'href' => route('admin.payments.ops'),
                        'active' => request()->routeIs('admin.payments.ops'),
                    ],
                ],
            ],
            [
                'label' => 'Analytics',
                'active' => request()->routeIs('admin.analytics.*'),
                'items' => [
                    [
                        'label' => 'Usage Analytics',
                        'href' => route('admin.analytics.usage'),
                        'active' => request()->routeIs('admin.analytics.usage'),
                    ],
                    [
                        'label' => 'Admin Funnel',
                        'href' => route('admin.analytics.funnel'),
                        'active' => request()->routeIs('admin.analytics.funnel'),
                    ],
                    [
                        'label' => 'Admin Coverage',
                        'href' => route('admin.analytics.caregiver-map'),
                        'active' => request()->routeIs('admin.analytics.caregiver-map'),
                    ],
                ],
            ],
        ];

        $primaryLinks = [];

        if ($canAccessCrm) {
            $primaryLinks = [$adminCrmLink];
        } elseif ($isSdr) {
            $primaryLinks = [$sdrCallLink];
        } else {
            if ($user && ! $caregiverOnboardingMode) {
                $primaryLinks[] = [
                    'label' => $isFamily ? 'Home' : 'Dashboard',
                    'href' => route('dashboard'),
                    'active' => request()->routeIs('dashboard'),
                ];
            }

            if ($isFamily) {
                $primaryLinks[] = [
                    'label' => 'Care',
                    'href' => route('family.requests.index'),
                    'active' => request()->routeIs('family.requests.index')
                        || request()->routeIs('family.requests.show')
                        || request()->routeIs('family.care.*'),
                ];
                $primaryLinks[] = [
                    'label' => 'Find Caregivers',
                    'href' => route('caregivers.search'),
                    'active' => request()->routeIs('caregivers.search') || request()->routeIs('caregivers.show'),
                ];
                $primaryLinks[] = [
                    'label' => $messageUnread > 0 ? "Messages ($messageUnread)" : 'Messages',
                    'href' => route('messages.index'),
                    'active' => request()->routeIs('messages.*'),
                ];
                $primaryLinks[] = [
                    'label' => 'Get care',
                    'href' => route('family.requests.create'),
                    'active' => request()->routeIs('family.requests.create') || request()->routeIs('family.requests.create_ai'),
                    'primary' => true,
                ];
            }

            if ($isCaregiver) {
                if ($caregiverOnboardingMode) {
                    $primaryLinks[] = [
                        'label' => 'Setup',
                        'href' => route('caregiver.setup.index'),
                        'active' => request()->routeIs('caregiver.setup.*')
                            || request()->routeIs('caregiver.onboarding')
                            || request()->routeIs('caregiver.verification.*')
                            || request()->routeIs('caregiver.tasks.*')
                            || request()->routeIs('caregiver.insurance.*')
                            || request()->routeIs('caregiver.video.*')
                            || request()->routeIs('caregiver.payouts.connect.*'),
                    ];
                    $primaryLinks[] = [
                        'label' => $supportUnread > 0 ? "Support ($supportUnread)" : 'Support',
                        'href' => route('support.index'),
                        'active' => request()->routeIs('support.*'),
                    ];
                } else {
                    $primaryLinks[] = [
                        'label' => $invitationUnread > 0 ? "Work Inbox ($invitationUnread)" : 'Work Inbox',
                        'href' => route('caregiver.work-inbox.index'),
                        'active' => request()->routeIs('caregiver.work-inbox.*'),
                    ];
                    $primaryLinks[] = [
                        'label' => 'Regular Clients',
                        'href' => route('caregiver.regular-clients.index'),
                        'active' => request()->routeIs('caregiver.regular-clients.*'),
                    ];
                    $primaryLinks[] = [
                        'label' => 'My Visits',
                        'href' => route('caregiver.shifts.index'),
                        'active' => request()->routeIs('caregiver.shifts.*'),
                    ];
                    $primaryLinks[] = [
                        'label' => 'My Earnings',
                        'href' => route('caregiver.earnings.index'),
                        'active' => request()->routeIs('caregiver.earnings.*'),
                    ];
                    $primaryLinks[] = [
                        'label' => $messageUnread > 0 ? "Messages ($messageUnread)" : 'Messages',
                        'href' => route('messages.index'),
                        'active' => request()->routeIs('messages.*'),
                    ];
                }
            }
        }
    @endphp

    <div class="hc-page relative">
        <div class="flex h-[4.5rem] items-center justify-between gap-3 py-2">
            <div class="shrink-0 flex items-center">
                <a href="{{ $user ? route('dashboard') : route('landing') }}" wire:navigate class="inline-flex items-center gap-3">
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-[#23483F]/10 bg-[rgba(255,253,250,0.96)] shadow-sm">
                        <img src="{{ asset('images/marketing/lolo/lolo-app-icon.svg') }}" alt="LoLo" class="block h-7 w-7 object-contain" />
                    </span>
                    <span class="block">
                        <img src="{{ asset('images/marketing/lolo/lolo-wordmark-evergreen.svg') }}" alt="LoLo" class="block h-6 w-auto object-contain sm:h-7" />
                        <span class="mt-1 hidden text-[11px] font-medium uppercase tracking-[0.16em] text-[#6E746F] md:block">{{ $canAccessCrm ? ($isAdmin ? 'Admin console' : 'Sales workspace') : ($isSdr ? 'SDR workspace' : ($isCaregiver ? 'Caregiver workspace' : ($isFamily ? 'Family workspace' : 'Care that feels personal.'))) }}</span>
                    </span>
                </a>
            </div>

            <div class="hidden min-w-0 flex-1 items-center gap-2 sm:ml-8 sm:mr-72 sm:flex">
                @if ($canAccessCrm)
                    <x-nav-link :href="$adminCrmLink['href']" :active="$adminCrmLink['active']" wire:navigate>
                        {{ __($adminCrmLink['label']) }}
                    </x-nav-link>

                    @if ($isAdmin)
                        @foreach ($adminNavGroups as $group)
                            <div class="relative" x-data="{ open: false }">
                                <button
                                    type="button"
                                    @click="open = !open"
                                    class="inline-flex items-center gap-1 rounded-full px-4 py-2 text-sm font-semibold transition {{ $group['active'] ? 'bg-[#23483F] text-[#FFFBF4]' : 'text-[#547067] hover:bg-[#F8F0E2] hover:text-[#23483F]' }}"
                                >
                                    <span>{{ $group['label'] }}</span>
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.12l3.71-3.89a.75.75 0 111.08 1.04l-4.25 4.46a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                    </svg>
                                </button>

                                <div
                                    x-cloak
                                    x-show="open"
                                    x-transition
                                    @click.outside="open = false"
                                    class="absolute left-0 z-50 mt-2 w-56 rounded-2xl border border-[#E3D6C5] bg-[rgba(255,253,250,0.98)] p-2 shadow-xl"
                                    style="display: none;"
                                >
                                    @foreach ($group['items'] as $item)
                                        <a
                                            href="{{ $item['href'] }}"
                                            wire:navigate
                                            class="block rounded-xl px-3 py-2 text-sm font-medium {{ $item['active'] ? 'bg-[#23483F] text-[#FFFBF4]' : 'text-[#23483F] hover:bg-[#F8F0E2]' }}"
                                        >
                                            {{ __($item['label']) }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @endif
                @elseif ($isSdr)
                    <x-nav-link :href="$sdrCallLink['href']" :active="$sdrCallLink['active']" wire:navigate>
                        {{ __($sdrCallLink['label']) }}
                    </x-nav-link>
                @else
                    @foreach ($primaryLinks as $link)
                        @if (!empty($link['primary']))
                            <a
                                href="{{ $link['href'] }}"
                                wire:navigate
                                class="inline-flex items-center justify-center rounded-full px-4 py-2 text-sm font-semibold transition {{ $link['active'] ? 'bg-[#C96B55] text-white shadow-sm' : 'bg-[#23483F] text-[#FFFBF4] shadow-sm hover:bg-[#1B3D35]' }}"
                            >
                                {{ __($link['label']) }}
                            </a>
                        @else
                            <x-nav-link :href="$link['href']" :active="$link['active']" wire:navigate>
                                {{ __($link['label']) }}
                            </x-nav-link>
                        @endif
                    @endforeach
                @endif
            </div>

            <div class="sm:hidden ml-auto flex items-center gap-2" @if ($isAdmin) wire:poll.10s.visible @endif>
                @if ($isAdmin)
                    <a
                        href="{{ route('admin.support.tickets') }}"
                        wire:navigate
                        aria-label="{{ $supportUnread > 0 ? $supportUnread.' unread support tickets' : 'Support notifications' }}"
                        class="relative inline-flex min-h-11 min-w-11 items-center justify-center rounded-2xl border border-[#E3D6C5] bg-[rgba(255,253,250,0.98)] text-[#23483F] shadow-sm hover:bg-[#F8F0E2]"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9a6 6 0 00-12 0v.75a8.967 8.967 0 01-2.312 6.022 23.848 23.848 0 005.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                        </svg>
                        @if ($supportUnread > 0)
                            <span class="absolute -right-1 -top-1 inline-flex min-h-5 min-w-5 items-center justify-center rounded-full bg-[#C96B55] px-1 text-[10px] font-bold leading-none text-white">
                                {{ $supportUnread > 99 ? '99+' : $supportUnread }}
                            </span>
                        @endif
                    </a>
                @endif
                <button @click="open = ! open" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-2xl border border-[#E3D6C5] bg-[rgba(255,253,250,0.98)] text-[#23483F] shadow-sm hover:bg-[#F8F0E2]">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': !open}" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': !open, 'inline-flex': open}" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <div class="hidden sm:flex absolute right-0 top-1/2 -translate-y-1/2 items-center gap-2" x-data="{ accountOpen: false, supportOpen: false }">
            @if ($user)
                @if ($isAdmin)
                    <div class="relative" wire:poll.10s.visible>
                        <button
                            type="button"
                            @click="supportOpen = !supportOpen; accountOpen = false"
                            :aria-expanded="supportOpen"
                            aria-controls="admin-support-notifications"
                            aria-label="{{ $supportUnread > 0 ? $supportUnread.' unread support tickets' : 'Support notifications' }}"
                            title="Support notifications"
                            class="relative inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-[#E3D6C5] bg-[rgba(255,253,250,0.98)] text-[#23483F] shadow-sm transition hover:border-[#23483F]/12 hover:bg-[#F8F0E2]"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9a6 6 0 00-12 0v.75a8.967 8.967 0 01-2.312 6.022 23.848 23.848 0 005.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                            </svg>
                            @if ($supportUnread > 0)
                                <span class="absolute -right-1 -top-1 inline-flex min-h-5 min-w-5 items-center justify-center rounded-full bg-[#C96B55] px-1 text-[10px] font-bold leading-none text-white">
                                    {{ $supportUnread > 99 ? '99+' : $supportUnread }}
                                </span>
                            @endif
                        </button>

                        <div
                            id="admin-support-notifications"
                            x-cloak
                            x-show="supportOpen"
                            x-transition
                            @click.outside="supportOpen = false"
                            class="absolute right-0 z-50 mt-2 w-[24rem] overflow-hidden rounded-[1.4rem] border border-[#E3D6C5] bg-[rgba(255,253,250,0.99)] shadow-xl"
                            style="display: none;"
                        >
                            <div class="flex items-center justify-between border-b border-[#E3D6C5] px-4 py-3">
                                <div>
                                    <p class="text-sm font-semibold text-[#23483F]">Support activity</p>
                                    <p class="mt-0.5 text-xs text-[#6E746F]">New customer and caregiver replies</p>
                                </div>
                                @if ($supportUnread > 0)
                                    <span class="rounded-full bg-[#F5E7E1] px-2.5 py-1 text-xs font-semibold text-[#A84F3B]">{{ $supportUnread }} unread</span>
                                @endif
                            </div>

                            <div class="max-h-[22rem] overflow-y-auto p-2">
                                @forelse ($supportUnreadTickets as $unreadTicket)
                                    <a
                                        href="{{ route('admin.support.tickets.show', $unreadTicket) }}"
                                        wire:navigate
                                        class="block rounded-2xl px-3 py-3 text-left transition hover:bg-[#F8F0E2]"
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <p class="min-w-0 truncate text-sm font-semibold text-[#23483F]">#{{ $unreadTicket->id }} {{ $unreadTicket->subject }}</p>
                                            <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-[#C96B55]" aria-hidden="true"></span>
                                        </div>
                                        <p class="mt-1 text-xs text-[#6E746F]">
                                            {{ $unreadTicket->opener?->name ?: 'Former user' }}
                                            · {{ $unreadTicket->assignedAdmin?->name ? 'Assigned to '.$unreadTicket->assignedAdmin->name : 'Unassigned' }}
                                        </p>
                                        <p class="mt-1 truncate text-xs text-[#547067]">
                                            {{ $unreadTicket->latestPublicMessage?->body ?: $unreadTicket->description }}
                                        </p>
                                        <p class="mt-1 text-[11px] text-[#8A8F8B]">{{ $unreadTicket->last_public_message_at?->diffForHumans() }}</p>
                                    </a>
                                @empty
                                    <div class="px-4 py-8 text-center">
                                        <p class="text-sm font-semibold text-[#23483F]">You’re all caught up</p>
                                        <p class="mt-1 text-xs text-[#6E746F]">New support replies will appear here.</p>
                                    </div>
                                @endforelse
                            </div>

                            <a
                                href="{{ route('admin.support.tickets') }}"
                                wire:navigate
                                class="block border-t border-[#E3D6C5] px-4 py-3 text-center text-sm font-semibold text-[#0F6B5B] hover:bg-[#F8F0E2]"
                            >
                                View all support tickets
                            </a>
                        </div>
                    </div>
                @endif

                <button
                    type="button"
                    @click="accountOpen = !accountOpen; supportOpen = false"
                    class="inline-flex items-center gap-3 rounded-2xl border border-[#E3D6C5] bg-[rgba(255,253,250,0.98)] px-3 py-2 text-sm text-[#23483F] shadow-sm transition hover:border-[#23483F]/12 hover:bg-[#F8F0E2]"
                >
                    @if ($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="{{ $user->name }}" class="h-9 w-9 rounded-full object-cover border border-[#E3D6C5]">
                    @else
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-[#F5E7E1] text-xs font-semibold text-[#C96B55]">
                            {{ $initials }}
                        </span>
                    @endif

                    <span class="hidden md:block text-left">
                        <span class="block text-sm font-semibold leading-tight text-[#23483F]">{{ $user->name }}</span>
                        <span class="block text-xs text-[#6E746F] leading-tight">{{ ucfirst((string) $user->role) }}</span>
                    </span>

                    <svg class="h-4 w-4 text-[#6E746F]" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.12l3.71-3.89a.75.75 0 111.08 1.04l-4.25 4.46a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                    </svg>
                </button>

                <div
                    x-show="accountOpen"
                    x-transition
                    @click.outside="accountOpen = false"
                    class="absolute right-0 z-50 mt-2 w-72 rounded-[1.4rem] border border-[#E3D6C5] bg-[rgba(255,253,250,0.98)] p-2 shadow-xl space-y-1"
                    style="display: none;"
                >
                    @if ($canAccessCrm)
                        <div class="rounded-2xl border border-[#E3D6C5] bg-[#F8F0E2] px-3 py-3 text-sm text-[#23483F]">
                            <p class="font-semibold text-[#23483F]">{{ $user->name }}</p>
                            <p class="mt-1 text-xs text-[#6E746F]">{{ $user->email }}</p>
                            <p class="mt-2 text-xs font-medium uppercase tracking-[0.14em] text-[#6E746F]">{{ $isAdmin ? 'Admin account' : 'Sales account' }}</p>
                        </div>
                    @elseif ($isSdr)
                        <div class="rounded-2xl border border-[#E3D6C5] bg-[#F8F0E2] px-3 py-3 text-sm text-[#23483F]">
                            <p class="font-semibold text-[#23483F]">{{ $user->name }}</p>
                            <p class="mt-1 text-xs text-[#6E746F]">{{ $user->email }}</p>
                            <p class="mt-2 text-xs font-medium uppercase tracking-[0.14em] text-[#6E746F]">SDR account</p>
                        </div>
                        <a href="{{ route('sdr.calling') }}" wire:navigate class="block rounded-xl px-3 py-2 text-sm text-[#23483F] hover:bg-[#F8F0E2]">Call queue</a>
                    @else
                        @if ($isCaregiver && $caregiverOnboardingMode)
                            <a href="{{ route('caregiver.setup.index') }}" wire:navigate class="block rounded-xl px-3 py-2 text-sm text-[#23483F] hover:bg-[#F8F0E2]">Setup Hub</a>
                        @endif
                        <a href="{{ $myProfileHref }}" wire:navigate class="block rounded-xl px-3 py-2 text-sm text-[#23483F] hover:bg-[#F8F0E2]">{{ $myProfileLabel }}</a>

                        @if ($publicProfileHref)
                            <a href="{{ $publicProfileHref }}" wire:navigate class="block rounded-xl px-3 py-2 text-sm text-[#23483F] hover:bg-[#F8F0E2]">View Public Profile</a>
                        @endif

                        <a href="{{ route('profile') }}" wire:navigate class="block rounded-xl px-3 py-2 text-sm text-[#23483F] hover:bg-[#F8F0E2]">Account Settings</a>
                        <a href="{{ $securityHref }}" class="block rounded-xl px-3 py-2 text-sm text-[#23483F] hover:bg-[#F8F0E2]">Change Password</a>
                        <a href="{{ route('support.index') }}" wire:navigate class="block rounded-xl px-3 py-2 text-sm text-[#23483F] hover:bg-[#F8F0E2]">{{ $supportUnread > 0 ? 'Support Center ('.$supportUnread.')' : 'Support Center' }}</a>
                        @if (! ($isCaregiver && $caregiverOnboardingMode))
                            <a href="{{ route('messages.index') }}" wire:navigate class="block rounded-xl px-3 py-2 text-sm text-[#23483F] hover:bg-[#F8F0E2]">
                                {{ $messageUnread > 0 ? 'Messages ('.$messageUnread.')' : 'Messages' }}
                            </a>
                        @endif
                        @if ($isFamily)
                            <a href="{{ route('family.billing.show') }}" wire:navigate class="block rounded-xl px-3 py-2 text-sm text-[#23483F] hover:bg-[#F8F0E2]">Billing & Payments</a>
                            <a href="{{ route('family.notifications.index') }}" wire:navigate class="block rounded-xl px-3 py-2 text-sm text-[#23483F] hover:bg-[#F8F0E2]">
                                {{ $notificationUnread > 0 ? 'Notifications ('.$notificationUnread.')' : 'Notifications' }}
                            </a>
                        @endif

                        @if ($isCaregiver && ! $caregiverOnboardingMode)
                            <a href="{{ route('caregiver.work-inbox.index') }}" wire:navigate class="block rounded-xl px-3 py-2 text-sm text-[#23483F] hover:bg-[#F8F0E2]">
                                {{ $invitationUnread > 0 ? 'Work Inbox ('.$invitationUnread.')' : 'Work Inbox' }}
                            </a>
                            <a href="{{ route('caregiver.notifications.index') }}" wire:navigate class="block rounded-xl px-3 py-2 text-sm text-[#23483F] hover:bg-[#F8F0E2]">
                                {{ $notificationUnread > 0 ? 'Notifications ('.$notificationUnread.')' : 'Notifications' }}
                            </a>
                            <a href="{{ route('caregiver.shifts.index') }}" wire:navigate class="block rounded-xl px-3 py-2 text-sm text-[#23483F] hover:bg-[#F8F0E2]">
                                My Visits
                            </a>
                            <a href="{{ route('caregiver.earnings.index') }}" wire:navigate class="block rounded-xl px-3 py-2 text-sm text-[#23483F] hover:bg-[#F8F0E2]">
                                My Earnings
                            </a>
                            <a href="{{ route('caregiver.payouts.connect.show') }}" wire:navigate class="block rounded-xl px-3 py-2 text-sm text-[#23483F] hover:bg-[#F8F0E2]">
                                Payout Setup
                            </a>
                            <a href="{{ route('caregiver.verification.show') }}" wire:navigate class="block rounded-xl px-3 py-2 text-sm text-[#23483F] hover:bg-[#F8F0E2]">
                                {{ $identityApproved ? 'Identity Verified' : 'Verify Identity' }}
                            </a>
                            <a href="{{ route('caregiver.invitations.index') }}" wire:navigate class="block rounded-xl px-3 py-2 text-sm text-[#23483F] hover:bg-[#F8F0E2]">
                                {{ $invitationUnread > 0 ? 'Invitations ('.$invitationUnread.')' : 'Invitations' }}
                            </a>
                        @endif
                    @endif

                    <button wire:click="logout" class="w-full rounded-xl px-3 py-2 text-left text-sm text-rose-700 hover:bg-rose-50">Log Out</button>
                </div>
            @else
                <div class="flex items-center gap-2">
                    <a href="{{ route('landing.family') }}" class="inline-flex items-center rounded-xl border border-[#E3D6C5] bg-[rgba(255,253,250,0.96)] px-3 py-2 text-sm text-[#23483F] hover:bg-[#F8F0E2]">Families</a>
                    <a href="{{ route('landing.caregiver') }}" class="inline-flex items-center rounded-xl border border-[#E3D6C5] bg-[rgba(255,253,250,0.96)] px-3 py-2 text-sm text-[#23483F] hover:bg-[#F8F0E2]">Caregivers</a>
                    <a href="{{ route('login') }}" class="inline-flex items-center rounded-xl bg-[#23483F] px-3 py-2 text-sm font-semibold text-[#FFFBF4] shadow-sm hover:bg-[#1B3D35]">Sign in</a>
                </div>
            @endif
        </div>

    </div>

    <div
        x-cloak
        x-show="open"
        x-transition.opacity
        class="sm:hidden border-t border-[#E3D6C5]/80 bg-[rgba(255,253,250,0.98)] backdrop-blur"
    >
        <div class="space-y-1 px-2 pb-3 pt-2">
            @if ($canAccessCrm)
                <x-responsive-nav-link :href="$adminCrmLink['href']" :active="$adminCrmLink['active']" wire:navigate>
                    {{ __($adminCrmLink['label']) }}
                </x-responsive-nav-link>

                @if ($isAdmin)
                    @foreach ($adminNavGroups as $group)
                        <div class="px-2 pt-3 text-xs font-semibold uppercase tracking-[0.14em] text-[#6E746F]">{{ $group['label'] }}</div>
                        @foreach ($group['items'] as $item)
                            <x-responsive-nav-link :href="$item['href']" :active="$item['active']" wire:navigate>
                                {{ __($item['label']) }}
                            </x-responsive-nav-link>
                        @endforeach
                    @endforeach
                @endif
            @elseif ($isSdr)
                <x-responsive-nav-link :href="$sdrCallLink['href']" :active="$sdrCallLink['active']" wire:navigate>
                    {{ __($sdrCallLink['label']) }}
                </x-responsive-nav-link>
            @else
                @foreach ($primaryLinks as $link)
                    @if (!empty($link['primary']))
                        <a
                            href="{{ $link['href'] }}"
                            wire:navigate
                            class="mt-2 flex min-h-12 items-center justify-center rounded-2xl bg-[#23483F] px-4 text-base font-semibold text-[#FFFBF4] shadow-sm"
                        >
                            {{ __($link['label']) }}
                        </a>
                    @else
                        <x-responsive-nav-link :href="$link['href']" :active="$link['active']" wire:navigate>
                            {{ __($link['label']) }}
                        </x-responsive-nav-link>
                    @endif
                @endforeach
            @endif
        </div>

        <div class="border-t border-[#E3D6C5]/80 pb-3 pt-4">
            @if ($user)
                <div class="px-4">
                    <div class="font-medium text-base text-[#23483F]">{{ $user->name }}</div>
                    <div class="font-medium text-sm text-[#6E746F]">{{ $user->email }}</div>
                    <div class="mt-1 text-xs font-medium text-[#6E746F]">{{ ucfirst((string) $user->role) }}</div>
                </div>

                <div class="mt-3 space-y-1 px-2">
                    @if ($isSdr)
                        <x-responsive-nav-link :href="route('sdr.calling')" wire:navigate>{{ __('Call queue') }}</x-responsive-nav-link>
                        <x-responsive-nav-link :href="$myProfileHref" wire:navigate>{{ __($myProfileLabel) }}</x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('profile')" wire:navigate>{{ __('Account Settings') }}</x-responsive-nav-link>
                    @elseif (! $canAccessCrm)
                        @if ($isCaregiver && $caregiverOnboardingMode)
                            <x-responsive-nav-link :href="route('caregiver.setup.index')" wire:navigate>{{ __('Setup Hub') }}</x-responsive-nav-link>
                        @endif
                        <x-responsive-nav-link :href="$myProfileHref" wire:navigate>{{ __($myProfileLabel) }}</x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('profile')" wire:navigate>{{ __('Account Settings') }}</x-responsive-nav-link>
                        <x-responsive-nav-link :href="$securityHref">{{ __('Change Password') }}</x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('support.index')" wire:navigate>{{ $supportUnread > 0 ? __('Support Center').' ('.$supportUnread.')' : __('Support Center') }}</x-responsive-nav-link>
                        @if (! ($isCaregiver && $caregiverOnboardingMode))
                            <x-responsive-nav-link :href="route('messages.index')" wire:navigate>
                                {{ $messageUnread > 0 ? __('Messages').' ('.$messageUnread.')' : __('Messages') }}
                            </x-responsive-nav-link>
                        @endif
                        @if ($isFamily)
                            <x-responsive-nav-link :href="route('family.billing.show')" wire:navigate>{{ __('Billing & Payments') }}</x-responsive-nav-link>
                            <x-responsive-nav-link :href="route('family.notifications.index')" wire:navigate>
                                {{ $notificationUnread > 0 ? __('Notifications').' ('.$notificationUnread.')' : __('Notifications') }}
                            </x-responsive-nav-link>
                        @endif
                        @if ($isCaregiver && ! $caregiverOnboardingMode)
                            <x-responsive-nav-link :href="route('caregiver.work-inbox.index')" wire:navigate>
                                {{ $invitationUnread > 0 ? __('Work Inbox').' ('.$invitationUnread.')' : __('Work Inbox') }}
                            </x-responsive-nav-link>
                            <x-responsive-nav-link :href="route('caregiver.notifications.index')" wire:navigate>
                                {{ $notificationUnread > 0 ? __('Notifications').' ('.$notificationUnread.')' : __('Notifications') }}
                            </x-responsive-nav-link>
                            <x-responsive-nav-link :href="route('caregiver.shifts.index')" wire:navigate>
                                {{ __('My Visits') }}
                            </x-responsive-nav-link>
                            <x-responsive-nav-link :href="route('caregiver.earnings.index')" wire:navigate>
                                {{ __('My Earnings') }}
                            </x-responsive-nav-link>
                            <x-responsive-nav-link :href="route('caregiver.payouts.connect.show')" wire:navigate>
                                {{ __('Payout Setup') }}
                            </x-responsive-nav-link>
                            <x-responsive-nav-link :href="route('caregiver.verification.show')" wire:navigate>
                                {{ $identityApproved ? __('Identity Verified') : __('Verify Identity') }}
                            </x-responsive-nav-link>
                        @endif
                    @endif
                    <button wire:click="logout" class="w-full text-start">
                        <x-responsive-nav-link>{{ __('Log Out') }}</x-responsive-nav-link>
                    </button>
                </div>
            @else
                <div class="space-y-1 px-2">
                    <x-responsive-nav-link :href="route('landing.family')" wire:navigate>{{ __('Families') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('landing.caregiver')" wire:navigate>{{ __('Caregivers') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('login')" wire:navigate>{{ __('Sign in') }}</x-responsive-nav-link>
                </div>
            @endif
        </div>
    </div>
</nav>

