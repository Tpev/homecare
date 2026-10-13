<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamilyAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CareArrangement extends Model
{
    use BelongsToFamilyAccount;

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return ['schedule_slots' => 'array', 'exceptions' => 'array', 'starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function sourceRequest(): BelongsTo
    {
        return $this->belongsTo(CareRequest::class, 'source_care_request_id');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(CarePlan::class);
    }

    public function needs(): HasMany
    {
        return $this->hasMany(CareArrangementNeed::class);
    }
}
