<?php

namespace App\Livewire\Support;

use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\SupportTicket;
use Illuminate\Validation\Rule;
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

    public function createTicket(): void
    {
        $this->validate([
            'subject' => ['required', 'string', 'min:8', 'max:160'],
            'description' => ['required', 'string', 'min:12', 'max:4000'],
            'category' => ['required', Rule::in(['general', 'dispute', 'incident', 'cancellation', 'billing', 'time_correction'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'care_request_id' => ['nullable', 'integer', Rule::exists('care_requests', 'id')],
            'care_booking_id' => ['nullable', 'integer', Rule::exists('care_bookings', 'id')],
        ]);

        $ticket = SupportTicket::query()->create([
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
        $tickets = SupportTicket::query()
            ->with(['latestPublicMessage.sender:id,name,role'])
            ->where('opener_user_id', auth()->id())
            ->orderByRaw('COALESCE(last_public_message_at, created_at) DESC')
            ->get()
            ->each(function (SupportTicket $ticket): void {
                $ticket->is_unread_for_opener = $ticket->isUnreadForOpener();
            });

        $requestOptions = CareRequest::query()
            ->where('family_user_id', auth()->id())
            ->orWhereHas('applications', fn ($query) => $query->where('caregiver_user_id', auth()->id()))
            ->latest()
            ->limit(40)
            ->get(['id', 'title'])
            ->map(fn (CareRequest $request) => ['label' => '#'.$request->id.' '.$request->title, 'value' => $request->id])
            ->all();

        $bookingOptions = CareBooking::query()
            ->where('family_user_id', auth()->id())
            ->orWhere('caregiver_user_id', auth()->id())
            ->latest()
            ->limit(40)
            ->get(['id', 'care_request_id'])
            ->map(fn (CareBooking $booking) => ['label' => 'Booking #'.$booking->id.' (request #'.$booking->care_request_id.')', 'value' => $booking->id])
            ->all();

        return view('livewire.support.tickets-center', compact('tickets', 'requestOptions', 'bookingOptions'));
    }
}
