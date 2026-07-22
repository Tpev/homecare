<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketMessage extends Model
{
    use HasFactory;

    public const KIND_PUBLIC = 'public';

    public const KIND_INTERNAL_NOTE = 'internal_note';

    protected $fillable = [
        'support_ticket_id',
        'sender_user_id',
        'kind',
        'body',
        'client_message_id',
    ];

    protected $hidden = [
        'client_message_id',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function isInternalNote(): bool
    {
        return $this->kind === self::KIND_INTERNAL_NOTE;
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdministrator()) {
            return $query;
        }

        return $query
            ->where('kind', self::KIND_PUBLIC)
            ->whereHas('ticket', fn (Builder $ticketQuery) => $ticketQuery->where('opener_user_id', $user->id));
    }
}
