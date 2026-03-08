<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CareTask extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function careRequests(): BelongsToMany
    {
        return $this->belongsToMany(CareRequest::class, 'care_request_task')
            ->withPivot('task_note')
            ->withTimestamps();
    }
}
