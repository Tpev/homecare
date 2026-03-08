<div>
    <div class="hc-page py-8 space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-display font-semibold">My care requests</h1>
                <p class="text-sm text-slate-600">Track applicants, shortlist, and hire caregivers.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('family.requests.create_ai') }}" wire:navigate>
                    <x-button color="emerald" icon="sparkles" position="left">New request with AI</x-button>
                </a>
                <a href="{{ route('family.requests.create') }}" wire:navigate>
                    <x-button color="blue" light>Manual form</x-button>
                </a>
            </div>
        </div>

        @if (session('status'))
            <x-alert color="green">{{ session('status') }}</x-alert>
        @endif

        <x-card>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-select.styled label="Status" wire:model.live="status" :options="$statusOptions" />
                <x-select.styled label="Type" wire:model.live="requestType" :options="$requestTypeOptions" />
                <x-select.styled label="Sort" wire:model.live="sort" :options="$sortOptions" />
            </div>
        </x-card>

        <div class="grid grid-cols-1 gap-4">
            @forelse ($requests as $request)
                <x-card>
                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-1">
                            <p class="font-display font-semibold text-slate-900">{{ $request->title }}</p>
                            <p class="text-sm text-slate-600">
                                {{ $request->city }}, {{ $request->state }}
                                @if ($request->request_type === \App\Models\CareRequest::TYPE_ONE_TIME)
                                    - {{ optional($request->requested_start_at)->format('M d, Y H:i') }}
                                @else
                                    - Recurring
                                @endif
                            </p>
                            <p class="text-sm text-slate-500">
                                Recipient: {{ $request->recipient?->full_name ?? 'Not set' }}
                            </p>
                        </div>
                        <x-badge :text="strtoupper($request->status)" color="blue" />
                    </div>

                    <div class="mt-4 flex items-center justify-between">
                        <p class="text-sm text-slate-600">{{ $request->applications_count }} applicant(s)</p>
                        <a href="{{ route('family.requests.show', $request->id) }}" wire:navigate class="hc-link">
                            Open request
                        </a>
                    </div>
                </x-card>
            @empty
                <x-card>
                    <p class="text-sm text-slate-600">No requests yet. Create your first one to start receiving applications.</p>
                </x-card>
            @endforelse
        </div>

        <div>{{ $requests->links() }}</div>
    </div>
</div>
