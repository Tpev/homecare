<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-display text-2xl font-semibold text-slate-900">
                    Account Settings
                </h2>
                <p class="text-sm text-slate-600 mt-1">Manage your profile, password, and account security.</p>
            </div>
        </div>
    </x-slot>

    <div class="hc-page py-8 space-y-6">
        <section id="account-info" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="max-w-2xl">
                <livewire:profile.update-profile-information-form />
            </div>
        </section>

        <section id="password-security" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="max-w-2xl">
                <livewire:profile.update-password-form />
            </div>
        </section>

        @if (auth()->user()?->role === 'family')
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-medium text-gray-900">Care profiles</h2>
                <p class="mt-1 text-sm text-gray-600">Describe the people receiving care and choose what caregivers can see.</p>
                <a href="{{ route('family.care-profiles.index') }}" wire:navigate class="mt-4 inline-flex min-h-11 items-center rounded-xl bg-[#17313F] px-4 text-sm font-semibold text-white">Manage care profiles</a>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-medium text-gray-900">Family access</h2>
                <p class="mt-1 text-sm text-gray-600">See who can help manage care, or leave the family account if you are a member.</p>
                <a href="{{ route('family.access') }}" wire:navigate class="mt-4 inline-flex min-h-11 items-center rounded-xl bg-[#17313F] px-4 text-sm font-semibold text-white">Manage family access</a>
            </section>
        @else
            <section id="danger-zone" class="rounded-2xl border border-red-200 bg-white p-5 shadow-sm">
                <div class="max-w-2xl">
                    <livewire:profile.delete-user-form />
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
