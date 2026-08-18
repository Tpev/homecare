<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AiSupportPreparation extends Model
{
    use HasUuids;

    public const STATE_READY = 'ready';

    public const STATE_APPLIED = 'applied';

    public const STATE_CANCELLED = 'cancelled';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['payload', 'fields_hash'];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'applied_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function isUsable(): bool
    {
        return in_array($this->state, [self::STATE_READY, self::STATE_APPLIED], true)
            && ! $this->cancelled_at
            && $this->expires_at?->isFuture() === true;
    }
}
