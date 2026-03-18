<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageViewEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_name',
        'user_id',
        'anon_id',
        'url',
        'referrer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'ip_hash',
        'user_agent_hash',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

