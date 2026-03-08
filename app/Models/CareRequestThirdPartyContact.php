<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareRequestThirdPartyContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'care_request_id',
        'full_name',
        'relationship_to_recipient',
        'phone',
        'email',
    ];

    public function careRequest(): BelongsTo
    {
        return $this->belongsTo(CareRequest::class);
    }
}
