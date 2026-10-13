<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CareArrangementNeed extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['status' => 'needed', 'kind' => 'regular'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'suppressed_plan_ids' => 'array'];
    }

    public function arrangement(): BelongsTo
    {
        return $this->belongsTo(CareArrangement::class, 'care_arrangement_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(CareBooking::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(CarePlanScheduleChange::class);
    }
}
