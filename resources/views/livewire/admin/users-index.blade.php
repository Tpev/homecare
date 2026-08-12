<div class="hc-page py-8 space-y-6">
    <x-card>
        <x-slot:header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold">User Management</h1>
                    <p class="mt-1 text-sm text-slate-600">All accounts on the platform with role, registration date, and quick delete action.</p>
                </div>
                <div class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs text-slate-500">Total: {{ $users->total() }}</div>
            </div>
        </x-slot:header>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <x-input
                label="Search"
                placeholder="Name, email, city, state"
                wire:model.blur="q"
            />

            <x-select.styled
                label="User type"
                wire:model.live="role"
                :options="$roleOptions"
            />

            <x-select.styled
                label="Rows per page"
                wire:model.live="perPage"
                :options="[
                    ['label' => '25', 'value' => 25],
                    ['label' => '50', 'value' => 50],
                    ['label' => '100', 'value' => 100],
                ]"
            />
        </div>

        @error('delete')
            <x-alert color="red" class="mt-3">{{ $message }}</x-alert>
        @enderror
        @error('loginAs')
            <x-alert color="red" class="mt-3">{{ $message }}</x-alert>
        @enderror

        <div class="mt-5 space-y-3 md:hidden">
            @forelse($users as $user)
                @php
                    $isProtectedStaff = in_array($user->role, ['admin', 'sales', 'sdr'], true);
                    $readiness = $reviewReadiness[$user->id] ?? null;
                    $statusClasses = match ($readiness['status'] ?? 'missing') {
                        'active' => 'text-emerald-700 bg-emerald-50 border-emerald-200',
                        'under_review' => 'text-cyan-700 bg-cyan-50 border-cyan-200',
                        'ready' => 'text-indigo-700 bg-indigo-50 border-indigo-200',
                        default => 'text-amber-700 bg-amber-50 border-amber-200',
                    };
                @endphp
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-base font-semibold text-slate-900">{{ $user->name }}</p>
                            <p class="truncate text-sm text-slate-500">{{ $user->email }}</p>
                        </div>
                        <x-badge :text="strtoupper((string) ($user->role ?: 'user'))" color="blue" />
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-[11px] uppercase tracking-[0.14em] text-slate-500">Registered</p>
                            <p class="mt-1 font-semibold text-slate-900">{{ optional($user->created_at)->format('M d, Y') ?: '—' }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-[11px] uppercase tracking-[0.14em] text-slate-500">Location</p>
                            <p class="mt-1 font-semibold text-slate-900">{{ $user->city ?: '—' }}{{ $user->state ? ', '.$user->state : '' }}</p>
                        </div>
                    </div>

                    @if((string) $user->role === 'caregiver')
                        <div class="mt-3 rounded-xl border px-3 py-2 text-sm {{ $statusClasses }}">
                            <p class="text-[11px] uppercase tracking-[0.14em]">Review readiness</p>
                            @if($readiness)
                                <p class="mt-1 font-semibold">{{ $readiness['status_label'] }}</p>
                                @if(($readiness['missing'] ?? []) !== [])
                                    <p class="mt-1 text-xs text-rose-700">Missing: {{ implode(', ', $readiness['missing']) }}</p>
                                @endif
                            @else
                                <p class="mt-1 font-semibold">No profile data</p>
                            @endif
                        </div>
                    @endif

                    <div class="mt-4 grid grid-cols-1 gap-2">
                        <a href="{{ route('admin.users.show', $user) }}" wire:navigate class="block">
                            <x-button color="cyan" light class="w-full justify-center">View profile</x-button>
                        </a>
                        @if($isProtectedStaff)
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-center text-xs text-slate-500">Protected account</div>
                        @else
                            <x-button color="amber" light class="w-full justify-center" wire:click="loginAs({{ $user->id }})" onclick="if (!confirm('Log in as this user now?')) return false;">Login as</x-button>
                            <x-button color="red" light class="w-full justify-center" wire:click="deleteUser({{ $user->id }})" onclick="if (!confirm('Delete this user account? This action cannot be undone.')) return false;">Delete</x-button>
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                    No users found for the current filters.
                </div>
            @endforelse
        </div>

        <div class="mt-4 hidden overflow-x-auto rounded-xl border border-slate-200 md:block">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Review readiness</th>
                        <th class="px-4 py-3">Location</th>
                        <th class="px-4 py-3">Registered</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($users as $user)
                        @php
                            $isProtectedStaff = in_array($user->role, ['admin', 'sales', 'sdr'], true);
                        @endphp
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-slate-900">{{ $user->name }}</p>
                                <p class="text-xs text-slate-500">{{ $user->email }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <x-badge :text="strtoupper((string) ($user->role ?: 'user'))" color="blue" />
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                @if((string) $user->role === 'caregiver')
                                    @php
                                        $readiness = $reviewReadiness[$user->id] ?? null;
                                        $statusClasses = match ($readiness['status'] ?? 'missing') {
                                            'active' => 'text-emerald-700',
                                            'under_review' => 'text-cyan-700',
                                            'ready' => 'text-indigo-700',
                                            default => 'text-amber-700',
                                        };
                                    @endphp
                                    @if($readiness)
                                        <p class="text-xs font-semibold uppercase tracking-[0.12em] {{ $statusClasses }}">
                                            {{ $readiness['status_label'] }}
                                        </p>
                                        @if(($readiness['missing'] ?? []) !== [])
                                            <p class="mt-1 text-xs text-rose-700">
                                                Missing: {{ implode(', ', $readiness['missing']) }}
                                            </p>
                                        @endif
                                    @else
                                        <p class="text-xs text-slate-500">No profile data</p>
                                    @endif
                                @else
                                    <span class="text-xs text-slate-500">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                {{ $user->city ?: '—' }}{{ $user->state ? ', '.$user->state : '' }}
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                {{ optional($user->created_at)->format('M d, Y H:i') }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.users.show', $user) }}" wire:navigate>
                                        <x-button color="cyan" light sm>View profile</x-button>
                                    </a>

                                    @if($isProtectedStaff)
                                        <span class="text-xs text-slate-500">Protected</span>
                                    @else
                                        <x-button
                                            color="amber"
                                            light
                                            sm
                                            wire:click="loginAs({{ $user->id }})"
                                            onclick="if (!confirm('Log in as this user now?')) return false;"
                                        >
                                            Login as
                                        </x-button>
                                        <x-button
                                            color="red"
                                            light
                                            sm
                                            wire:click="deleteUser({{ $user->id }})"
                                            onclick="if (!confirm('Delete this user account? This action cannot be undone.')) return false;"
                                        >
                                            Delete
                                        </x-button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-500">No users found for the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:footer>
            <div class="pt-2">
                {{ $users->links() }}
            </div>
        </x-slot:footer>
    </x-card>
</div>
