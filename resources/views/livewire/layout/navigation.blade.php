<?php

use App\Livewire\Actions\Logout;
use App\Models\CareRequestConversation;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    @php
        $user = auth()->user();
        $isCaregiver = $user?->role === 'caregiver';
        $isFamily = $user?->role === 'family';
        $isAdmin = $user && strtolower($user->email) === 'test@test.com';
        $messageUnread = 0;

        if (\Illuminate\Support\Facades\Schema::hasTable('care_request_conversations')) {
            if ($isFamily) {
                $messageUnread = CareRequestConversation::query()
                    ->where('family_user_id', $user->id)
                    ->whereNotNull('last_message_at')
                    ->where('last_message_sender_id', '!=', $user->id)
                    ->where(function ($query) {
                        $query->whereNull('family_last_read_at')
                            ->orWhereColumn('last_message_at', '>', 'family_last_read_at');
                    })
                    ->count();
            } elseif ($isCaregiver) {
                $messageUnread = CareRequestConversation::query()
                    ->where('caregiver_user_id', $user->id)
                    ->whereNotNull('last_message_at')
                    ->where('last_message_sender_id', '!=', $user->id)
                    ->where(function ($query) {
                        $query->whereNull('caregiver_last_read_at')
                            ->orWhereColumn('last_message_at', '>', 'caregiver_last_read_at');
                    })
                    ->count();
            }
        }

        $primaryLinks = [
            [
                'label' => 'Dashboard',
                'href' => route('dashboard'),
                'active' => request()->routeIs('dashboard'),
            ],
            [
                'label' => $messageUnread > 0 ? "Messages ($messageUnread)" : 'Messages',
                'href' => route('messages.index'),
                'active' => request()->routeIs('messages.*'),
            ],
        ];

        if ($isFamily) {
            $primaryLinks[] = [
                'label' => 'My Requests',
                'href' => route('family.requests.index'),
                'active' => request()->routeIs('family.requests.index') || request()->routeIs('family.requests.show'),
            ];
            $primaryLinks[] = [
                'label' => 'Post Request',
                'href' => route('family.requests.create'),
                'active' => request()->routeIs('family.requests.create'),
            ];
            $primaryLinks[] = [
                'label' => 'Find Caregivers',
                'href' => route('caregivers.search'),
                'active' => request()->routeIs('caregivers.search') || request()->routeIs('caregivers.show'),
            ];
        }

        if ($isCaregiver) {
            $primaryLinks[] = [
                'label' => 'Open Requests',
                'href' => route('care-requests.index'),
                'active' => request()->routeIs('care-requests.*'),
            ];
            $primaryLinks[] = [
                'label' => 'My Caregiver Profile',
                'href' => route('caregiver.profile.edit'),
                'active' => request()->routeIs('caregiver.profile.edit') || request()->routeIs('caregiver.onboarding'),
            ];
        }

        if ($isAdmin) {
            $primaryLinks[] = [
                'label' => 'Admin Reviews',
                'href' => route('admin.caregivers.reviews'),
                'active' => request()->routeIs('admin.caregivers.reviews'),
            ];
        }
    @endphp

    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" wire:navigate class="inline-flex items-center gap-2">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                        <span class="hidden md:inline text-sm font-semibold text-slate-700">HomeCare</span>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    @foreach ($primaryLinks as $link)
                        <x-nav-link :href="$link['href']" :active="$link['active']" wire:navigate>
                            {{ __($link['label']) }}
                        </x-nav-link>
                    @endforeach
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-3 px-3 py-2 border border-slate-200 text-sm leading-4 font-medium rounded-lg text-slate-600 bg-white hover:text-slate-800 hover:bg-slate-50 focus:outline-none transition ease-in-out duration-150">
                            <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-100 text-blue-700 text-xs font-semibold">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </span>

                            <div class="text-left">
                                <p class="text-sm font-semibold text-slate-800" x-data="{{ json_encode(['name' => $user->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></p>
                                <p class="text-xs text-slate-500">{{ ucfirst((string) $user->role) }}</p>
                            </div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="px-4 py-3 border-b border-slate-100">
                            <p class="text-sm font-semibold text-slate-800">{{ $user->name }}</p>
                            <p class="text-xs text-slate-500">{{ $user->email }}</p>
                        </div>

                        <x-dropdown-link :href="route('profile')" wire:navigate>
                            {{ __('Account Settings') }}
                        </x-dropdown-link>

                        <x-dropdown-link :href="route('messages.index')" wire:navigate>
                            {{ $messageUnread > 0 ? __('Messages').' ('.$messageUnread.')' : __('Messages') }}
                        </x-dropdown-link>

                        @if ($isCaregiver)
                            <x-dropdown-link :href="route('caregiver.profile.edit')" wire:navigate>
                                {{ __('Caregiver Profile') }}
                            </x-dropdown-link>
                        @endif

                        @if ($isFamily)
                            <x-dropdown-link :href="route('family.requests.index')" wire:navigate>
                                {{ __('Family Requests') }}
                            </x-dropdown-link>
                        @endif

                        @if ($isAdmin)
                            <x-dropdown-link :href="route('admin.caregivers.reviews')" wire:navigate>
                                {{ __('Admin Reviews') }}
                            </x-dropdown-link>
                        @endif

                        <!-- Authentication -->
                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link>
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            @foreach ($primaryLinks as $link)
                <x-responsive-nav-link :href="$link['href']" :active="$link['active']" wire:navigate>
                    {{ __($link['label']) }}
                </x-responsive-nav-link>
            @endforeach
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800" x-data="{{ json_encode(['name' => $user->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                <div class="font-medium text-sm text-gray-500">{{ $user->email }}</div>
                <div class="font-medium text-xs text-gray-500 mt-1">{{ ucfirst((string) $user->role) }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile')" wire:navigate>
                    {{ __('Account Settings') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('messages.index')" wire:navigate>
                    {{ $messageUnread > 0 ? __('Messages').' ('.$messageUnread.')' : __('Messages') }}
                </x-responsive-nav-link>

                @if ($isCaregiver)
                    <x-responsive-nav-link :href="route('caregiver.profile.edit')" wire:navigate>
                        {{ __('Caregiver Profile') }}
                    </x-responsive-nav-link>
                @endif

                @if ($isFamily)
                    <x-responsive-nav-link :href="route('family.requests.index')" wire:navigate>
                        {{ __('Family Requests') }}
                    </x-responsive-nav-link>
                @endif

                @if ($isAdmin)
                    <x-responsive-nav-link :href="route('admin.caregivers.reviews')" wire:navigate>
                        {{ __('Admin Reviews') }}
                    </x-responsive-nav-link>
                @endif

                <!-- Authentication -->
                <button wire:click="logout" class="w-full text-start">
                    <x-responsive-nav-link>
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </button>
            </div>
        </div>
    </div>
</nav>
