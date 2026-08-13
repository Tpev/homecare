<?php

namespace App\Models;

use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

class SupportTicket extends Model
{
    use HasFactory;

    public const SOURCE_SUPPORT_CENTER = 'support_center';

    public const SOURCE_CHAT_WIDGET = 'chat_widget';

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const RESPONDER_MODE_HUMAN_ONLY = 'human_only';

    public const RESPONDER_MODE_AUTOMATED = 'automated';

    protected $fillable = [
        'family_account_id',
        'family_visibility',
        'source',
        'responder_mode',
        'origin_route',
        'origin_path',
        'opener_user_id',
        'counterparty_user_id',
        'care_request_id',
        'care_booking_id',
        'category',
        'status',
        'priority',
        'subject',
        'description',
        'initial_client_message_id',
        'admin_note',
        'resolved_at',
        'retention_started_at',
        'transcript_delete_after',
        'transcript_deleted_at',
        'assigned_admin_id',
        'claimed_at',
        'transferred_to_human_at',
        'returned_to_automation_at',
        'handoff_reason_code',
        'last_public_message_at',
        'last_public_message_sender_id',
        'opener_last_read_at',
        'admin_last_read_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'retention_started_at' => 'datetime',
            'transcript_delete_after' => 'datetime',
            'transcript_deleted_at' => 'datetime',
            'claimed_at' => 'datetime',
            'transferred_to_human_at' => 'datetime',
            'returned_to_automation_at' => 'datetime',
            'last_public_message_at' => 'datetime',
            'opener_last_read_at' => 'datetime',
            'admin_last_read_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (SupportTicket $ticket): void {
            if (! Schema::hasColumn('support_tickets', 'retention_started_at') || ! $ticket->isDirty('status')) {
                return;
            }

            $finalStatuses = [self::STATUS_RESOLVED, self::STATUS_CLOSED];
            $wasFinal = in_array($ticket->getOriginal('status'), $finalStatuses, true);
            $isFinal = in_array($ticket->status, $finalStatuses, true);

            if ($isFinal && ! $wasFinal) {
                $startedAt = now();
                $ticket->retention_started_at = $startedAt;
                $ticket->transcript_delete_after = $startedAt->copy()->addMonths(
                    (int) config('ai_support.support_transcript_months', 12)
                );
            } elseif (! $isFinal) {
                $ticket->retention_started_at = null;
                $ticket->transcript_delete_after = null;
            }
        });

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
                    $ticket->family_account_id = app(FamilyAccountContext::class)
                        ->membershipFor($opener)?->family_account_id;
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

    public function activities(): HasMany
    {
        return $this->hasMany(SupportTicketActivity::class)->latest('created_at');
    }

    public function aiInteractionEvents(): HasMany
    {
        return $this->hasMany(AiSupportInteractionEvent::class);
    }

    public function isHumanOnly(): bool
    {
        return $this->responder_mode !== self::RESPONDER_MODE_AUTOMATED;
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdministrator()) {
            return $query;
        }

        if ($user->role !== 'family') {
            return $query->where('opener_user_id', $user->id);
        }

        $context = app(FamilyAccountContext::class);
        $membership = $context->membershipFor($user, false);

        return $query->where(function (Builder $visible) use ($user, $membership): void {
            $visible->where(function (Builder $legacy) use ($user): void {
                $legacy->whereNull('family_account_id')
                    ->where('opener_user_id', $user->id);
            });

            if (! $membership) {
                return;
            }

            $visible->orWhere(function (Builder $accountTickets) use ($membership): void {
                $accountTickets->where('family_account_id', $membership->family_account_id)
                    ->where(function (Builder $visibility) use ($membership): void {
                        $visibility->where('family_visibility', 'shared_care');
                        if ($membership->isOwner()) {
                            $visibility->orWhere('family_visibility', 'owner_only');
                        }
                    });
            });
        });
    }

    public function scopeUnreadForAdmin(Builder $query): Builder
    {
        return $query
            ->whereNotNull('last_public_message_at')
            ->where(function (Builder $unread): void {
                $unread->whereNull('admin_last_read_at')
                    ->orWhereColumn('last_public_message_at', '>', 'admin_last_read_at');
            })
            ->where(function (Builder $sender): void {
                $sender->whereNull('last_public_message_sender_id')
                    ->orWhereHas('lastPublicMessageSender', fn (Builder $user): Builder => $user->where('role', '!=', 'admin'));
            });
    }

    public function isChatWidgetConversation(): bool
    {
        return $this->source === self::SOURCE_CHAT_WIDGET;
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
        if (! $this->last_public_message_at
            || ($this->admin_last_read_at && ! $this->last_public_message_at->gt($this->admin_last_read_at))) {
            return false;
        }

        if (! $this->last_public_message_sender_id) {
            return true;
        }

        $sender = $this->relationLoaded('lastPublicMessageSender')
            ? $this->lastPublicMessageSender
            : $this->lastPublicMessageSender()->first(['id', 'role']);

        return ! $sender?->isAdministrator();
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
