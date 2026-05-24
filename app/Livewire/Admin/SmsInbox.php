<?php

namespace App\Livewire\Admin;

use App\Models\SmsMessage;
use App\Services\Messaging\TwilioSmsClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

#[Layout('layouts.app')]
class SmsInbox extends Component
{
    use WithPagination;

    public string $q = '';

    public string $direction = 'all';

    public int $perPage = 25;

    public string $toPhone = '';

    public string $fromPhone = '';

    public string $messageBody = '';

    protected $queryString = [
        'q' => ['except' => ''],
        'direction' => ['except' => 'all'],
        'perPage' => ['except' => 25],
    ];

    public function mount(): void
    {
        $this->fromPhone = (string) config('services.twilio.sms_from');
    }

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function updatingDirection(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function replyTo(string $phone): void
    {
        $this->toPhone = TwilioSmsClient::normalizePhone($phone);
    }

    public function sendMessage(): void
    {
        $this->validate([
            'toPhone' => ['required', 'string', 'max:40'],
            'fromPhone' => ['nullable', 'string', 'max:40'],
            'messageBody' => ['required', 'string', 'min:1', 'max:1600'],
        ]);

        $to = TwilioSmsClient::normalizePhone($this->toPhone);
        $from = TwilioSmsClient::normalizePhone($this->fromPhone ?: (string) config('services.twilio.sms_from'));
        $body = trim($this->messageBody);

        if ($to === '') {
            $this->addError('toPhone', 'Enter a valid recipient phone number.');

            return;
        }

        if ($from === '') {
            $this->addError('send', 'Configure TWILIO_SMS_FROM or TWILIO_PHONE_NUMBER before sending SMS.');

            return;
        }

        $message = SmsMessage::query()->create([
            'direction' => SmsMessage::DIRECTION_OUTGOING,
            'status' => SmsMessage::STATUS_SENDING,
            'from_phone' => $from,
            'to_phone' => $to,
            'body' => $body,
            'sent_by_user_id' => auth()->id(),
        ]);

        try {
            $payload = app(TwilioSmsClient::class)->sendMessage($to, $body, $from);
        } catch (Throwable $e) {
            $message->update([
                'status' => SmsMessage::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'raw_payload' => [
                    'error' => $e->getMessage(),
                ],
            ]);

            $this->addError('send', $e->getMessage());

            return;
        }

        $status = (string) ($payload['status'] ?? SmsMessage::STATUS_QUEUED);

        $message->update([
            'status' => $status !== '' ? $status : SmsMessage::STATUS_QUEUED,
            'twilio_sid' => filled($payload['sid'] ?? null) ? (string) $payload['sid'] : null,
            'twilio_account_sid' => filled($payload['account_sid'] ?? null)
                ? (string) $payload['account_sid']
                : (filled(config('services.twilio.account_sid')) ? (string) config('services.twilio.account_sid') : null),
            'twilio_status' => $status,
            'raw_payload' => $payload,
            'sent_at' => now(),
        ]);

        $this->reset('messageBody');
        session()->flash('status', 'Text message sent to '.$to.'.');
    }

    public function render(): View
    {
        $baseQuery = SmsMessage::query();

        $query = SmsMessage::query()
            ->with('sentBy:id,name,email')
            ->latest('created_at');

        if ($this->direction !== 'all') {
            $query->where('direction', $this->direction);
        }

        if (trim($this->q) !== '') {
            $term = trim($this->q);
            $query->where(function ($subQuery) use ($term) {
                $subQuery
                    ->where('from_phone', 'like', '%'.$term.'%')
                    ->orWhere('to_phone', 'like', '%'.$term.'%')
                    ->orWhere('body', 'like', '%'.$term.'%')
                    ->orWhere('twilio_sid', 'like', '%'.$term.'%');
            });
        }

        return view('livewire.admin.sms-inbox', [
            'messages' => $query->paginate($this->perPage),
            'summary' => [
                'incoming' => (clone $baseQuery)->where('direction', SmsMessage::DIRECTION_INCOMING)->count(),
                'outgoing' => (clone $baseQuery)->where('direction', SmsMessage::DIRECTION_OUTGOING)->count(),
                'failed' => (clone $baseQuery)->where('status', SmsMessage::STATUS_FAILED)->count(),
            ],
            'directionOptions' => [
                ['label' => 'All messages', 'value' => 'all'],
                ['label' => 'Received', 'value' => SmsMessage::DIRECTION_INCOMING],
                ['label' => 'Sent', 'value' => SmsMessage::DIRECTION_OUTGOING],
            ],
        ]);
    }
}
