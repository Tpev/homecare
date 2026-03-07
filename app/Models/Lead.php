<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $fillable = [
        'lead_type',
        'name',
        'email',
        'phone',
        'company',
        'location',
        'zip',
        'data',
        'status',
        'source_url',
        'referrer_url',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'data' => 'array',
    ];
}