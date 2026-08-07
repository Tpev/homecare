<?php

namespace App\Models;

use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

class SupportTicket extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'family_account_id',
        'family_visibility',
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
        'last_public_message_at',
        'last_public_message_sender_id',
        'opener_last_read_at',
        'admin_last_read_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'last_public_message_at' => 'datetime',
            'opener_last_read_at' => 'datetime',
            'admin_last_read_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SupportTicket $ticket): void {
            if (! $ticket->family_account_id) {
                $ticket->family_account_id = $ticket->care_request_id
                    ? CareRequest::query()->whereKey($ticket->care_request_id)->value('family_account_id')
                    : null;
            }

            if (! $ticket->family_account_id && $ticket->care_booking_id) {
                $ticket->family_account_id = CareBooking::query()
                    ->whereKey($ticket->care_booking_id)
                    ->value('family_account_id');
            }

            if (! $ticket->family_account_id && $ticket->opener_user_id) {
                $opener = User::query()->find($ticket->opener_user_id);
                if ($opener?->role === 'family' && ! $opener->isAdministrator()) {
                    $ticket->family_account_id = app(FamilyAccountContext::class)->account($opener)->id;
                }
            }

            $ticket->family_visibility ??= in_array($ticket->category, ['billing', 'account', 'account_access'], true)
                ? 'owner_only'
                : 'shared_care';
        });

        static::created(function (SupportTicket $ticket): void {
            if (! Schema::hasColumn('support_tickets', 'last_public_message_at') || $ticket->last_public_message_at) {
                return;
            }

            $ticket->forceFill([
                'last_public_message_at' => $ticket->created_at,
                'last_public_message_sender_id' => $ticket->opener_user_id,
                'opener_last_read_at' => $ticket->created_at,
            ])->saveQuietly();
        });
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opener_user_id');
    }

    public function familyAccount(): BelongsTo
    {
        return $this->belongsTo(FamilyAccount::class);
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

    public function lastPublicMessageSender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_public_message_sender_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class)->oldest();
    }

    public function publicMessages(): HasMany
    {
        return $this->messages()->where('kind', SupportTicketMessage::KIND_PUBLIC);
    }

    public function latestPublicMessage(): HasOne
    {
        return $this->hasOne(SupportTicketMessage::class)
            ->where('kind', SupportTicketMessage::KIND_PUBLIC)
            ->latestOfMany();
    }

    public function bookingCorrections(): HasMany
    {
        return $this->hasMany(CareBookingCorrection::class)->latest();
    }

    public function familyReads(): HasMany
    {
        return $this->hasMany(FamilySupportTicketRead::class);
    }

    public function isUnreadFor(User $user): bool
    {
        if ($user->role !== 'family' || ! $this->family_account_id) {
            return $this->isUnreadForOpener();
        }

        $read = $this->relationLoaded('familyReads')
            ? $this->familyReads->firstWhere('user_id', $user->id)
            : $this->familyReads()->where('user_id', $user->id)->first();

        return $this->last_public_message_at
            && (int) $this->last_public_message_sender_id !== (int) $user->id
            && (! $read?->last_read_at || $this->last_public_message_at->gt($read->last_read_at));
    }

    public function markReadFor(User $user): void
    {
        if ($user->role === 'family' && $this->family_account_id) {
            FamilySupportTicketRead::query()->updateOrCreate(
                ['support_ticket_id' => $this->id, 'user_id' => $user->id],
                ['last_read_at' => now()],
            );
        }

        if ((int) $this->opener_user_id === (int) $user->id) {
            $this->markReadForOpener();
        }
    }

    public function timeCorrection(): HasOne
    {
        return $this->hasOne(CareBookingTimeCorrection::class);
    }

    public function isUnreadForOpener(): bool
    {
        return $this->last_public_message_at
            && (int) $this->last_public_message_sender_id !== (int) $this->opener_user_id
            && (! $this->opener_last_read_at || $this->last_public_message_at->gt($this->opener_last_read_at));
    }

    public function isUnreadForAdmin(): bool
    {
        return $this->last_public_message_at
            && (int) $this->last_public_message_sender_id === (int) $this->opener_user_id
            && (! $this->admin_last_read_at || $this->last_public_message_at->gt($this->admin_last_read_at));
    }

    public function markReadForOpener(): void
    {
        if (! $this->last_public_message_at || ! $this->isUnreadForOpener()) {
            return;
        }

        $this->forceFill(['opener_last_read_at' => now()])->save();
    }

    public function markReadForAdmin(): void
    {
        if (! $this->last_public_message_at || ! $this->isUnreadForAdmin()) {
            return;
        }

        $this->forceFill(['admin_last_read_at' => now()])->save();
    }
}
