<?php

namespace App\Observers;

use App\Services\Ops\ManagedCareOpsRecorder;
use Illuminate\Database\Eloquent\Model;

/** Capture inside the original transaction; the outbox and delivery commit with the change. */
class ManagedCareOpsObserver
{
    public function created(Model $model): void
    {
        app(ManagedCareOpsRecorder::class)->record($model, true);
    }

    public function updated(Model $model): void
    {
        app(ManagedCareOpsRecorder::class)->record($model, false);
    }
}
