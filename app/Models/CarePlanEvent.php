<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarePlanEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'care_plan_id',
        'actor_user_id',
        'event_type',
        'reason',
        'payload',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(CarePlan::class, 'care_plan_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
