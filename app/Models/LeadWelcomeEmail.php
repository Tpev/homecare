<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

class LeadWelcomeEmail extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return array_fill_keys([
            'last_attempt_at', 'sent_at', 'previewed_at', 'unsubscribed_at',
            'account_created_at', 'account_linked_at', 'request_posted_at',
        ], 'datetime') + ['attempts' => 'integer'];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function startUrl(): string
    {
        return URL::temporarySignedRoute('lead-welcome.start', now()->addDays(14), ['welcomeEmail' => $this->id]);
    }

    public function unsubscribeUrl(): string
    {
        return URL::signedRoute('lead-welcome.unsubscribe', ['welcomeEmail' => $this->id]);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'queued' => 'Waiting to send',
            'sending' => ($this->last_attempt_at ?: $this->updated_at)?->lt(now()->subMinutes(5)) ? 'Delivery unconfirmed' : 'Sending',
            'unconfirmed' => 'Delivery unconfirmed',
            'retrying' => 'Retrying',
            'sent' => 'Email sent',
            'previewed' => 'Preview saved locally',
            'failed' => 'Email failed',
            default => 'Not sent',
        };
    }

    public function progressLabel(): string
    {
        return match (true) {
            $this->request_posted_at !== null => 'Request posted',
            $this->account_created_at !== null => 'Account created',
            $this->account_linked_at !== null => 'Existing account',
            default => 'Hasn’t registered',
        };
    }
}
