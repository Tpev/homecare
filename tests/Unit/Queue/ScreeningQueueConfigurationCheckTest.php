<?php

namespace Tests\Unit\Queue;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ScreeningQueueConfigurationCheckTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = tempnam(sys_get_temp_dir(), 'homecare-queue-check-');
    }

    protected function tearDown(): void
    {
        if (is_file($this->fixture)) {
            unlink($this->fixture);
        }
        parent::tearDown();
    }

    public function test_it_accepts_a_safe_cached_configuration_without_printing_other_configuration(): void
    {
        $configuration = $this->configuration();
        $configuration['database']['connections']['mysql']['password'] = 'do-not-print-this-secret';
        $this->writeFixture($configuration);

        $result = $this->runCheck([$this->fixture]);

        $this->assertSame(0, $result->getExitCode(), $result->getErrorOutput());
        $this->assertStringContainsString('retry_after=180 worker_timeout=120', $result->getOutput());
        $this->assertStringNotContainsString('do-not-print-this-secret', $result->getOutput().$result->getErrorOutput());
    }

    #[DataProvider('unsafeQueueSettings')]
    public function test_it_rejects_an_unsafe_or_unconsumed_queue(string $field, mixed $value): void
    {
        $configuration = $this->configuration();
        if ($field === 'default') {
            $configuration['queue']['default'] = $value;
        } else {
            $configuration['queue']['connections']['database'][$field] = $value;
        }
        $this->writeFixture($configuration);

        $result = $this->runCheck([$this->fixture, '120']);

        $this->assertSame(1, $result->getExitCode());
        $this->assertSame('', $result->getOutput());
        $this->assertStringContainsString('Screening queue check:', $result->getErrorOutput());
    }

    public static function unsafeQueueSettings(): array
    {
        return [
            'production retry below timeout' => ['retry_after', 90],
            'retry equal to timeout' => ['retry_after', 120],
            'string retry is not validated integer config' => ['retry_after', '180'],
            'missing retry' => ['retry_after', null],
            'disabled timeout reservation' => ['retry_after', 0],
            'wrong default connection' => ['default', 'redis'],
            'synchronous default' => ['default', 'sync'],
            'wrong connection driver' => ['driver', 'redis'],
            'unconsumed queue' => ['queue', 'screening'],
        ];
    }

    public function test_it_uses_the_provided_worker_timeout(): void
    {
        $this->writeFixture($this->configuration());

        $unsafe = $this->runCheck([$this->fixture, '180']);
        $safe = $this->runCheck([$this->fixture, '179']);

        $this->assertSame(1, $unsafe->getExitCode());
        $this->assertSame(0, $safe->getExitCode(), $safe->getErrorOutput());
        $this->assertStringContainsString('worker_timeout=179', $safe->getOutput());
    }

    #[DataProvider('invalidTimeouts')]
    public function test_it_rejects_invalid_worker_timeout_arguments(string $timeout): void
    {
        $this->writeFixture($this->configuration());

        $result = $this->runCheck([$this->fixture, $timeout]);

        $this->assertSame(1, $result->getExitCode());
        $this->assertStringContainsString('positive integer', $result->getErrorOutput());
    }

    public static function invalidTimeouts(): array
    {
        return array_map(fn ($value) => [$value], ['', '0', '-1', '1.5', '120s', '99999999999999999999999999999']);
    }

    public function test_it_rejects_missing_or_extra_arguments(): void
    {
        foreach ([[], [$this->fixture, '120', 'unexpected']] as $arguments) {
            $result = $this->runCheck($arguments);
            $this->assertSame(1, $result->getExitCode());
            $this->assertStringContainsString('Usage:', $result->getErrorOutput());
        }
    }

    public function test_it_rejects_an_absent_cache_instead_of_loading_the_application(): void
    {
        unlink($this->fixture);

        $result = $this->runCheck([$this->fixture]);

        $this->assertSame(1, $result->getExitCode());
        $this->assertStringContainsString('readable cached configuration', $result->getErrorOutput());
    }

    public function test_it_rejects_invalid_cache_contents_without_disclosing_the_error_details(): void
    {
        file_put_contents($this->fixture, '<?php echo "do-not-print-this-secret"; throw new RuntimeException("do-not-print-this-secret");');

        $result = $this->runCheck([$this->fixture]);

        $this->assertSame(1, $result->getExitCode());
        $this->assertStringContainsString('could not be read', $result->getErrorOutput());
        $this->assertStringNotContainsString('do-not-print-this-secret', $result->getOutput().$result->getErrorOutput());
    }

    public function test_it_rejects_a_cache_without_queue_settings(): void
    {
        $this->writeFixture([]);

        $result = $this->runCheck([$this->fixture]);

        $this->assertSame(1, $result->getExitCode());
        $this->assertStringContainsString('no valid queue configuration', $result->getErrorOutput());
    }

    private function configuration(): array
    {
        return ['queue' => [
            'default' => 'database',
            'connections' => ['database' => [
                'driver' => 'database',
                'queue' => 'default',
                'retry_after' => 180,
            ]],
        ]];
    }

    private function writeFixture(array $configuration): void
    {
        file_put_contents($this->fixture, '<?php return '.var_export($configuration, true).';');
    }

    private function runCheck(array $arguments): Process
    {
        $result = new Process([
            PHP_BINARY,
            dirname(__DIR__, 3).'/scripts/deployment/check-screening-queue.php',
            ...$arguments,
        ]);
        $result->setTimeout(10);
        $result->run();

        return $result;
    }
}
