<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScreeningDatasetImport extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'source_urls' => 'array', 'file_manifest' => 'array', 'row_counts' => 'array', 'downloaded_at' => 'datetime', 'validated_at' => 'datetime', 'activated_at' => 'datetime'];
    }
}
