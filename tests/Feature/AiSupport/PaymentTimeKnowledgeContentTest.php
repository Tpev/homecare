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
        $this->assertStringContainsString('$30 per hour', $pricing['answer_body']);
        $this->assertStringContainsString('$27 per hour', $pricing['answer_body']);
        $this->assertStringContainsString('$1 per hour processing fee', $pricing['answer_body']);
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

        $this->assertSame(3100, AiSupportPricingTruth::CAREGIVER_HOURLY_CENTS + AiSupportPricingTruth::PLATFORM_HOURLY_CENTS);
        $this->assertSame(
            'Care costs $30 per hour for the Family, plus a $1 per hour processing fee ($31 per hour total). The caregiver earns $27 per hour gross, minus the actual Stripe processing fees on successful family charges. Refund costs, dispute fees, and optional instant-payout fees are not deducted from the caregiver rate.',
            $pricing->familyAnswer('How much does care cost?'),
        );
        $answer = $pricing->familyAnswer('What would 2.5 hours cost?');
        $this->assertStringContainsString('Family total is $77.50', $answer);
        $this->assertStringContainsString('caregiver gross earnings are $67.50', $answer);
        $this->assertStringContainsString('platform portion is $10.00', $answer);
        $this->assertStringContainsString(
            'I do not add taxes, tips, mileage, holiday charges, or surcharges',
            $pricing->familyAnswer('Are taxes or mileage added?'),
        );
        $this->assertTrue($pricing->isPricingQuestion('What is the caregiver rate?'));
        $caregiver = $pricing->answer('caregiver', 'What do I earn for 2 hours?');
        $this->assertStringContainsString('caregiver earnings are $54.00', $caregiver);
        $this->assertStringContainsString('Family total is $62.00', $caregiver);
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
