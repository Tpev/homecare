<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareRecipient extends Model
{
    use HasFactory;

    protected $table = 'care_request_recipients';

    protected $fillable = [
        'care_request_id',
        'full_name',
        'date_of_birth',
        'gender',
        'mobility_level',
        'relationship_to_family',
        'care_notes',
    ];

    public function careRequest(): BelongsTo
    {
        return $this->belongsTo(CareRequest::class);
    }
}
