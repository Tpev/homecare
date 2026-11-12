<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Delivery history only; not a staff inbox. Payloads contain allowlisted operational summaries. */
class ManagedCareOpsEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'available_at' => 'datetime', 'sending_at' => 'datetime', 'sent_at' => 'datetime'];
    }
}
