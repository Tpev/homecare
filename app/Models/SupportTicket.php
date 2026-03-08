<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'opener_user_id',
        'counterparty_user_id',
        'care_request_id',
        'care_booking_id',
        'category',
        'status',
        'priority',
        'subject',
        'description',
        'admin_note',
        'resolved_at',
        'assigned_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opener_user_id');
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counterparty_user_id');
    }

    public function careRequest(): BelongsTo
    {
        return $this->belongsTo(CareRequest::class);
    }

    public function careBooking(): BelongsTo
    {
        return $this->belongsTo(CareBooking::class);
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }
}
