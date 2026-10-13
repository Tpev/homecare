<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CareRequestApplication extends Model
{
    use HasFactory;

    protected $attributes = ['offer_version' => 1];

    public const STATUS_APPLIED = 'applied';

    public const STATUS_SHORTLISTED = 'shortlisted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_HIRED = 'hired';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUS_NOT_SELECTED = 'not_selected';

    protected $fillable = [
        'care_request_id',
        'caregiver_user_id',
        'status',
        'proposed_rate',
        'cover_note',
        'selected_days',
        'offered_schedule',
        'schedule_fingerprint',
        'offer_version',
    ];

    protected function casts(): array
    {
        return [
            'proposed_rate' => 'decimal:2',
            'selected_days' => 'array',
            'offered_schedule' => 'array',
            'offer_version' => 'integer',
        ];
    }

    public function careRequest(): BelongsTo
    {
        return $this->belongsTo(CareRequest::class);
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_user_id');
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(CareRequestConversation::class, 'care_request_application_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(CareRequestInvitation::class, 'care_request_application_id');
    }

    public function booking(): HasOne
    {
        return $this->hasOne(CareBooking::class, 'care_request_application_id')->latest('id');
    }
}
