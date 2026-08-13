<?php

namespace Tests\Feature\AiSupport;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyCopilotRetirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_route_and_runtime_are_absent_while_manual_care_and_human_support_remain(): void
    {
        $family = User::factory()->create(['role' => 'family']);

        $this->actingAs($family)
            ->get('/family/requests/create/ai')
            ->assertNotFound();

        $this->actingAs($family)
            ->get(route('family.requests.create'))
            ->assertOk();

        $this->actingAs($family)
            ->get(route('support.index'))
            ->assertOk();

        $this->assertFileDoesNotExist(app_path('Livewire/Family/AiRequestCopilot.php'));
        $this->assertFileDoesNotExist(app_path('Models/AiRequestSession.php'));
        $this->assertFileDoesNotExist(app_path('Contracts/AiCopilotResponder.php'));
    }

    public function test_destruction_command_is_dry_run_by_default(): void
    {
        $this->artisan('ai-support:destroy-legacy-copilot-data', [
            '--environment' => 'testing',
        ])
            ->expectsOutputToContain('Dry run only')
            ->assertSuccessful();

        $this->assertDatabaseCount('legacy_copilot_destruction_runs', 0);
    }

    public function test_guarded_command_deletes_only_legacy_tables_and_records_content_free_evidence(): void
    {
        Schema::create('ai_request_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('draft_json')->nullable();
        });
        Schema::create('ai_request_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('ai_request_session_id');
            $table->longText('content_text');
        });

        DB::table('ai_request_sessions')->insert([
            'id' => '11111111-1111-4111-8111-111111111111',
            'draft_json' => '{"private":"must be destroyed"}',
        ]);
        DB::table('ai_request_messages')->insert([
            'ai_request_session_id' => '11111111-1111-4111-8111-111111111111',
            'content_text' => 'private legacy message',
        ]);

        $database = (string) config('database.connections.'.config('database.default').'.database');

        $this->artisan('ai-support:destroy-legacy-copilot-data', [
            '--environment' => 'testing',
            '--execute' => true,
            '--confirm' => 'DESTROY-LEGACY-COPILOT-DATA:testing:'.$database,
            '--operator' => 'Test operator',
            '--approver' => ['Privacy approver', 'Database approver'],
            '--derived-targets-verified' => true,
            '--backup-status' => 'Ephemeral in-memory test database; no backups.',
            '--code-version' => 'test-suite',
        ])->assertSuccessful();

        $this->assertDatabaseCount('ai_request_messages', 0);
        $this->assertDatabaseCount('ai_request_sessions', 0);
        $this->assertDatabaseHas('legacy_copilot_destruction_runs', [
            'environment' => 'testing',
            'operator_name' => 'Test operator',
            'verification_result' => 'passed',
            'code_version' => 'test-suite',
        ]);

        $evidence = (array) DB::table('legacy_copilot_destruction_runs')->first();
        $serialized = json_encode($evidence, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('private legacy message', $serialized);
        $this->assertStringNotContainsString('must be destroyed', $serialized);
    }
}
