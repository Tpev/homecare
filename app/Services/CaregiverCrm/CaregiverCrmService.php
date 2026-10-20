<?php

namespace App\Services\CaregiverCrm;

use App\Models\CaregiverCrmActivity;
use App\Models\CaregiverCrmRecord;
use App\Models\CaregiverProfile;
use App\Models\User;
use App\Services\Screening\CaregiverWorkEligibility;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CaregiverCrmService
{
    public function __construct(private readonly CaregiverWorkEligibility $eligibility) {}

    public function saveRecord(User $actor, int $caregiverId, array $data, int $expectedVersion): CaregiverCrmRecord
    {
        $this->assertAdministrator($actor);
        $this->validateVersion($expectedVersion);

        return DB::transaction(function () use ($actor, $caregiverId, $data, $expectedVersion): CaregiverCrmRecord {
            $this->lockEligibleCaregiver($caregiverId);
            $record = CaregiverCrmRecord::query()->where('caregiver_user_id', $caregiverId)->lockForUpdate()->first();
            $this->assertVersion($record, $expectedVersion);
            $before = $record ? $this->recordValues($record) : [];
            $values = $this->validateRecord(array_replace([
                'assigned_admin_id' => null,
                'priority' => 'normal',
                'notes' => null,
                'next_action' => null,
                'next_follow_up_at' => null,
            ], $before, array_intersect_key($data, array_flip([
                'assigned_admin_id', 'priority', 'notes', 'next_action', 'next_follow_up_at',
            ]))), $record);
            if ($values['assigned_admin_id'] !== null) {
                $assigned = User::query()->whereKey($values['assigned_admin_id'])->lockForUpdate()->first();
                if (! $assigned?->isAdministrator()) {
                    throw ValidationException::withMessages(['assigned_admin_id' => 'Choose a current administrator.']);
                }
            }
            $changes = [];
            foreach ($values as $field => $value) {
                if (! array_key_exists($field, $before) || $before[$field] !== $value) {
                    $changes[$field] = ['from' => $before[$field] ?? null, 'to' => $value];
                }
            }
            if ($record && $changes === []) {
                return $record;
            }
            $record ??= new CaregiverCrmRecord(['caregiver_user_id' => $caregiverId]);
            $record->fill($values);
            $record->version = $expectedVersion + 1;
            $record->save();
            $this->systemActivity($actor, $caregiverId, 'record_updated', 'Updated caregiver CRM details.', [
                'changes' => $changes,
                'version' => $record->version,
            ]);

            return $record->fresh();
        });
    }

    public function completeNextAction(User $actor, int $caregiverId, int $expectedVersion): CaregiverCrmRecord
    {
        $this->assertAdministrator($actor);
        $this->validateVersion($expectedVersion);

        return DB::transaction(function () use ($actor, $caregiverId, $expectedVersion): CaregiverCrmRecord {
            $this->lockEligibleCaregiver($caregiverId);
            $record = CaregiverCrmRecord::query()->where('caregiver_user_id', $caregiverId)->lockForUpdate()->first();
            $this->assertVersion($record, $expectedVersion);
            if (! $record || ! filled($record->next_action)) {
                throw ValidationException::withMessages(['next_action' => 'There is no next action to complete.']);
            }
            $this->systemActivity($actor, $caregiverId, 'next_action_completed', 'Completed next action: '.$record->next_action, [
                'next_action' => $record->next_action,
                'next_follow_up_at' => $record->next_follow_up_at?->toDateTimeString(),
                'version' => $expectedVersion + 1,
            ]);
            $record->forceFill([
                'next_action' => null,
                'next_follow_up_at' => null,
                'version' => $expectedVersion + 1,
            ])->save();

            return $record->fresh();
        });
    }

    public function logActivity(User $actor, int $caregiverId, array $data): CaregiverCrmActivity
    {
        $this->assertAdministrator($actor);
        $normalized = $this->trimStrings($data, ['type', 'call_outcome', 'body', 'occurred_at']);
        $normalized['occurred_at'] ??= now()->toDateTimeString();
        $validated = Validator::make($normalized, [
            'type' => ['required', Rule::in(CaregiverCrmActivity::PUBLIC_TYPES)],
            'call_outcome' => ['nullable', 'required_if:type,call', 'prohibited_unless:type,call', Rule::in(CaregiverCrmActivity::CALL_OUTCOMES)],
            'body' => ['required', 'string', 'max:5000'],
            'occurred_at' => ['required', 'date', 'before_or_equal:'.now()->toDateTimeString()],
        ])->validate();
        $occurredAt = Carbon::parse($validated['occurred_at'], config('app.timezone'))->setTimezone(config('app.timezone'));

        return DB::transaction(function () use ($actor, $caregiverId, $validated, $occurredAt): CaregiverCrmActivity {
            $this->lockEligibleCaregiver($caregiverId);

            return CaregiverCrmActivity::query()->create([
                'caregiver_user_id' => $caregiverId,
                'actor_user_id' => $actor->id,
                'type' => $validated['type'],
                'call_outcome' => $validated['type'] === 'call' ? $validated['call_outcome'] : null,
                'body' => $validated['body'],
                'occurred_at' => $occurredAt,
                'metadata' => null,
            ]);
        });
    }

    private function assertAdministrator(User $actor): void
    {
        abort_unless($actor->isAdministrator() && $actor->fresh()?->isAdministrator(), 403);
    }

    private function lockEligibleCaregiver(int $caregiverId): void
    {
        $profile = $this->eligibility->constrain(CaregiverProfile::query())
            ->where('user_id', $caregiverId)->lockForUpdate()->first();
        $caregiver = $profile ? User::query()->whereKey($caregiverId)->lockForUpdate()->first() : null;
        if (! $profile || $caregiver?->role !== 'caregiver') {
            throw ValidationException::withMessages(['caregiver' => 'This caregiver is no longer approved and available for new work. Reload the directory to continue.']);
        }
    }

    private function validateVersion(int $version): void
    {
        Validator::make(['version' => $version], ['version' => ['integer', 'min:0']])->validate();
    }

    private function assertVersion(?CaregiverCrmRecord $record, int $expectedVersion): void
    {
        if (($record?->version ?? 0) !== $expectedVersion) {
            throw ValidationException::withMessages(['record' => 'This caregiver record changed while you were editing it. Reload the latest details before saving.']);
        }
    }

    private function validateRecord(array $values, ?CaregiverCrmRecord $record): array
    {
        $values = $this->trimStrings($values, ['priority', 'notes', 'next_action', 'next_follow_up_at', 'assigned_admin_id']);
        $validated = Validator::make($values, [
            'assigned_admin_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'admin')],
            'priority' => ['required', Rule::in(CaregiverCrmRecord::PRIORITIES)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'next_action' => ['nullable', 'string', 'max:255', 'required_with:next_follow_up_at'],
            'next_follow_up_at' => ['nullable', 'date'],
        ])->validate();
        $validated['assigned_admin_id'] = isset($validated['assigned_admin_id']) ? (int) $validated['assigned_admin_id'] : null;
        $followUp = isset($validated['next_follow_up_at'])
            ? Carbon::parse($validated['next_follow_up_at'], config('app.timezone'))->setTimezone(config('app.timezone')) : null;
        // datetime-local forms show minutes. Preserve the stored seconds when
        // that unchanged minute is submitted, including an overdue follow-up.
        if ($followUp && $record?->next_follow_up_at && is_string($validated['next_follow_up_at'])
            && preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}$/D', $validated['next_follow_up_at'])
            && $followUp->format('Y-m-d H:i') === $record->next_follow_up_at->format('Y-m-d H:i')) {
            $followUp = $record->next_follow_up_at->copy();
        }
        if ($followUp && (! $record?->next_follow_up_at || ! $followUp->equalTo($record->next_follow_up_at))
            && $followUp->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages(['next_follow_up_at' => 'Choose a future date and time for the next follow-up.']);
        }
        $validated['next_follow_up_at'] = $followUp?->toDateTimeString();

        return $validated;
    }

    private function recordValues(CaregiverCrmRecord $record): array
    {
        return [
            'assigned_admin_id' => $record->assigned_admin_id,
            'priority' => $record->priority,
            'notes' => $record->notes,
            'next_action' => $record->next_action,
            'next_follow_up_at' => $record->next_follow_up_at?->toDateTimeString(),
        ];
    }

    private function trimStrings(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
                if ($data[$field] === '') {
                    $data[$field] = null;
                }
            }
        }

        return $data;
    }

    private function systemActivity(User $actor, int $caregiverId, string $action, string $body, array $metadata): void
    {
        CaregiverCrmActivity::query()->create([
            'caregiver_user_id' => $caregiverId,
            'actor_user_id' => $actor->id,
            'type' => 'system',
            'body' => $body,
            'occurred_at' => now(),
            'metadata' => ['action' => $action, ...$metadata],
        ]);
    }
}
