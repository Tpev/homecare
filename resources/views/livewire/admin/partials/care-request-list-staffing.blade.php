@php
    $requestTone = match ($request->status) {
        \App\Models\CareRequest::STATUS_OPEN => 'blue',
        \App\Models\CareRequest::STATUS_FILLED => 'indigo',
        \App\Models\CareRequest::STATUS_CANCELLED => 'red',
        \App\Models\CareRequest::STATUS_EXPIRED => 'amber',
        default => 'slate',
    };
    $bookingTone = match ($request->booking?->status) {
        \App\Models\CareBooking::STATUS_IN_PROGRESS,
        \App\Models\CareBooking::STATUS_PAUSED => 'green',
        \App\Models\CareBooking::STATUS_COMPLETED,
        \App\Models\CareBooking::STATUS_REVIEWED => 'blue',
        \App\Models\CareBooking::STATUS_CANCELLED => 'red',
        \App\Models\CareBooking::STATUS_DISPUTED => 'amber',
        default => 'slate',
    };
@endphp
<div class="space-y-2">
    <div class="flex flex-wrap items-center gap-1.5">
        <x-badge :text="strtoupper((string) $request->status)" :color="$requestTone" />
        @if ($request->is_private)
            <x-badge text="PRIVATE" color="indigo" />
        @endif
        @if ($request->booking)
            <x-badge :text="'BOOKING #'.$request->booking->id.' '.strtoupper((string) $request->booking->status)" :color="$bookingTone" />
        @endif
    </div>
    <p class="text-sm font-semibold text-slate-900">{{ $request->applications_count }} {{ $request->applications_count === 1 ? 'applicant' : 'applicants' }}</p>
    @if ($request->booking)
        <p class="text-xs text-slate-600">Caregiver: <span class="font-medium text-slate-900">{{ $request->booking->caregiver?->name ?: 'Unknown' }}</span></p>
    @endif
    <p class="text-xs leading-relaxed text-slate-500">{{ $request->invitations_count }} invited · {{ $request->conversations_count }} chats · {{ $request->tasks_count }} tasks</p>
</div>
