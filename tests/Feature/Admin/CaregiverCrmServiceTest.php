<?php

namespace Tests\Feature\Admin;

use App\Models\CaregiverCrmActivity;
use App\Models\CaregiverCrmRecord;
use App\Models\CaregiverProfile;
use App\Models\User;
use App\Services\CaregiverCrm\CaregiverCrmService;
use App\Services\Screening\CaregiverWorkEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CaregiverCrmServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        Queue::fake();
    }

    public static function mutations(): array
    {
        return [['save'], ['complete'], ['log']];
    }

    #[DataProvider('mutations')]
    public function test_every_mutation_requires_a_current_administrator(string $mutation): void
    {
        [$admin, $caregiver, , $service] = $this->actors();
        $admin->update(['role' => 'family']);
        $this->expectException(HttpException::class);
        $this->mutate($service, $mutation, $admin, $caregiver);
    }

    public static function ineligibleCaregivers(): array
    {
        $cases = [];
        foreach (['save', 'complete', 'log'] as $mutation) {
            foreach (['suspended', 'unapproved', 'inactive', 'wrong_role'] as $state) {
                $cases[$mutation.' '.$state] = [$mutation, $state];
            }
        }

        return $cases;
    }

    #[DataProvider('ineligibleCaregivers')]
    public function test_every_mutation_rechecks_current_work_eligibility(string $mutation, string $state): void
    {
        [$admin, $caregiver, $profile, $service] = $this->actors();
        if ($state === 'wrong_role') {
            $caregiver->update(['role' => 'family']);
        } else {
            $profile->update(match ($state) {
                'suspended' => ['screening_suspended_at' => now()],
                'unapproved' => ['screening_initial_approved_at' => null, 'screening_legacy_access' => false],
                'inactive' => ['status' => 'pending'],
            });
        }
        try {
            $this->mutate($service, $mutation, $admin, $caregiver);
            $this->fail('Expected current eligibility to be checked.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('caregiver', $exception->errors());
            $this->assertDatabaseCount('caregiver_crm_records', 0);
            $this->assertDatabaseCount('caregiver_crm_activities', 0);
        }
    }

    public function test_record_is_lazy_assigned_trimmed_versioned_and_audited(): void
    {
        [$admin, $caregiver, , $service] = $this->actors();
        $assignee = User::factory()->create(['role' => 'admin']);
        $this->assertDatabaseCount('caregiver_crm_records', 0);
        $record = $service->saveRecord($admin, $caregiver->id, [
            'assigned_admin_id' => $assignee->id,
            'priority' => 'high',
            'notes' => '  Prefers afternoon calls.  ',
            'next_action' => '  Discuss weekend availability  ',
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
            'version' => 99,
        ], 0);

        $this->assertSame(1, $record->version);
        $this->assertSame($assignee->id, $record->assigned_admin_id);
        $this->assertSame('Prefers afternoon calls.', $record->notes);
        $this->assertSame('Discuss weekend availability', $record->next_action);
        $this->assertSame('high', $record->priority);
        $audit = CaregiverCrmActivity::query()->sole();
        $this->assertSame('system', $audit->type);
        $this->assertSame($admin->id, $audit->actor_user_id);
        $this->assertSame('record_updated', $audit->metadata['action']);
        $this->assertSame('high', $audit->metadata['changes']['priority']['to']);
        $this->assertDatabaseCount('leads', 0);
        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_stale_record_save_and_completion_cannot_overwrite_newer_edits(): void
    {
        [$admin, $caregiver, , $service] = $this->actors();
        $service->saveRecord($admin, $caregiver->id, ['next_action' => 'Call on Friday'], 0);
        $service->saveRecord($admin, $caregiver->id, ['notes' => 'Updated by another administrator'], 1);
        foreach (['save', 'complete'] as $mutation) {
            try {
                $mutation === 'save'
                    ? $service->saveRecord($admin, $caregiver->id, ['notes' => 'Stale edit'], 1)
                    : $service->completeNextAction($admin, $caregiver->id, 1);
                $this->fail('Expected a version conflict.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('record', $exception->errors());
            }
        }
        $record = CaregiverCrmRecord::query()->sole();
        $this->assertSame(2, $record->version);
        $this->assertSame('Updated by another administrator', $record->notes);
        $this->assertSame('Call on Friday', $record->next_action);
        $this->assertDatabaseCount('caregiver_crm_activities', 2);
    }

    public function test_partial_updates_preserve_fields_and_unchanged_save_does_not_create_an_audit(): void
    {
        [$admin, $caregiver, , $service] = $this->actors();
        $record = $service->saveRecord($admin, $caregiver->id, ['priority' => 'urgent', 'notes' => 'Original notes'], 0);
        $record = $service->saveRecord($admin, $caregiver->id, ['notes' => 'Updated notes'], $record->version);
        $this->assertSame('urgent', $record->priority);
        $this->assertSame(2, $record->version);
        $record = $service->saveRecord($admin, $caregiver->id, ['notes' => ' Updated notes '], $record->version);
        $this->assertSame(2, $record->version);
        $this->assertDatabaseCount('caregiver_crm_activities', 2);
        $audit = CaregiverCrmActivity::query()->latest('id')->first();
        $this->assertSame(['from' => 'Original notes', 'to' => 'Updated notes'], $audit->metadata['changes']['notes']);
    }

    public static function invalidRecordInputs(): array
    {
        return [
            'unknown priority' => [['priority' => 'critical'], 'priority'],
            'long notes' => [['notes' => str_repeat('x', 5001)], 'notes'],
            'long action' => [['next_action' => str_repeat('x', 256)], 'next_action'],
            'invalid date' => [['next_follow_up_at' => 'not a date'], 'next_follow_up_at'],
            'followup without action' => [['next_follow_up_at' => '2099-01-01 10:00:00', 'next_action' => '   '], 'next_action'],
            'past followup' => [['next_follow_up_at' => '2000-01-01 10:00:00', 'next_action' => 'Call'], 'next_follow_up_at'],
            'nonexistent assignee' => [['assigned_admin_id' => 9999999], 'assigned_admin_id'],
        ];
    }

    #[DataProvider('invalidRecordInputs')]
    public function test_service_validates_record_inputs_before_persistence(array $data, string $field): void
    {
        [$admin, $caregiver, , $service] = $this->actors();
        try {
            $service->saveRecord($admin, $caregiver->id, $data, 0);
            $this->fail('Expected validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
            $this->assertDatabaseCount('caregiver_crm_records', 0);
            $this->assertDatabaseCount('caregiver_crm_activities', 0);
        }
    }

    public function test_assignment_rejects_a_non_admin(): void
    {
        [$admin, $caregiver, , $service] = $this->actors();
        $this->expectException(ValidationException::class);
        $service->saveRecord($admin, $caregiver->id, ['assigned_admin_id' => $caregiver->id], 0);
    }

    public function test_overdue_unchanged_followup_can_be_kept_while_editing_notes(): void
    {
        [$admin, $caregiver, , $service] = $this->actors();
        $record = $service->saveRecord($admin, $caregiver->id, [
            'next_action' => 'Call caregiver',
            'next_follow_up_at' => now()->addMinute()->toDateTimeString(),
        ], 0);
        $this->travel(2)->minutes();
        $record = $service->saveRecord($admin, $caregiver->id, ['notes' => 'Waiting for a reply'], $record->version);
        $this->assertTrue($record->next_follow_up_at->isPast());
        $this->assertSame('Waiting for a reply', $record->notes);
    }

    public function test_minute_precision_form_preserves_an_overdue_followups_stored_seconds(): void
    {
        [$admin, $caregiver, , $service] = $this->actors();
        $followUp = now()->addDay()->setSecond(37)->startOfSecond();
        $record = $service->saveRecord($admin, $caregiver->id, [
            'next_action' => 'Call caregiver',
            'next_follow_up_at' => $followUp->toDateTimeString(),
        ], 0);
        $this->travelTo($followUp->copy()->addHour());
        $record = $service->saveRecord($admin, $caregiver->id, [
            'notes' => 'Waiting for a reply',
            'next_follow_up_at' => $record->next_follow_up_at->format('Y-m-d\TH:i'),
        ], $record->version);
        $this->assertSame($followUp->timestamp, $record->next_follow_up_at->timestamp);
        $this->assertSame('Waiting for a reply', $record->notes);
        $audit = CaregiverCrmActivity::query()->latest('id')->first();
        $this->assertArrayNotHasKey('next_follow_up_at', $audit->metadata['changes']);
    }

    public function test_future_interactions_are_rejected_even_within_the_next_minute(): void
    {
        [$admin, $caregiver, , $service] = $this->actors();
        $this->expectException(ValidationException::class);
        $service->logActivity($admin, $caregiver->id, [
            'type' => 'call',
            'call_outcome' => 'connected',
            'body' => 'A future call must not mark someone contacted.',
            'occurred_at' => now()->addSeconds(30)->toDateTimeString(),
        ]);
    }

    public function test_completion_logs_the_prior_action_and_clears_only_followup_fields(): void
    {
        [$admin, $caregiver, , $service] = $this->actors();
        $record = $service->saveRecord($admin, $caregiver->id, [
            'priority' => 'high', 'notes' => 'Pinned context', 'assigned_admin_id' => $admin->id,
            'next_action' => 'Discuss weekend availability', 'next_follow_up_at' => now()->addDay()->toDateTimeString(),
        ], 0);
        $record = $service->completeNextAction($admin, $caregiver->id, $record->version);
        $this->assertSame(2, $record->version);
        $this->assertNull($record->next_action);
        $this->assertNull($record->next_follow_up_at);
        $this->assertSame('Pinned context', $record->notes);
        $this->assertSame('high', $record->priority);
        $this->assertSame($admin->id, $record->assigned_admin_id);
        $audit = CaregiverCrmActivity::query()->latest('id')->first();
        $this->assertSame('next_action_completed', $audit->metadata['action']);
        $this->assertSame('Discuss weekend availability', $audit->metadata['next_action']);
        $this->assertFalse($audit->isConnectedCall());
    }

    public function test_logging_contact_does_not_touch_an_open_followup_or_its_version(): void
    {
        [$admin, $caregiver, , $service] = $this->actors();
        $record = $service->saveRecord($admin, $caregiver->id, ['next_action' => 'Follow up tomorrow'], 0);
        foreach (['connected', 'voicemail', 'no_answer'] as $outcome) {
            $activity = $service->logActivity($admin, $caregiver->id, [
                'type' => 'call', 'call_outcome' => $outcome, 'body' => '  Called about availability.  ',
            ]);
            $this->assertSame($outcome === 'connected', $activity->isConnectedCall());
            $this->assertSame('Called about availability.', $activity->body);
        }
        foreach (['note', 'email', 'sms', 'meeting'] as $type) {
            $this->assertFalse($service->logActivity($admin, $caregiver->id, [
                'type' => $type, 'body' => 'Recorded previous interaction.',
            ])->isConnectedCall());
        }
        $this->assertSame(1, $record->fresh()->version);
        $this->assertSame('Follow up tomorrow', $record->fresh()->next_action);
        $this->assertSame(1, CaregiverCrmActivity::query()->where('type', 'call')->where('call_outcome', 'connected')->count());
        Mail::assertNothingSent();
        Notification::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_logging_first_contact_does_not_create_a_record_or_accept_client_audit_metadata(): void
    {
        [$admin, $caregiver, , $service] = $this->actors();
        $activity = $service->logActivity($admin, $caregiver->id, [
            'type' => 'note', 'body' => 'Initial contact note.',
            'metadata' => ['action' => 'next_action_completed'], 'actor_user_id' => $caregiver->id,
        ]);
        $this->assertDatabaseCount('caregiver_crm_records', 0);
        $this->assertNull($activity->metadata);
        $this->assertSame($admin->id, $activity->actor_user_id);
    }

    public static function invalidActivityInputs(): array
    {
        return [
            'system type injection' => [['type' => 'system', 'body' => 'Fake audit'], 'type'],
            'empty body' => [['type' => 'note', 'body' => '  '], 'body'],
            'long body' => [['type' => 'note', 'body' => str_repeat('x', 5001)], 'body'],
            'call outcome missing' => [['type' => 'call', 'body' => 'A call'], 'call_outcome'],
            'invalid outcome' => [['type' => 'call', 'call_outcome' => 'answered', 'body' => 'A call'], 'call_outcome'],
            'outcome on note' => [['type' => 'note', 'call_outcome' => 'connected', 'body' => 'A note'], 'call_outcome'],
            'future interaction' => [['type' => 'note', 'body' => 'A note', 'occurred_at' => '2099-01-01 10:00:00'], 'occurred_at'],
        ];
    }

    #[DataProvider('invalidActivityInputs')]
    public function test_activity_input_is_validated(array $data, string $field): void
    {
        [$admin, $caregiver, , $service] = $this->actors();
        try {
            $service->logActivity($admin, $caregiver->id, $data);
            $this->fail('Expected validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
            $this->assertDatabaseCount('caregiver_crm_activities', 0);
        }
    }

    public function test_contact_history_is_append_only(): void
    {
        [$admin, $caregiver, , $service] = $this->actors();
        $activity = $service->logActivity($admin, $caregiver->id, ['type' => 'note', 'body' => 'Original history']);
        $this->expectException(\LogicException::class);
        $activity->update(['body' => 'Rewritten history']);
    }

    public function test_eligibility_is_checked_inside_the_mutation_transaction(): void
    {
        [$admin, $caregiver] = $this->actors();
        $baseTransactionLevel = DB::transactionLevel();
        $eligibility = Mockery::mock(CaregiverWorkEligibility::class)->makePartial();
        $eligibility->shouldReceive('constrain')->once()->andReturnUsing(function ($query) use ($baseTransactionLevel) {
            $this->assertGreaterThan($baseTransactionLevel, DB::transactionLevel());

            return (new CaregiverWorkEligibility)->constrain($query);
        });
        (new CaregiverCrmService($eligibility))->saveRecord($admin, $caregiver->id, ['notes' => 'Current approval checked'], 0);
        $this->assertDatabaseCount('caregiver_crm_records', 1);
    }

    public function test_explicit_timezones_preserve_the_instant_in_storage(): void
    {
        [$admin, $caregiver, , $service] = $this->actors();
        $past = now()->subHour()->utc();
        $future = now()->addDay()->utc();
        $activity = $service->logActivity($admin, $caregiver->id, ['type' => 'note', 'body' => 'Past interaction', 'occurred_at' => $past->toIso8601String()]);
        $record = $service->saveRecord($admin, $caregiver->id, ['next_action' => 'Call tomorrow', 'next_follow_up_at' => $future->toIso8601String()], 0);
        $this->assertSame($past->timestamp, $activity->fresh()->occurred_at->timestamp);
        $this->assertSame($future->timestamp, $record->fresh()->next_follow_up_at->timestamp);
    }

    private function actors(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id, 'status' => 'active', 'screening_initial_approved_at' => now(),
            'screening_legacy_access' => false, 'screening_suspended_at' => null,
        ]);

        return [$admin, $caregiver, $profile, app(CaregiverCrmService::class)];
    }

    private function mutate(CaregiverCrmService $service, string $mutation, User $actor, User $caregiver): void
    {
        match ($mutation) {
            'save' => $service->saveRecord($actor, $caregiver->id, ['notes' => 'Updated'], 0),
            'complete' => $service->completeNextAction($actor, $caregiver->id, 0),
            'log' => $service->logActivity($actor, $caregiver->id, ['type' => 'note', 'body' => 'Contact note']),
        };
    }
}
