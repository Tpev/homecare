<?php

namespace App\Livewire\Support;

use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\SupportTicket;
use App\Services\AiSupport\AiSupportPreparationService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class TicketsCenter extends Component
{
    public string $subject = '';

    public string $description = '';

    public string $category = 'general';

    public string $priority = 'normal';

    public ?int $care_request_id = null;

    public ?int $care_booking_id = null;

    public bool $aiPrepared = false;

    public function mount(): void
    {
        $user = auth()->user();
        if (! $user || $user->role !== 'family') {
            return;
        }
        $prepared = app(AiSupportPreparationService::class)->consume($user, 'support_intake_v1');
        $this->subject = (string) ($prepared['subject'] ?? $this->subject);
        $this->description = (string) ($prepared['description'] ?? $this->description);
        $category = (string) ($prepared['category'] ?? 'general');
        if (in_array($category, ['general', 'dispute', 'incident', 'cancellation', 'billing', 'time_correction'], true)) {
            $this->category = $category;
        }
        if (($prepared['resource_type'] ?? null) === 'care_request') {
            $this->care_request_id = (int) ($prepared['resource_id'] ?? 0) ?: null;
        }
        if (($prepared['resource_type'] ?? null) === 'care_booking') {
            $this->care_booking_id = (int) ($prepared['resource_id'] ?? 0) ?: null;
        }
        $this->aiPrepared = $prepared !== [];
    }

    public function createTicket(): void
    {
        $user = auth()->user();
        $this->validate([
            'subject' => ['required', 'string', 'min:8', 'max:160'],
            'description' => ['required', 'string', 'min:12', 'max:4000'],
            'category' => ['required', Rule::in(['general', 'dispute', 'incident', 'cancellation', 'billing', 'time_correction'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'care_request_id' => ['nullable', 'integer'],
            'care_booking_id' => ['nullable', 'integer'],
        ]);

        if ($user->role === 'family' && $this->category === 'billing'
            && ! app(FamilyAccountContext::class)->isOwner($user)) {
            throw ValidationException::withMessages(['category' => 'Only the family account owner can open billing requests.']);
        }

        $request = $this->care_request_id ? CareRequest::query()
            ->whereKey($this->care_request_id)
            ->when($user->role === 'caregiver', fn ($query) => $query
                ->whereHas('applications', fn ($applications) => $applications->where('caregiver_user_id', $user->id)))
            ->first() : null;
        $booking = $this->care_booking_id ? CareBooking::query()
            ->whereKey($this->care_booking_id)
            ->when($user->role === 'caregiver', fn ($query) => $query->where('caregiver_user_id', $user->id))
            ->first() : null;
        if (($this->care_request_id && ! $request) || ($this->care_booking_id && ! $booking)) {
            throw ValidationException::withMessages(['care_request_id' => 'Choose a care record you can access.']);
        }

        $account = $user->role === 'family' ? app(FamilyAccountContext::class)->account($user) : null;

        $ticket = SupportTicket::query()->create([
            'family_account_id' => $account?->id,
            'family_visibility' => $this->category === 'billing' ? 'owner_only' : 'shared_care',
            'opener_user_id' => auth()->id(),
            'care_request_id' => $this->care_request_id ?: null,
            'care_booking_id' => $this->care_booking_id ?: null,
            'category' => $this->category,
            'priority' => $this->priority,
            'subject' => trim($this->subject),
            'description' => trim($this->description),
        ]);

        $this->reset(['subject', 'description', 'category', 'priority', 'care_request_id', 'care_booking_id']);
        $this->category = 'general';
        $this->priority = 'normal';

        session()->flash('status', 'Support ticket created. You can continue the conversation here.');
        $this->redirect(route('support.tickets.show', $ticket, absolute: false), navigate: true);
    }

    public function render()
    {
        $user = auth()->user();
        $tickets = SupportTicket::query()
            ->with(['latestPublicMessage.sender:id,name,role', 'familyReads' => fn ($query) => $query->where('user_id', $user->id)])
            ->when($user->role === 'family', function ($query) use ($user): void {
                $context = app(FamilyAccountContext::class);
                $account = $context->account($user);
                $isOwner = $context->isOwner($user);
                $query->where(function ($tickets) use ($user, $account, $isOwner): void {
                    $tickets->where(function ($legacy) use ($user): void {
                        $legacy->whereNull('family_account_id')
                            ->where('opener_user_id', $user->id);
                    })->orWhere(function ($accountTickets) use ($account, $isOwner, $user): void {
                        $accountTickets->where('family_account_id', $account->id)
                            ->where(function ($visibility) use ($isOwner, $user): void {
                                $visibility->where('family_visibility', 'shared_care');
                                if ($isOwner) {
                                    $visibility->orWhere('family_visibility', 'owner_only');
                                }
                                $visibility->orWhere(function ($private) use ($user): void {
                                    $private->where('family_visibility', 'opener_only')
                                        ->where('opener_user_id', $user->id);
                                });
                            });
                    });
                });
            }, fn ($query) => $query->where('opener_user_id', $user->id))
            ->orderByRaw('COALESCE(last_public_message_at, created_at) DESC')
            ->get()
            ->each(function (SupportTicket $ticket): void {
                $ticket->is_unread_for_opener = $ticket->isUnreadFor(auth()->user());
            });

        $requestOptions = CareRequest::query()
            ->when($user->role === 'caregiver', fn ($query) => $query
                ->whereHas('applications', fn ($applications) => $applications->where('caregiver_user_id', $user->id)))
            ->latest()
            ->limit(40)
            ->get(['id', 'title'])
            ->map(fn (CareRequest $request) => ['label' => '#'.$request->id.' '.$request->title, 'value' => $request->id])
            ->all();

        $bookingOptions = CareBooking::query()
            ->when($user->role === 'caregiver', fn ($query) => $query->where('caregiver_user_id', $user->id))
            ->latest()
            ->limit(40)
            ->get(['id', 'care_request_id'])
            ->map(fn (CareBooking $booking) => ['label' => 'Booking #'.$booking->id.' (request #'.$booking->care_request_id.')', 'value' => $booking->id])
            ->all();

        return view('livewire.support.tickets-center', compact('tickets', 'requestOptions', 'bookingOptions'));
    }
}
