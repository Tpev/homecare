<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScreeningRecordName extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_alias' => 'boolean'];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(ScreeningRecord::class, 'record_id');
    }
}
