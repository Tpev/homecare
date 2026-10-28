<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CareHourContributionVerification extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['code_hash', 'token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'verified_at' => 'datetime', 'token_expires_at' => 'datetime', 'attempts' => 'integer'];
    }
}
