<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class ScreeningAuditEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'occurred_at' => 'datetime', 'exported_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $event): void {
            if (array_diff(array_keys($event->getDirty()), ['exported_at'])) {
                throw new LogicException('Screening audit evidence is append-only.');
            }
        });
        static::deleting(fn () => throw new LogicException('Screening audit evidence cannot be deleted.'));
    }
}
