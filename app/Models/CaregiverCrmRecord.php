<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaregiverCrmRecord extends Model
{
    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    protected $fillable = [
        'caregiver_user_id',
        'assigned_admin_id',
        'priority',
        'notes',
        'next_action',
        'next_follow_up_at',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'next_follow_up_at' => 'datetime',
            'version' => 'integer',
            'assigned_admin_id' => 'integer',
        ];
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_user_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CaregiverCrmActivity::class, 'caregiver_user_id', 'caregiver_user_id');
    }
}
