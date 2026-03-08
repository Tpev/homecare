<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-display text-2xl font-semibold text-slate-900">Identity Verification</h2>
                <p class="text-sm text-slate-600 mt-1">Verify your ID and selfie through Didit to unlock activation.</p>
            </div>
            <a href="{{ route('caregiver.profile.edit') }}">
                <x-button color="slate" light sm>Back to profile</x-button>
            </a>
        </div>
    </x-slot>

    @php
        $statusLabel = match ($profile->identity_verification_status) {
            'approved' => 'Approved',
            'in_review' => 'In review',
            'in_progress' => 'In progress',
            'declined' => 'Declined',
            'abandoned' => 'Abandoned',
            'expired' => 'Expired',
            'error' => 'Error',
            default => 'Not started',
        };

        $statusColor = match ($profile->identity_verification_status) {
            'approved' => 'green',
            'in_review' => 'yellow',
            'in_progress' => 'blue',
            'declined', 'abandoned', 'expired', 'error' => 'red',
            default => 'slate',
        };
    @endphp

    <div class="hc-page py-8 space-y-6">
        @if (session('status'))
            <x-alert color="green">{{ session('status') }}</x-alert>
        @endif

        @if ($errors->has('verification'))
            <x-alert color="red">{{ $errors->first('verification') }}</x-alert>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-slate-500">Current verification status</p>
                    <div class="mt-1 flex items-center gap-2">
                        <x-badge :color="$statusColor" :text="$statusLabel" />
                        @if ($profile->identity_verified_at)
                            <span class="text-xs text-slate-500">Verified on {{ $profile->identity_verified_at->format('M d, Y H:i') }}</span>
                        @endif
                    </div>
                </div>

                <form method="POST" action="{{ route('caregiver.verification.session') }}">
                    @csrf
                    <x-button color="blue">
                        {{ $profile->identity_verification_status === 'approved' ? 'Start new verification' : 'Start verification' }}
                    </x-button>
                </form>
            </div>

            <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                You will be redirected to Didit to scan your ID and take a selfie. Results are synced automatically in a few seconds.
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="font-semibold text-slate-900">Recent attempts</h3>

            <div class="mt-4 space-y-3">
                @forelse ($recentAttempts as $attempt)
                    <div class="rounded-xl border border-slate-200 p-3 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-slate-900">Session {{ $attempt->didit_session_id }}</p>
                            <p class="text-xs text-slate-500">
                                Started {{ optional($attempt->started_at)->format('M d, Y H:i') ?? '-' }}
                                @if ($attempt->last_webhook_at)
                                    • Last update {{ $attempt->last_webhook_at->format('M d, Y H:i') }}
                                @endif
                            </p>
                        </div>
                        <x-badge
                            :text="strtoupper(str_replace('_', ' ', (string) $attempt->status))"
                            :color="$attempt->status === 'approved' ? 'green' : ($attempt->status === 'in_review' ? 'yellow' : ($attempt->status === 'in_progress' ? 'blue' : ($attempt->status === 'not_started' ? 'slate' : 'red')))"
                        />
                    </div>
                @empty
                    <p class="text-sm text-slate-600">No verification attempts yet.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>

