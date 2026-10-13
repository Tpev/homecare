<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScreeningRecord extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }
}
