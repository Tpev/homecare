<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaregiverRosterEntry extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['assessment' => 'array', 'evidence' => 'array', 'selected' => 'boolean', 'send_sms' => 'boolean'];
    }

    public function roster(): BelongsTo
    {
        return $this->belongsTo(CaregiverRoster::class, 'caregiver_roster_id');
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_user_id');
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(CareRequestInvitation::class, 'care_request_invitation_id');
    }

    public function smsMessage(): BelongsTo
    {
        return $this->belongsTo(SmsMessage::class);
    }
}
