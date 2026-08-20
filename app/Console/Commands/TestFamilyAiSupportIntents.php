<?php

namespace App\Console\Commands;

use App\Services\AiSupport\Batch5FamilyIntentEvaluationCatalog;
use App\Services\AiSupport\FamilyAdministrationKnowledgeEvaluationCatalog;
use App\Services\AiSupport\FamilyIntentCatalog;
use App\Services\AiSupport\FamilyIntentEvaluationCatalog;
use App\Services\AiSupport\FamilyIntentResolver;
use App\Services\AiSupport\MarketplaceCareKnowledgeEvaluationCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class TestFamilyAiSupportIntents extends Command
{
    protected $signature = 'ai-support:test-family-intents
        {--plan : Validate and display the selected corpus without running application tests}
        {--batch=* : Optional Batch number (1, 2, 4, 5, 6, 7, 8, or 9)}
        {--domain=* : Optional exact domain such as payments, visits, or profiles}
        {--intent=* : Optional exact registry intent ID}
        {--output= : Optional content-minimized JSON report path on the local storage disk}';

    protected $description = 'Validate all 324 Family intents and mass-test implemented Family journeys in isolated SQLite.';

    public function handle(
        FamilyIntentEvaluationCatalog $catalog,
        FamilyIntentCatalog $fullCatalog,
        Batch5FamilyIntentEvaluationCatalog $batch5Catalog,
        FamilyIntentResolver $resolver,
        MarketplaceCareKnowledgeEvaluationCatalog $marketplaceKnowledge,
        FamilyAdministrationKnowledgeEvaluationCatalog $administrationKnowledge,
    ): int {
        try {
            $manifest = $catalog->manifest();
            $fullManifest = $fullCatalog->manifest();
            $this->validatePortfolioFilters($manifest['cases'], $fullManifest['records']);
            $cases = $this->selectedCases($manifest['cases']);
            $marketplaceRecords = $this->selectedMarketplaceRecords($fullManifest['records']);
            $batch5Manifest = $batch5Catalog->manifest();
            $marketplaceCases = $marketplaceKnowledge->cases();
            $administrationRecords = $this->selectedAdministrationRecords($fullManifest['records']);
            $administrationCases = $administrationKnowledge->cases();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $phraseCount = array_sum(array_map(static fn (array $case): int => count($case['phrases']), $cases));
        $fullPhraseCount = collect($fullManifest['records'])->sum(fn (array $record): int => count((array) data_get($record, 'phrases.ordinary', [])) + count((array) data_get($record, 'phrases.imperfect', [])));
        $this->table(['Property', 'Value'], [
            ['Executable catalog', $fullManifest['version']],
            ['Catalog registry intents', count($fullManifest['records']).' / 324'],
            ['Explicit KB mappings', $fullCatalog->coverageSummary()['kb_mapped'].' / 324'],
            ['Catalog phrase definitions', $fullPhraseCount],
            ['Runtime corpus', $manifest['version']],
            ['Frozen on', $manifest['frozen_on']],
            ['Implemented runtime intents', count($cases).' / '.count($manifest['cases'])],
            ['Implemented routing phrases', $phraseCount],
            ['Batch 5 lifecycle intents', count($batch5Manifest['cases']).' / 21'],
            ['Batch 6/7 runtime intents', count($marketplaceRecords).' / 86'],
            ['Batch 6/7 routing phrases', collect($marketplaceRecords)->sum(fn (array $record): int => count((array) data_get($record, 'phrases.ordinary', [])) + count((array) data_get($record, 'phrases.imperfect', [])))],
            ['Batch 6/7 KB evaluations', count($marketplaceCases).' / 160'],
            ['Batch 8/9 runtime intents', count($administrationRecords).' / 118'],
            ['Batch 8/9 routing phrases', collect($administrationRecords)->sum(fn (array $record): int => count((array) data_get($record, 'phrases.ordinary', [])) + count((array) data_get($record, 'phrases.imperfect', [])))],
            ['Batch 8/9 KB evaluations', count($administrationCases).' / 220'],
            ['Near-neighbor collision cases', count($manifest['negative_cases'])],
            ['Provider calls', '0'],
            ['Database', 'isolated SQLite :memory:'],
        ]);

        $routing = $this->evaluateRouting($catalog, $cases, $manifest['negative_cases']);
        $batch5Routing = $batch5Catalog->evaluate();
        $marketplaceRouting = $this->evaluateMarketplaceRouting($resolver, $marketplaceRecords);
        $administrationRouting = $this->evaluateMarketplaceRouting($resolver, $administrationRecords);
        if ($this->option('plan')) {
            $routingPassed = $routing['passed'] && $batch5Routing['passed'] && $marketplaceRouting['passed'] && $administrationRouting['passed'];
            $this->line('Routing precheck: '.($routingPassed ? 'PASS' : 'FAIL'));
            foreach ([...$routing['failures'], ...$batch5Routing['failures'], ...$marketplaceRouting['failures'], ...$administrationRouting['failures']] as $failure) {
                $this->error($failure);
            }
            $this->warn('Plan only. All 324 catalog records were validated. No test database, provider call, production write, or report write occurred.');

            return $routingPassed ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->info('Running the full Family operating-layer application regression in an isolated test process...');
        $runtime = $this->runIsolatedApplicationTests();
        $passed = $routing['passed'] && $batch5Routing['passed'] && $marketplaceRouting['passed'] && $administrationRouting['passed'] && $runtime['passed'];

        $report = $this->report($manifest, $cases, $routing, $runtime, $passed);
        $this->table(['Result', 'Value'], [
            ['Routing phrases', $routing['passed_phrases'].' / '.$routing['total_phrases'].' passed'],
            ['Batch 5 routing phrases', $batch5Routing['passed_phrases'].' / '.$batch5Routing['total_phrases'].' passed'],
            ['Batch 6/7 routing phrases', $marketplaceRouting['passed_phrases'].' / '.$marketplaceRouting['total_phrases'].' passed'],
            ['Batch 6/7 runtime intents', $marketplaceRouting['passed_intents'].' / '.$marketplaceRouting['total_intents'].' passed'],
            ['Batch 8/9 routing phrases', $administrationRouting['passed_phrases'].' / '.$administrationRouting['total_phrases'].' passed'],
            ['Batch 8/9 runtime intents', $administrationRouting['passed_intents'].' / '.$administrationRouting['total_intents'].' passed'],
            ['Executable catalog', count($fullManifest['records']).' / 324 valid'],
            ['Explicit KB mappings', $fullCatalog->coverageSummary()['kb_mapped'].' / 324 valid'],
            ['Collision cases', $routing['passed_negative_cases'].' / '.$routing['total_negative_cases'].' passed'],
            ['Application regression', $runtime['passed'] ? 'PASS' : 'FAIL'],
            ['Registry intents', $report['summary']['passed_intents'].' / '.$report['summary']['selected_intents'].' passed'],
            ['Overall', $passed ? 'PASS' : 'FAIL'],
        ]);

        foreach ([...$routing['failures'], ...$batch5Routing['failures'], ...$marketplaceRouting['failures'], ...$administrationRouting['failures']] as $failure) {
            $this->error($failure);
        }

        $output = trim((string) $this->option('output'));
        if ($output !== '') {
            $output = ltrim(str_replace('..', '', str_replace('\\', '/', $output)), '/');
            Storage::disk('local')->put(
                $output,
                json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            );
            $this->info('Content-minimized report written to local storage: '.$output);
        }

        if ($passed) {
            $this->info('Family Batch 1-9 intent evaluation passed. No provider or production database was used.');
        }

        return $passed ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<array<string,mixed>>  $cases
     * @return list<array<string,mixed>>
     */
    private function selectedCases(array $cases): array
    {
        $batches = array_values(array_unique(array_filter(array_map('intval', (array) $this->option('batch')))));
        $domains = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => mb_strtolower(trim((string) $value)),
            (array) $this->option('domain'),
        ))));
        $intents = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => mb_strtoupper(trim((string) $value)),
            (array) $this->option('intent'),
        ))));

        $legacyBatches = array_values(array_intersect($batches, [1, 2, 4]));
        if ($batches !== [] && $legacyBatches === []) {
            return [];
        }

        $knownDomains = array_values(array_unique(array_column($cases, 'domain')));
        $legacyDomains = array_values(array_intersect($domains, $knownDomains));

        $knownIntents = array_column($cases, 'intent_id');
        $legacyIntents = array_values(array_intersect($intents, $knownIntents));
        if (($domains !== [] && $legacyDomains === []) || ($intents !== [] && $legacyIntents === [])) {
            return [];
        }

        $selected = array_values(array_filter($cases, static function (array $case) use ($legacyBatches, $legacyDomains, $legacyIntents): bool {
            return ($legacyBatches === [] || in_array((int) $case['batch'], $legacyBatches, true))
                && ($legacyDomains === [] || in_array($case['domain'], $legacyDomains, true))
                && ($legacyIntents === [] || in_array($case['intent_id'], $legacyIntents, true));
        }));

        if ($selected === [] && $batches === []) {
            throw new \InvalidArgumentException('The supplied filters select no Batch 1/2/4 Family intents.');
        }

        return $selected;
    }

    /** @param list<array<string,mixed>> $legacyCases @param list<array<string,mixed>> $records */
    private function validatePortfolioFilters(array $legacyCases, array $records): void
    {
        $batches = array_values(array_unique(array_filter(array_map('intval', (array) $this->option('batch')))));
        if (array_diff($batches, [1, 2, 4, 5, 6, 7, 8, 9]) !== []) {
            throw new \InvalidArgumentException('Batch filters must be 1, 2, 4, 5, 6, 7, 8, or 9.');
        }
        $domains = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => mb_strtolower(trim((string) $value)),
            (array) $this->option('domain'),
        ))));
        $knownDomains = array_values(array_unique([
            ...array_column($legacyCases, 'domain'),
            ...array_column($records, 'domain'),
            'visits',
        ]));
        if ($unknown = array_diff($domains, $knownDomains)) {
            throw new \InvalidArgumentException('Unknown domain: '.implode(', ', $unknown).'. Known domains: '.implode(', ', $knownDomains).'.');
        }
        $intents = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => mb_strtoupper(trim((string) $value)),
            (array) $this->option('intent'),
        ))));
        if ($unknown = array_diff($intents, array_column($records, 'intent_id'))) {
            throw new \InvalidArgumentException('Unknown Family intent: '.implode(', ', $unknown).'.');
        }
    }

    /** @param list<array<string,mixed>> $records @return list<array<string,mixed>> */
    private function selectedMarketplaceRecords(array $records): array
    {
        $selected = collect($records)->filter(fn (array $record): bool => preg_match('/^FAM-(MATCH|VISIT|REGULAR)-/', (string) $record['intent_id']) === 1);
        $batches = array_values(array_unique(array_filter(array_map('intval', (array) $this->option('batch')))));
        if ($batches !== []) {
            $selected = $selected->filter(fn (array $record): bool => (in_array(6, $batches, true) && str_starts_with((string) $record['intent_id'], 'FAM-MATCH-'))
                || (in_array(7, $batches, true) && preg_match('/^FAM-(VISIT|REGULAR)-/', (string) $record['intent_id']) === 1));
        }
        $domains = collect((array) $this->option('domain'))->map(fn ($value): string => match (mb_strtolower(trim((string) $value))) {
            'visits' => 'visits_timesheets',
            default => mb_strtolower(trim((string) $value)),
        })->filter()->unique()->values();
        if ($domains->isNotEmpty()) {
            $selected = $selected->whereIn('domain', $domains->all());
        }
        $intents = collect((array) $this->option('intent'))->map(fn ($value): string => mb_strtoupper(trim((string) $value)))->filter()->unique()->values();
        if ($intents->isNotEmpty()) {
            $selected = $selected->whereIn('intent_id', $intents->all());
        }

        return $selected->values()->all();
    }

    /** @param list<array<string,mixed>> $records @return list<array<string,mixed>> */
    private function selectedAdministrationRecords(array $records): array
    {
        $selected = collect($records)->filter(fn (array $record): bool => preg_match('/^FAM-(ACCOUNT|ACCESS|COMMS|HISTORY|COVERAGE|SUPPORT)-/', (string) $record['intent_id']) === 1);
        $batches = array_values(array_unique(array_filter(array_map('intval', (array) $this->option('batch')))));
        if ($batches !== []) {
            $selected = $selected->filter(fn (array $record): bool => (in_array(8, $batches, true) && preg_match('/^FAM-(ACCOUNT|ACCESS|COMMS|HISTORY)-/', (string) $record['intent_id']) === 1)
                || (in_array(9, $batches, true) && preg_match('/^FAM-(COVERAGE|SUPPORT)-/', (string) $record['intent_id']) === 1));
        }
        $domains = collect((array) $this->option('domain'))->map(fn ($value): string => mb_strtolower(trim((string) $value)))->filter()->unique()->values();
        if ($domains->isNotEmpty()) {
            $selected = $selected->whereIn('domain', $domains->all());
        }
        $intents = collect((array) $this->option('intent'))->map(fn ($value): string => mb_strtoupper(trim((string) $value)))->filter()->unique()->values();
        if ($intents->isNotEmpty()) {
            $selected = $selected->whereIn('intent_id', $intents->all());
        }

        return $selected->values()->all();
    }

    /** @param list<array<string,mixed>> $records @return array{passed:bool,total_phrases:int,passed_phrases:int,total_intents:int,passed_intents:int,failures:list<string>} */
    private function evaluateMarketplaceRouting(FamilyIntentResolver $resolver, array $records): array
    {
        $totalPhrases = 0;
        $passedPhrases = 0;
        $passedIntentIds = [];
        $failures = [];
        foreach ($records as $record) {
            $intentPassed = true;
            $phrases = [...(array) data_get($record, 'phrases.ordinary', []), ...(array) data_get($record, 'phrases.imperfect', [])];
            foreach ($phrases as $phrase) {
                $totalPhrases++;
                $resolution = $resolver->resolve((string) $phrase);
                if (($resolution['status'] ?? null) === FamilyIntentResolver::STATUS_RECOGNIZED
                    && ($resolution['intent_id'] ?? null) === $record['intent_id']) {
                    $passedPhrases++;
                } else {
                    $intentPassed = false;
                    $failures[] = $record['intent_id'].' phrase routed to '.($resolution['intent_id'] ?? $resolution['status'] ?? 'unmatched').'.';
                }
            }
            if ($intentPassed) {
                $passedIntentIds[] = $record['intent_id'];
            }
        }

        return [
            'passed' => $failures === [],
            'total_phrases' => $totalPhrases,
            'passed_phrases' => $passedPhrases,
            'total_intents' => count($records),
            'passed_intents' => count($passedIntentIds),
            'failures' => $failures,
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $cases
     * @param  list<array<string,string>>  $negativeCases
     * @return array{passed:bool,total_phrases:int,passed_phrases:int,total_negative_cases:int,passed_negative_cases:int,failed_intent_ids:list<string>,failures:list<string>}
     */
    private function evaluateRouting(FamilyIntentEvaluationCatalog $catalog, array $cases, array $negativeCases): array
    {
        $totalPhrases = 0;
        $passedPhrases = 0;
        $passedNegative = 0;
        $failedIntentIds = [];
        $failures = [];

        foreach ($cases as $case) {
            foreach ($case['phrases'] as $phrase) {
                $totalPhrases++;
                $actual = $catalog->classify($phrase);
                if ($actual === $case['handler']) {
                    $passedPhrases++;
                } else {
                    $failedIntentIds[] = $case['intent_id'];
                    $failures[] = $case['intent_id'].' routed to '.($actual ?? 'no deep runtime handler').' instead of '.$case['handler'].'.';
                }
            }
        }

        foreach ($negativeCases as $case) {
            $actual = $catalog->classify($case['message']);
            if ($actual === null) {
                $passedNegative++;
            } else {
                $failures[] = $case['id'].' collided with '.$actual.'.';
            }
        }

        return [
            'passed' => $failures === [],
            'total_phrases' => $totalPhrases,
            'passed_phrases' => $passedPhrases,
            'total_negative_cases' => count($negativeCases),
            'passed_negative_cases' => $passedNegative,
            'failed_intent_ids' => array_values(array_unique($failedIntentIds)),
            'failures' => $failures,
        ];
    }

    /** @return array{passed:bool,exit_code:int,duration_ms:int,suites:list<string>,error:?string} */
    private function runIsolatedApplicationTests(): array
    {
        $suites = [
            'tests/Feature/AiSupport/FamilyIntentCoverageTest.php',
            'tests/Feature/AiSupport/GuidedPaymentMethodTest.php',
            'tests/Feature/AiSupport/FamilyGuidedAssistanceTest.php',
            'tests/Feature/AiSupport/FamilyGuidedAssistanceStateMatrixTest.php',
            'tests/Feature/AiSupport/Batch3FamilyOperatingLayerTest.php',
            'tests/Feature/AiSupport/PaymentTimeKnowledgeContentTest.php',
            'tests/Feature/AiSupport/InteractiveSupportRuntimeTest.php',
            'tests/Feature/AiSupport/ProfileRequestKnowledgeContentTest.php',
            'tests/Feature/AiSupport/Batch5FamilyLifecycleTest.php',
            'tests/Feature/AiSupport/MarketplaceCareKnowledgeContentTest.php',
            'tests/Feature/AiSupport/Batch67FamilyCareOperationsTest.php',
            'tests/Feature/AiSupport/FamilyAdministrationKnowledgeContentTest.php',
            'tests/Feature/AiSupport/Batch89FamilyAdministrationTest.php',
        ];
        if (! class_exists('PHPUnit\\Framework\\TestCase')) {
            $error = 'The full Family intent regression requires Composer development dependencies. Run it in the development/CI workspace; use --plan on a no-dev production install.';
            $this->error($error);

            return [
                'passed' => false,
                'exit_code' => 1,
                'duration_ms' => 0,
                'suites' => $suites,
                'error' => $error,
            ];
        }

        // Laravel treats Windows drive-letter cache paths as relative. A unique path
        // below the existing bootstrap/cache directory works consistently on every OS.
        $cachePrefix = 'bootstrap/cache/lolo-family-eval-'.Str::uuid();
        $process = new Process(
            [PHP_BINARY, base_path('artisan'), 'test', '--colors=never', ...$suites],
            base_path(),
            [
                'APP_ENV' => 'testing',
                'APP_CONFIG_CACHE' => $cachePrefix.'-config.php',
                'APP_ROUTES_CACHE' => $cachePrefix.'-routes.php',
                'APP_EVENTS_CACHE' => $cachePrefix.'-events.php',
                'APP_SERVICES_CACHE' => $cachePrefix.'-services.php',
                'APP_PACKAGES_CACHE' => $cachePrefix.'-packages.php',
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => ':memory:',
                'CACHE_STORE' => 'array',
                'SESSION_DRIVER' => 'array',
                'QUEUE_CONNECTION' => 'sync',
                'MAIL_MAILER' => 'array',
                'OPENAI_API_KEY' => 'disabled-family-intent-evaluation',
                'STRIPE_BYPASS' => 'true',
                'PULSE_ENABLED' => 'false',
                'TELESCOPE_ENABLED' => 'false',
                'NIGHTWATCH_ENABLED' => 'false',
            ],
        );
        $process->setTimeout(420);
        $started = hrtime(true);
        $error = null;
        try {
            $exitCode = $process->run(function (string $type, string $buffer): void {
                $this->output->write($buffer);
            });
        } catch (Throwable $exception) {
            $exitCode = 1;
            $error = $exception->getMessage();
            $this->error('The isolated application regression could not finish: '.$error);
        } finally {
            foreach (['-config.php', '-routes.php', '-events.php', '-services.php', '-packages.php'] as $suffix) {
                $path = base_path($cachePrefix.$suffix);
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }

        return [
            'passed' => $exitCode === 0,
            'exit_code' => $exitCode,
            'duration_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
            'suites' => $suites,
            'error' => $error,
        ];
    }

    /**
     * @param  array<string,mixed>  $manifest
     * @param  list<array<string,mixed>>  $cases
     * @param  array<string,mixed>  $routing
     * @param  array<string,mixed>  $runtime
     * @return array<string,mixed>
     */
    private function report(array $manifest, array $cases, array $routing, array $runtime, bool $passed): array
    {
        $failedIntentIds = $routing['failed_intent_ids'];
        $results = array_map(static function (array $case) use ($failedIntentIds, $runtime): array {
            $routingPassed = ! in_array($case['intent_id'], $failedIntentIds, true);
            $status = $routingPassed && $runtime['passed'] ? 'passed' : 'failed';

            return [
                'intent_id' => $case['intent_id'],
                'batch' => $case['batch'],
                'domain' => $case['domain'],
                'handler' => $case['handler'],
                'phrase_count' => count($case['phrases']),
                'routing' => $routingPassed ? 'passed' : 'failed',
                'shared_runtime_regression' => $runtime['passed'] ? 'passed' : 'failed',
                'runtime_evidence' => $case['runtime_evidence'],
                'status' => $status,
            ];
        }, $cases);
        $passedIntents = count(array_filter($results, static fn (array $result): bool => $result['status'] === 'passed'));

        return [
            'schema_version' => 'family-intent-evaluation-report-v1',
            'generated_at' => now()->toIso8601String(),
            'corpus_version' => $manifest['version'],
            'corpus_frozen_on' => $manifest['frozen_on'],
            'execution' => [
                'provider_calls' => 0,
                'production_database_writes' => 0,
                'database' => 'sqlite-memory',
                'runtime_duration_ms' => $runtime['duration_ms'],
                'runtime_exit_code' => $runtime['exit_code'],
                'runtime_error' => $runtime['error'],
                'suites' => $runtime['suites'],
            ],
            'summary' => [
                'selected_intents' => count($results),
                'passed_intents' => $passedIntents,
                'failed_intents' => count($results) - $passedIntents,
                'routing_phrases' => $routing['total_phrases'],
                'passed_routing_phrases' => $routing['passed_phrases'],
                'collision_cases' => $routing['total_negative_cases'],
                'passed_collision_cases' => $routing['passed_negative_cases'],
                'shared_runtime_regression' => $runtime['passed'] ? 'passed' : 'failed',
                'overall' => $passed ? 'passed' : 'failed',
            ],
            'intent_results' => $results,
        ];
    }
}
