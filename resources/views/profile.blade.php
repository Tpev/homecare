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

        <section id="danger-zone" class="rounded-2xl border border-red-200 bg-white p-5 shadow-sm">
            <div class="max-w-2xl">
                <livewire:profile.delete-user-form />
            </div>
        </section>
    </div>
</x-app-layout>
