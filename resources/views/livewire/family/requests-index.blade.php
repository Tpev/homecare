<div>
    <div class="max-w-6xl mx-auto py-8 space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">My care requests</h1>
                <p class="text-sm text-slate-600">Track applicants, shortlist, and hire caregivers.</p>
            </div>
            <a href="{{ route('family.requests.create') }}" wire:navigate>
                <x-button color="blue">New request</x-button>
            </a>
        </div>

        @if (session('status'))
            <x-alert color="green">{{ session('status') }}</x-alert>
        @endif

        <x-card>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-select.styled label="Status" wire:model.live="status" :options="$statusOptions" />
                <x-select.styled label="Sort" wire:model.live="sort" :options="$sortOptions" />
            </div>
        </x-card>

        <div class="grid grid-cols-1 gap-4">
            @forelse ($requests as $request)
                <x-card>
                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-1">
                            <p class="font-semibold text-slate-900">{{ $request->title }}</p>
                            <p class="text-sm text-slate-600">
                                {{ $request->city }}, {{ $request->state }} - {{ optional($request->requested_start_at)->format('M d, Y H:i') }}
                            </p>
                            <p class="text-sm text-slate-500">
                                Recipient: {{ $request->recipient?->full_name ?? 'Not set' }}
                            </p>
                        </div>
                        <x-badge :text="strtoupper($request->status)" color="blue" />
                    </div>

                    <div class="mt-4 flex items-center justify-between">
                        <p class="text-sm text-slate-600">{{ $request->applications_count }} applicant(s)</p>
                        <a href="{{ route('family.requests.show', $request->id) }}" wire:navigate class="text-sm underline text-blue-700">
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
