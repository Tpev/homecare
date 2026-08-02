<?php

namespace App\Services\ContinuousCoverage;

use App\Models\ContinuousCoverageHandoff;
use App\Models\ContinuousCoverageShift;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContinuousCoverageHandoffService
{
    public function __construct(
        private readonly ContinuousCoverageAccess $access,
        private readonly ContinuousCoverageEventRecorder $events,
    ) {}

    public function record(ContinuousCoverageShift $shift, User $caregiver, string $notes): ContinuousCoverageHandoff
    {
        if (! $this->access->allows($caregiver)) {
            throw ValidationException::withMessages(['coverage' => 'Continuous Coverage is not currently available for this account.']);
        }

        $notes = trim($notes);
        if (mb_strlen($notes) < 3 || mb_strlen($notes) > 2000) {
            throw ValidationException::withMessages(['notes' => 'Enter a handoff note between 3 and 2,000 characters.']);
        }

        return DB::transaction(function () use ($shift, $caregiver, $notes): ContinuousCoverageHandoff {
            $locked = ContinuousCoverageShift::query()
                ->lockForUpdate()
                ->with('plan')
                ->findOrFail($shift->id);
            if ($caregiver->role !== 'caregiver'
                || (int) $locked->assigned_caregiver_user_id !== (int) $caregiver->id
                || ! in_array($locked->status, [
                    ContinuousCoverageShift::STATUS_CONFIRMED,
                    ContinuousCoverageShift::STATUS_PAYMENT_ATTENTION,
                    ContinuousCoverageShift::STATUS_IN_PROGRESS,
                ], true)) {
                throw ValidationException::withMessages(['shift' => 'Only the currently assigned caregiver can add a handoff note to an active coverage shift.']);
            }

            $handoff = ContinuousCoverageHandoff::query()->create([
                'continuous_coverage_shift_id' => $locked->id,
                'caregiver_user_id' => $caregiver->id,
                'notes' => $notes,
                'recorded_at' => now(),
            ]);
            $this->events->record($locked->plan, 'handoff_recorded', $caregiver, $locked, [
                'handoff_id' => $handoff->id,
            ]);

            return $handoff->fresh(['caregiver']);
        });
    }
}
