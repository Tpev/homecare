<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AiSupportActionPreview extends Model
{
    use HasUuids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'preview_payload' => 'encrypted:array',
            'expires_at' => 'datetime',
            'invalidated_at' => 'datetime',
            'content_deleted_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
