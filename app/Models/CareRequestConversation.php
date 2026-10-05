<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamilyAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CareRequestConversation extends Model
{
    use BelongsToFamilyAccount, HasFactory;

    protected $fillable = [
        'care_request_id',
        'family_account_id',
        'family_user_id',
        'caregiver_user_id',
        'care_request_application_id',
        'started_by_user_id',
        'family_last_read_at',
        'caregiver_last_read_at',
        'last_message_at',
        'last_message_sender_id',
    ];

    protected function casts(): array
    {
        return [
            'family_last_read_at' => 'datetime',
            'caregiver_last_read_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    public function careRequest(): BelongsTo
    {
        return $this->belongsTo(CareRequest::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(CareRequestApplication::class, 'care_request_application_id');
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(User::class, 'family_user_id');
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_user_id');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    public function lastMessageSender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_message_sender_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CareRequestMessage::class)->orderBy('created_at');
    }

    public function familyReads(): HasMany
    {
        return $this->hasMany(FamilyConversationRead::class);
    }

    public function scopeForUser($query, User $user)
    {
        if ($user->role === 'family') {
            $accountId = app(\App\Services\FamilyAccounts\FamilyAccountContext::class)
                ->membershipFor($user, false)?->family_account_id;

            return $query->where('family_account_id', $accountId ?? 0);
        }

        if ($user->role === 'caregiver') {
            return $query->where('caregiver_user_id', $user->id);
        }

        return $query->whereRaw('1=0');
    }

    public function isParticipant(User $user): bool
    {
        if ($user->role === 'family') {
            return app(\App\Services\FamilyAccounts\FamilyAccountContext::class)->canAccessRecord($user, $this);
        }

        return $user->role === 'caregiver' && (int) $this->caregiver_user_id === (int) $user->id;
    }

    public function canSendMessages(User $user): bool
    {
        if (! $this->isParticipant($user)) {
            return false;
        }

        return $this->messagingClosedReason() === null;
    }

    public function messagingClosedReason(): ?string
    {
        $request = CareRequest::query()->find($this->care_request_id);

        return $request
            ? app(\App\Services\Messaging\CareRequestChatService::class)->closedReason($request, (int) $this->caregiver_user_id)
            : 'This request is no longer available. You can still read this conversation.';
    }

    public function markRead(User $user): void
    {
        if (! $this->isParticipant($user)) {
            return;
        }

        app(\App\Services\Notifications\NotificationReadService::class)
            ->markRelated($user, 'conversation_id', (int) $this->id, now());

        if ($user->role === 'family') {
            FamilyConversationRead::query()->updateOrCreate(
                ['care_request_conversation_id' => $this->id, 'user_id' => $user->id],
                ['last_read_at' => now()],
            );

            // Keep this populated for compatibility with old reporting and rollback-free deploys.
            $this->forceFill(['family_last_read_at' => now()])->save();

            return;
        }

        $this->forceFill(['caregiver_last_read_at' => now()])->save();
    }

    public function lastReadAtFor(User $user): mixed
    {
        if ($user->role !== 'family') {
            return $this->caregiver_last_read_at;
        }

        $read = $this->relationLoaded('familyReads')
            ? $this->familyReads->firstWhere('user_id', $user->id)
            : $this->familyReads()->where('user_id', $user->id)->first();

        return $read?->last_read_at;
    }

    public static function findOrCreateForApplication(CareRequestApplication $application, int $startedByUserId): self
    {
        return self::findOrCreateForRequest($application->careRequest, (int) $application->caregiver_user_id, $startedByUserId, $application);
    }

    public static function findOrCreateForRequest(CareRequest $request, int $caregiverId, int $startedByUserId, ?CareRequestApplication $application = null): self
    {
        $conversation = self::query()->firstOrCreate(
            ['care_request_id' => $request->id, 'caregiver_user_id' => $caregiverId],
            [
                'family_account_id' => $request->family_account_id,
                'family_user_id' => $request->family_user_id,
                'care_request_application_id' => $application?->id,
                'started_by_user_id' => $startedByUserId,
            ],
        );

        if ($application && ! $conversation->care_request_application_id) {
            $conversation->forceFill(['care_request_application_id' => $application->id])->save();
        }

        return $conversation;
    }
}
