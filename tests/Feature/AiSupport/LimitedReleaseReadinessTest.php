<?php

namespace Tests\Feature\AiSupport;

use App\Livewire\Admin\AiSupport\Readiness;
use App\Models\AiSupportIncident;
use App\Models\AiSupportInteractionEvent;
use App\Models\AiSupportReadinessEvidence;
use App\Models\AiSupportReleaseDecision;
use App\Models\KnowledgeBaseEntry;
use App\Models\KnowledgeBaseVersion;
use App\Models\MarketplaceNotificationDelivery;
use App\Models\User;
use App\Services\AiSupport\AiSupportControlService;
use App\Services\AiSupport\AiSupportHealthMonitorService;
use App\Services\AiSupport\AiSupportIncidentService;
use App\Services\AiSupport\AiSupportInitialPilotReleaseService;
use App\Services\AiSupport\AiSupportOpenAiClient;
use App\Services\AiSupport\AiSupportPilotGrantService;
use App\Services\AiSupport\AiSupportReadinessService;
use App\Services\AiSupport\InitialKnowledgeBaseImportService;
use App\Services\AiSupport\InteractiveKnowledgeBaseImportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class LimitedReleaseReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_is_admin_only_blocked_and_has_no_activation_action(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($family)->get(route('admin.ai-support.readiness'))->assertForbidden();
        $this->actingAs($admin)
            ->get(route('admin.ai-support.readiness'))
            ->assertOk()
            ->assertSee('Release readiness')
            ->assertSee('BLOCKED')
            ->assertSee('cannot enable AI')
            ->assertDontSee('Enable AI pilot');

        $definitions = app(AiSupportReadinessService::class)->definitions();
        $this->assertSame('Configured provider credential', $definitions['provider_project_configuration']['label']);
        $this->assertStringContainsString('not a gate', $definitions['cost_monitoring']['guidance']);
        $this->assertFalse((bool) config('ai_support.provider_monthly_budget_alert_required'));
    }

    public function test_one_admin_can_version_content_free_readiness_evidence_end_to_end(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)
            ->test(Readiness::class)
            ->set('evidenceKey', 'provider_data_controls')
            ->set('evidenceStatus', 'passed')
            ->set('evidenceSummary', 'No training opt-in; store false; default abuse monitoring is bounded to 30 days.')
            ->set('sourceReference', 'OpenAI data controls checked 2026-08-14')
            ->set('evidenceObservedAt', '2026-08-14')
            ->set('contentFreeConfirmed', true)
            ->call('recordEvidence')
            ->assertHasNoErrors();

        Livewire::actingAs($admin)
            ->test(Readiness::class)
            ->set('evidenceKey', 'provider_data_controls')
            ->set('evidenceStatus', 'pending')
            ->set('evidenceSummary', 'Annual recheck scheduled; prior evidence remains superseded for audit history.')
            ->set('evidenceObservedAt', '2026-08-14')
            ->set('contentFreeConfirmed', true)
            ->call('recordEvidence')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('ai_support_readiness_evidence', 2);
        $this->assertSame(1, AiSupportReadinessEvidence::query()->current()->count());
        $this->assertSame('pending', AiSupportReadinessEvidence::query()->current()->sole()->status);
        $this->assertDatabaseCount('ai_support_admin_audit_events', 2);
    }

    public function test_release_preflight_is_read_only_and_fails_while_required_evidence_is_missing(): void
    {
        $this->assertSame(1, Artisan::call('ai-support:release-preflight'));
        $this->assertStringContainsString('Read-only preflight', Artisan::output());
        $this->assertDatabaseCount('ai_support_readiness_evidence', 0);
        $this->assertDatabaseCount('ai_support_control_versions', 0);
        $this->assertDatabaseCount('ai_support_pilot_grants', 0);
    }

    public function test_system_stop_opens_one_visible_incident_and_resolution_does_not_reenable_control(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $controls = app(AiSupportControlService::class);
        $controls->set($admin, 'capability.support_answers_v1', true, 'Prepare a synthetic automatic-stop test');

        $controls->systemStop(
            'capability.support_answers_v1',
            'synthetic_critical_stop',
            'Synthetic critical stop for incident visibility verification.',
        );
        $controls->systemStop(
            'capability.support_answers_v1',
            'synthetic_critical_stop',
            'Synthetic critical stop for incident visibility verification.',
        );

        $incident = AiSupportIncident::query()->sole();
        $this->assertSame(AiSupportIncident::STATUS_OPEN, $incident->status);
        $this->assertFalse($controls->enabled('capability.support_answers_v1'));
        $this->assertSame(2, MarketplaceNotificationDelivery::query()->count());

        app(AiSupportIncidentService::class)->resolve($admin, $incident, 'Verified the synthetic failure and kept the capability disabled.');
        $this->assertSame(AiSupportIncident::STATUS_RESOLVED, $incident->fresh()->status);
        $this->assertFalse($controls->enabled('capability.support_answers_v1'));
    }

    public function test_runtime_provider_uses_hmac_safety_identifier_and_versioned_current_cost_rates(): void
    {
        config([
            'ai_support.runtime_available' => true,
            'ai_support.provider_enabled' => true,
            'ai_support.safety_identifier_secret' => 'a-dedicated-test-secret-that-is-long-enough',
            'services.openai.api_key' => 'test-key',
        ]);
        Http::fake(['*/responses' => Http::response([
            'output' => [[
                'type' => 'message',
                'content' => [['type' => 'output_text', 'text' => json_encode($this->emptyResult(), JSON_THROW_ON_ERROR)]],
            ]],
            'usage' => [
                'input_tokens' => 400,
                'input_tokens_details' => ['cached_tokens' => 100],
                'output_tokens' => 60,
            ],
        ])]);

        $response = app(AiSupportOpenAiClient::class)->respond('Safe instructions', 'Synthetic input', 42);

        $this->assertSame(134, $response['cost_microunits']);
        $this->assertSame('openai-gpt-5.6-luna-2026-08-14', $response['price_version']);
        Http::assertSent(function ($request): bool {
            $identifier = (string) ($request->data()['safety_identifier'] ?? '');

            return strlen($identifier) === 64
                && $identifier !== '42'
                && $request->data()['store'] === false
                && $request->data()['parallel_tool_calls'] === false
                && ! array_key_exists('background', $request->data())
                && ! array_key_exists('tools', $request->data());
        });
    }

    public function test_operations_alert_command_is_plan_only_then_records_pending_until_human_receipt_confirmation(): void
    {
        Notification::fake();
        $adminOne = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'admin']);

        $this->assertSame(0, Artisan::call('ai-support:test-operations-alert'));
        $this->assertDatabaseCount('marketplace_notification_deliveries', 0);
        $this->assertDatabaseCount('ai_support_readiness_evidence', 0);

        $this->assertSame(0, Artisan::call('ai-support:test-operations-alert', [
            '--send' => true,
            '--actor-email' => $adminOne->email,
            '--confirm' => 'SEND-CONTENT-FREE-ALERT',
        ]));
        $this->assertDatabaseCount('marketplace_notification_deliveries', 4);
        $evidence = AiSupportReadinessEvidence::query()->where('evidence_key', 'operations_alert_delivery')->sole();
        $this->assertSame(AiSupportReadinessEvidence::STATUS_PENDING, $evidence->status);
        $this->assertStringContainsString('inbox receipt still requires human confirmation', $evidence->summary);
    }

    public function test_health_monitor_opens_one_latency_warning_only_after_the_minimum_sample(): void
    {
        Notification::fake();
        User::factory()->create(['role' => 'admin']);
        foreach (range(1, 20) as $index) {
            AiSupportInteractionEvent::query()->create([
                'id' => (string) Str::uuid(),
                'event_type' => 'model_turn_completed',
                'latency_ms' => 6_000 + $index,
                'event_contract_version' => 'support-event-v1',
                'occurred_at' => now(),
                'created_at' => now(),
            ]);
        }

        $result = app(AiSupportHealthMonitorService::class)->run();
        app(AiSupportHealthMonitorService::class)->run();

        $this->assertTrue($result['conversation_warning']);
        $this->assertGreaterThan(6_000, $result['conversation_p95_ms']);
        $this->assertDatabaseCount('ai_support_incidents', 1);
        $this->assertDatabaseHas('ai_support_incidents', [
            'reason_code' => 'conversation_latency_p95_warning',
            'status' => AiSupportIncident::STATUS_OPEN,
            'severity' => AiSupportIncident::SEVERITY_WARNING,
        ]);
        $snapshot = app(AiSupportReadinessService::class)->snapshot(app(AiSupportControlService::class));
        $this->assertSame(0, $snapshot['open_incidents']);
        $this->assertSame(1, $snapshot['open_warnings']);
        $this->assertTrue(collect($snapshot['checks'])->firstWhere('id', 'incidents')['passed']);
    }

    public function test_health_monitor_does_not_reopen_an_incident_for_alert_failures_before_a_passed_recovery_checkpoint(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $failedAt = now()->subMinutes(20);
        $recoveredAt = now()->subMinutes(10);

        $historicalFailure = MarketplaceNotificationDelivery::query()->create([
            'user_id' => $admin->id,
            'event_key' => 'support_ticket_reply',
            'channel' => 'email',
            'status' => 'failed',
            'dedupe_key' => 'ai-support-operations-test-historical:user-'.$admin->id.':email',
            'payload' => ['provider_error' => 'Synthetic historical failure.'],
        ]);
        MarketplaceNotificationDelivery::query()->whereKey($historicalFailure->id)->update([
            'created_at' => $failedAt,
            'updated_at' => $failedAt,
        ]);
        AiSupportReadinessEvidence::query()->create([
            'id' => (string) Str::uuid(),
            'evidence_key' => 'operations_alert_delivery',
            'version' => 1,
            'status' => AiSupportReadinessEvidence::STATUS_PASSED,
            'summary' => 'Synthetic recovery checkpoint with confirmed delivery.',
            'recorded_by_user_id' => $admin->id,
            'observed_at' => $recoveredAt,
            'retain_until' => now()->addYear(),
            'created_at' => $recoveredAt,
        ]);

        $recoveredResult = app(AiSupportHealthMonitorService::class)->run();

        $this->assertSame(0, $recoveredResult['failed_notifications']);
        $this->assertDatabaseCount('ai_support_incidents', 0);

        MarketplaceNotificationDelivery::query()->create([
            'user_id' => $admin->id,
            'event_key' => 'support_ticket_reply',
            'channel' => 'email',
            'status' => 'failed',
            'dedupe_key' => 'ai-support-operations-test-new:user-'.$admin->id.':email',
            'payload' => ['provider_error' => 'Synthetic new failure.'],
        ]);

        $newFailureResult = app(AiSupportHealthMonitorService::class)->run();

        $this->assertSame(1, $newFailureResult['failed_notifications']);
        $this->assertDatabaseHas('ai_support_incidents', [
            'reason_code' => 'operations_notification_failed',
            'status' => AiSupportIncident::STATUS_OPEN,
        ]);
    }

    public function test_operations_alert_recovery_does_not_hide_a_handoff_notification_failure(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $failedAt = now()->subMinutes(20);
        $recoveredAt = now()->subMinutes(10);

        $handoffFailure = MarketplaceNotificationDelivery::query()->create([
            'user_id' => $admin->id,
            'event_key' => 'support_ticket_reply',
            'channel' => 'email',
            'status' => 'failed',
            'dedupe_key' => 'support-handoff-historical:user-'.$admin->id.':email',
            'payload' => ['provider_error' => 'Synthetic handoff failure.'],
        ]);
        MarketplaceNotificationDelivery::query()->whereKey($handoffFailure->id)->update([
            'created_at' => $failedAt,
            'updated_at' => $failedAt,
        ]);
        AiSupportReadinessEvidence::query()->create([
            'id' => (string) Str::uuid(),
            'evidence_key' => 'operations_alert_delivery',
            'version' => 1,
            'status' => AiSupportReadinessEvidence::STATUS_PASSED,
            'summary' => 'Synthetic operations-alert recovery checkpoint.',
            'recorded_by_user_id' => $admin->id,
            'observed_at' => $recoveredAt,
            'retain_until' => now()->addYear(),
            'created_at' => $recoveredAt,
        ]);

        $result = app(AiSupportHealthMonitorService::class)->run();

        $this->assertSame(1, $result['failed_notifications']);
        $this->assertDatabaseHas('ai_support_incidents', [
            'reason_code' => 'operations_notification_failed',
            'status' => AiSupportIncident::STATUS_OPEN,
        ]);
    }

    public function test_expired_operations_alert_evidence_is_not_a_recovery_checkpoint(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $failedAt = now()->subMinutes(20);

        $historicalFailure = MarketplaceNotificationDelivery::query()->create([
            'user_id' => $admin->id,
            'event_key' => 'support_ticket_reply',
            'channel' => 'email',
            'status' => 'failed',
            'dedupe_key' => 'ai-support-operations-test-historical:user-'.$admin->id.':email',
            'payload' => ['provider_error' => 'Synthetic historical failure.'],
        ]);
        MarketplaceNotificationDelivery::query()->whereKey($historicalFailure->id)->update([
            'created_at' => $failedAt,
            'updated_at' => $failedAt,
        ]);
        AiSupportReadinessEvidence::query()->create([
            'id' => (string) Str::uuid(),
            'evidence_key' => 'operations_alert_delivery',
            'version' => 1,
            'status' => AiSupportReadinessEvidence::STATUS_PASSED,
            'summary' => 'Synthetic expired recovery checkpoint.',
            'recorded_by_user_id' => $admin->id,
            'observed_at' => now()->subMinutes(10),
            'expires_at' => now()->subMinute(),
            'retain_until' => now()->addYear(),
            'created_at' => now()->subMinutes(10),
        ]);

        $result = app(AiSupportHealthMonitorService::class)->run();

        $this->assertSame(1, $result['failed_notifications']);
        $this->assertDatabaseHas('ai_support_incidents', [
            'reason_code' => 'operations_notification_failed',
            'status' => AiSupportIncident::STATUS_OPEN,
        ]);
    }

    public function test_option_b_deferrals_are_distinct_and_satisfy_only_initial_pilot_scope(): void
    {
        config(['ai_support.initial_pilot.enforced' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'option-b-admin@example.com']);

        $this->artisan('ai-support:record-option-b-deferrals')
            ->expectsOutputToContain('Plan only')
            ->assertSuccessful();
        $this->assertDatabaseCount('ai_support_readiness_evidence', 0);

        $this->artisan('ai-support:record-option-b-deferrals', [
            '--apply' => true,
            '--actor-email' => $admin->email,
            '--confirm' => 'DEFER-SIX-GATES-BEFORE-EXPANSION',
        ])->expectsOutputToContain('Recorded 6 deferred-before-expansion')
            ->expectsOutputToContain('No item was marked Passed')
            ->assertSuccessful();

        $this->assertSame(6, AiSupportReadinessEvidence::query()
            ->where('status', AiSupportReadinessEvidence::STATUS_DEFERRED)->count());
        $this->assertSame(0, AiSupportReadinessEvidence::query()
            ->where('status', AiSupportReadinessEvidence::STATUS_PASSED)->count());

        $readiness = app(AiSupportReadinessService::class);
        $controls = app(AiSupportControlService::class);
        $initial = $readiness->snapshot($controls, AiSupportReadinessService::SCOPE_INITIAL_PILOT);
        $expansion = $readiness->snapshot($controls, AiSupportReadinessService::SCOPE_EXPANSION);
        $initialDeferred = collect($initial['checks'])->where('state', 'deferred');

        $this->assertCount(6, $initialDeferred);
        $this->assertTrue($initialDeferred->every(fn (array $check): bool => ! $check['passed'] && $check['satisfied']));
        $this->assertSame(6, $initial['deferred_count']);
        $this->assertSame(0, $expansion['deferred_count']);
        foreach ($readiness->initialPilotDeferredEvidenceKeys() as $key) {
            $check = collect($expansion['checks'])->firstWhere('id', 'evidence.'.$key);
            $this->assertSame('blocked', $check['state']);
            $this->assertFalse($check['satisfied']);
        }

        Livewire::actingAs($admin)
            ->test(Readiness::class)
            ->set('evidenceKey', 'monitoring_ownership')
            ->set('evidenceStatus', 'deferred')
            ->set('evidenceSummary', 'This non-deferrable item must be rejected.')
            ->set('evidenceObservedAt', '2026-08-15')
            ->set('contentFreeConfirmed', true)
            ->call('recordEvidence')
            ->assertHasErrors(['evidenceStatus']);
    }

    public function test_initial_pilot_requires_ready_preflight_explicit_decision_and_exact_release_boundary(): void
    {
        CarbonImmutable::setTestNow('2026-08-15 10:00:00');

        try {
            config([
                'ai_support.initial_pilot.enforced' => true,
                'services.openai.api_key' => 'sk-proj-synthetic-test-key',
            ]);
            $admin = User::factory()->create(['id' => 1, 'role' => 'admin', 'email' => 'release-admin@example.com']);
            $familyOne = User::factory()->create(['id' => 19, 'role' => 'family']);
            $familyTwo = User::factory()->create(['id' => 282, 'role' => 'family']);
            $outsideFamily = User::factory()->create(['id' => 300, 'role' => 'family']);
            $this->seedReadyKnowledge($admin);
            $this->recordRequiredPassedEvidence($admin);

            $this->artisan('ai-support:record-option-b-deferrals', [
                '--apply' => true,
                '--actor-email' => $admin->email,
                '--confirm' => 'DEFER-SIX-GATES-BEFORE-EXPANSION',
            ])->assertSuccessful();

            $this->artisan('ai-support:release-preflight', ['--scope' => 'initial-pilot'])
                ->expectsOutputToContain('READY FOR EXPLICIT INITIAL PILOT APPROVAL')
                ->expectsOutputToContain('DEFERRED BEFORE EXPANSION')
                ->assertSuccessful();
            $this->artisan('ai-support:release-preflight', ['--scope' => 'expansion'])
                ->expectsOutputToContain('BLOCKED')
                ->assertFailed();

            try {
                app(AiSupportControlService::class)->set($admin, 'master_enabled', true, 'Must wait for explicit release approval');
                $this->fail('Expected release-decision gating.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('releaseDecision', $exception->errors());
            }

            $commit = trim(Process::path(base_path())->run(['git', 'rev-parse', 'HEAD'])->output());
            $this->artisan('ai-support:approve-initial-pilot-release', [
                '--actor-email' => $admin->email,
                '--release-commit' => $commit,
                '--reason' => 'Approve the exact two-user DEC-070 pilot after the green read-only preflight.',
            ])->expectsOutputToContain('Plan only')
                ->assertSuccessful();
            $this->assertDatabaseCount('ai_support_release_decisions', 0);

            $this->artisan('ai-support:approve-initial-pilot-release', [
                '--approve' => true,
                '--actor-email' => $admin->email,
                '--release-commit' => $commit,
                '--reason' => 'Approve the exact two-user DEC-070 pilot after the green read-only preflight.',
                '--confirm' => 'APPROVE-EXACT-TWO-USER-PILOT',
            ])->expectsOutputToContain('Explicit initial-pilot release decision recorded')
                ->expectsOutputToContain('No control, grant, provider, or application state was changed')
                ->assertSuccessful();

            $decision = AiSupportReleaseDecision::query()->sole();
            $this->assertTrue($decision->isEffective());
            $this->assertSame([19, 282], $decision->approved_user_ids);
            $this->assertSame($decision->id, app(AiSupportInitialPilotReleaseService::class)->effectiveApproval()?->id);
            Process::fake(fn () => Process::result(errorOutput: 'git process unavailable to the web user', exitCode: 1));
            $this->assertSame($decision->id, app(AiSupportInitialPilotReleaseService::class)->effectiveApproval()?->id);
            Process::assertNothingRan();
            $this->assertDatabaseCount('ai_support_pilot_grants', 0);
            $this->assertDatabaseCount('ai_support_control_versions', 0);

            $controls = app(AiSupportControlService::class);
            $controls->set($admin, 'master_enabled', true, 'Open only the explicitly approved two-user pilot');
            try {
                $controls->set($admin, 'role.caregiver', true, 'Must remain outside initial-pilot scope');
                $this->fail('Expected out-of-scope control denial.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('controlKey', $exception->errors());
            }

            $grants = app(AiSupportPilotGrantService::class);
            try {
                $grants->grant(
                    $admin,
                    $outsideFamily,
                    'family_support_v1',
                    CarbonImmutable::now(),
                    CarbonImmutable::now()->addDay(),
                    'Must remain outside exact scope',
                    (string) Str::uuid(),
                );
                $this->fail('Expected exact-user boundary denial.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('grant', $exception->errors());
            }
            try {
                $grants->grant(
                    $admin,
                    $familyOne,
                    'family_support_v1',
                    CarbonImmutable::now(),
                    CarbonImmutable::now()->addDays(14)->addMinute(),
                    'Must remain inside fourteen days',
                    (string) Str::uuid(),
                );
                $this->fail('Expected 14-day boundary denial.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('grantExpiresAt', $exception->errors());
            }

            foreach ([$familyOne, $familyTwo] as $family) {
                $grants->grant(
                    $admin,
                    $family,
                    'family_support_v1',
                    CarbonImmutable::now(),
                    CarbonImmutable::now()->addDays(14),
                    'Exact DEC-070 initial pilot grant',
                    (string) Str::uuid(),
                );
            }
            $this->assertDatabaseCount('ai_support_pilot_grants', 2);

            $decision->forceFill(['release_commit' => str_repeat('a', 40)])->save();
            $this->assertNull(app(AiSupportInitialPilotReleaseService::class)->effectiveApproval());
            try {
                $controls->set($admin, 'user_visible_enabled', true, 'A later deployment must require a new exact-commit decision');
                $this->fail('Expected stale exact-commit decision denial.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('releaseDecision', $exception->errors());
            }
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_release_rehearsal_is_plan_only_by_default(): void
    {
        Storage::fake('local');

        $this->assertSame(0, Artisan::call('ai-support:rehearse-release'));
        $this->assertStringContainsString('Plan only', Artisan::output());
        Storage::disk('local')->assertMissing('ai-support/rehearsal-latest.json');
        $this->assertDatabaseCount('ai_support_readiness_evidence', 0);
    }

    private function seedReadyKnowledge(User $admin): void
    {
        app(InitialKnowledgeBaseImportService::class)->apply($admin);
        app(InteractiveKnowledgeBaseImportService::class)->apply($admin);

        KnowledgeBaseEntry::query()->with('workingVersion')->get()->each(function (KnowledgeBaseEntry $entry): void {
            if ($entry->stable_id === 'KB-CARE-006') {
                return;
            }
            $version = $entry->workingVersion;
            $version->forceFill([
                'status' => KnowledgeBaseVersion::STATUS_PUBLISHED,
                'published_at' => now(),
            ])->save();
            $entry->forceFill(['published_version_id' => $version->id])->save();
        });
    }

    private function recordRequiredPassedEvidence(User $admin): void
    {
        $readiness = app(AiSupportReadinessService::class);
        foreach ([
            'provider_project_configuration',
            'provider_deletion',
            'monitoring_ownership',
            'cost_monitoring',
            'operations_alert_delivery',
            'staff_rehearsal',
            'pilot_users_named',
        ] as $key) {
            $readiness->record(
                $admin,
                $key,
                AiSupportReadinessEvidence::STATUS_PASSED,
                'Synthetic content-free Passed evidence for the complete initial-pilot preflight test.',
                'synthetic:'.$key,
                CarbonImmutable::now(),
                CarbonImmutable::now()->addDays(14),
            );
        }
    }

    /** @return array<string,mixed> */
    private function emptyResult(): array
    {
        return [
            'operation' => 'answer',
            'message' => 'Synthetic safe answer.',
            'navigation_target_id' => null,
            'care_path' => null,
            'clarifying_question' => null,
            'confidence_band' => 'clear',
            'kb_stable_ids' => [],
            'draft_patch' => [
                'patch_fields' => [],
                'recipient_is_requester' => null,
                'recipient_profile_id' => null,
                'recipient_full_name' => null,
                'recipient_relationship' => null,
                'task_ids' => [],
                'task_notes' => [],
                'requested_start_date' => null,
                'requested_start_time' => null,
                'duration_minutes' => null,
                'recurring_days' => [],
                'recurring_schedule' => [],
                'recurring_starts_on' => null,
                'recurring_ends_on' => null,
                'address_line1' => null,
                'address_line2' => null,
                'city' => null,
                'state' => null,
                'zip' => null,
                'additional_info' => null,
                'home_access_notes' => null,
                'preferred_response_hours' => null,
            ],
        ];
    }
}
