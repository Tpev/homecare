<?php

namespace Tests\Feature\AiSupport;

use App\Models\AiSupportControlVersion;
use App\Models\AiSupportPilotGrant;
use App\Models\KnowledgeBaseEntry;
use App\Models\KnowledgeBaseVersion;
use App\Models\User;
use App\Services\AiSupport\AiSupportPricingTruth;
use App\Services\AiSupport\PaymentTimeKnowledgeBaseCatalog;
use App\Services\AiSupport\PaymentTimeKnowledgeBaseImportService;
use App\Services\AiSupport\PaymentTimeKnowledgeEvaluationCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTimeKnowledgeContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_has_eighteen_entries_ninety_evaluations_and_complete_batch_four_mapping(): void
    {
        $catalog = app(PaymentTimeKnowledgeBaseCatalog::class);
        $entries = collect($catalog->entries());
        $cases = collect(app(PaymentTimeKnowledgeEvaluationCatalog::class)->cases());
        $intents = $entries->flatMap(fn (array $entry): array => $entry['intent_ids'])->unique();

        $this->assertCount(18, $entries);
        $this->assertCount(90, $cases);
        $this->assertCount(90, $cases->pluck('id')->unique());
        foreach (range(1, 32) as $number) {
            $this->assertContains(sprintf('FAM-PAY-%03d', $number), $intents);
        }
        foreach (range(18, 29) as $number) {
            $this->assertContains(sprintf('FAM-VISIT-%03d', $number), $intents);
        }
        $this->assertContains('FAM-VISIT-034', $intents);

        $pricing = $entries->firstWhere('stable_id', 'KB-B4-PRICE-001');
        $this->assertSame(['family', 'caregiver'], $pricing['roles']);
        $this->assertStringContainsString('Family processing fee', $pricing['answer_body']);
        $this->assertStringContainsString('booking snapshot', $pricing['answer_body']);
        $this->assertStringContainsString('payment ledger', $pricing['answer_body']);
        $this->assertContains('support_answers_v1', $pricing['capability_ids']);

        foreach ($entries as $entry) {
            $this->assertSame(
                ['boundary', 'handoff', 'positive', 'unsupported_state', 'wrong_role'],
                $cases->where('stable_id', $entry['stable_id'])->pluck('type')->sort()->values()->all(),
            );
        }
    }

    public function test_pricing_truth_reconciles_and_calculates_only_an_explicit_duration(): void
    {
        $pricing = app(AiSupportPricingTruth::class);

        config(['marketplace.pricing_v2.enabled' => true, 'marketplace.pricing_v2.family_care_hourly_cents' => 3000,
            'marketplace.pricing_v2.family_processing_fee_hourly_cents' => 100, 'marketplace.pricing_v2.caregiver_gross_hourly_cents' => 2700]);
        $this->assertStringContainsString('$30.00 care plus $1.00 Family processing fee', $pricing->familyAnswer('How much does care cost?'));
        $answer = $pricing->familyAnswer('What would 2.5 hours cost?');
        $this->assertStringContainsString('Family total is $77.50', $answer);
        $this->assertStringContainsString('care is $75.00', $answer);
        $this->assertStringContainsString('processing fee is $2.50', $answer);
        $this->assertStringContainsString(
            'I do not add taxes, tips, mileage, holiday charges, or surcharges',
            $pricing->familyAnswer('Are taxes or mileage added?'),
        );
        $this->assertTrue($pricing->isPricingQuestion('What is the caregiver rate?'));
        $caregiver = $pricing->answer('caregiver', 'What do I earn for 2 hours?');
        $this->assertStringContainsString('Caregiver gross earnings are $54.00', $caregiver);
        $this->assertStringContainsString('Family total is $62.00', $caregiver);
        $this->assertStringContainsString(
            'Family total is $62.00',
            $pricing->familyAnswer('How much does care cost for a two-hour visit?'),
        );
    }

    public function test_draft_import_is_idempotent_and_does_not_publish_or_change_pilot_controls(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $importer = app(PaymentTimeKnowledgeBaseImportService::class);

        $this->assertSame(18, $importer->plan()['counts']['creates']);
        $result = $importer->apply($admin);

        $this->assertCount(18, $result['created']);
        $this->assertSame(0, $result['published_count_after']);
        $this->assertSame(18, KnowledgeBaseEntry::query()
            ->whereIn('stable_id', PaymentTimeKnowledgeBaseCatalog::APPROVED_STABLE_IDS)
            ->whereHas('workingVersion', fn ($query) => $query
                ->where('status', KnowledgeBaseVersion::STATUS_DRAFT)
                ->whereNotNull('validated_at'))
            ->count());

        $again = $importer->apply($admin);
        $this->assertCount(18, $again['noops']);
        $this->assertCount(0, $again['created']);
        $this->assertDatabaseCount('ai_support_pilot_grants', 0);
        $this->assertDatabaseCount('ai_support_control_versions', 0);
    }

    public function test_pricing_reads_changed_server_defaults_without_repricing_specific_or_historical_care(): void
    {
        config(['marketplace.pricing_v2.enabled' => true, 'marketplace.pricing_v2.family_care_hourly_cents' => 4200,
            'marketplace.pricing_v2.family_processing_fee_hourly_cents' => 175, 'marketplace.pricing_v2.caregiver_gross_hourly_cents' => 3800]);
        $pricing = app(AiSupportPricingTruth::class);

        $family = $pricing->familyAnswer('What would 90 minutes cost?');
        $this->assertStringContainsString('care is $63.00', $family);
        $this->assertStringContainsString('processing fee is $2.63', $family);
        $this->assertStringContainsString('Family total is $65.63', $family);
        $this->assertStringContainsString('Caregiver gross earnings are $57.00', $pricing->answer('caregiver', 'What do I earn for 90 minutes?'));

        foreach (['What did my 2 hour visit cost?', 'Why was the previous booking charged this rate?', 'What is our agreement rate?'] as $question) {
            $answer = $pricing->familyAnswer($question);
            $this->assertStringNotContainsString('$', $answer);
            $this->assertStringContainsString('booking snapshot and payment ledger', $answer);
        }
        config(['marketplace.pricing_v2.enabled' => false]);
        $this->assertStringNotContainsString('$', $pricing->familyAnswer('What would 2 hours cost?'));
        $this->assertDatabaseCount('care_bookings', 0);
    }

    public function test_exact_publish_command_publishes_only_the_kb_package(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'batch4-kb@example.com']);

        $this->artisan('ai-support:import-payment-time-kb', [
            '--publish' => true,
            '--actor-email' => $admin->email,
            '--reason' => 'Publish approved Batch 4 pilot knowledge.',
            '--confirm' => 'PUBLISH-PAYMENT-TIME-KB',
        ])->expectsOutputToContain('18 entries published')
            ->expectsOutputToContain('pilot users, payment records, and visit records were not changed')
            ->assertSuccessful();

        $this->assertSame(18, KnowledgeBaseEntry::query()
            ->whereIn('stable_id', PaymentTimeKnowledgeBaseCatalog::APPROVED_STABLE_IDS)
            ->whereNotNull('published_version_id')
            ->count());
        $this->assertSame(0, AiSupportPilotGrant::query()->count());
        $this->assertSame(0, AiSupportControlVersion::query()->count());
    }
}
