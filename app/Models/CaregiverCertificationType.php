<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaregiverCertificationType extends Model
{
    use HasFactory;

    protected $fillable = ['slug', 'label', 'sort_order', 'active'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(CaregiverCertification::class);
    }
}
