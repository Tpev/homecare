<?php

namespace Tests\Unit\Queue;

use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Support\Carbon;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class DatabaseQueueReservationTest extends TestCase
{
    private const WORKER_TIMEOUT = 120;

    private array $environment = [];

    private mixed $previousTestNow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousTestNow = Carbon::getTestNow();
        foreach (['DB_QUEUE_RETRY_AFTER', 'QUEUE_CONNECTION'] as $name) {
            $this->environment[$name] = [
                'env' => $_ENV[$name] ?? null,
                'server' => $_SERVER[$name] ?? null,
                'process' => getenv($name),
            ];
            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow($this->previousTestNow);
        foreach ($this->environment as $name => $values) {
            unset($_ENV[$name], $_SERVER[$name]);
            if ($values['env'] !== null) {
                $_ENV[$name] = $values['env'];
            }
            if ($values['server'] !== null) {
                $_SERVER[$name] = $values['server'];
            }
            putenv($values['process'] === false ? $name : $name.'='.$values['process']);
        }

        parent::tearDown();
    }

    public function test_a_reserved_job_cannot_be_claimed_again_during_the_worker_timeout(): void
    {
        $config = $this->queueConfig();
        $this->assertSame('database', $config['default']);
        $retryAfter = $config['connections']['database']['retry_after'];
        $this->assertGreaterThan(self::WORKER_TIMEOUT, $retryAfter);

        // This connection is isolated from Laravel's configured application database.
        $database = new SQLiteConnection(new PDO('sqlite::memory:'));
        $database->statement('CREATE TABLE jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            queue VARCHAR(255) NOT NULL,
            payload TEXT NOT NULL,
            attempts INTEGER NOT NULL,
            reserved_at INTEGER NULL,
            available_at INTEGER NOT NULL,
            created_at INTEGER NOT NULL
        )');
        $queue = new DatabaseQueue($database, 'jobs', 'default', $retryAfter);
        $queue->setContainer(new Container);
        $queue->setConnectionName('database');

        $startedAt = Carbon::parse('2026-10-13 12:00:00', 'UTC');
        Carbon::setTestNow($startedAt);
        $jobId = $queue->push('reservation-test-job');
        $firstAttempt = $queue->pop();
        $this->assertNotNull($firstAttempt);
        $this->assertSame($jobId, $firstAttempt->getJobId());

        foreach ([90, self::WORKER_TIMEOUT, $retryAfter - 1] as $elapsed) {
            Carbon::setTestNow($startedAt->copy()->addSeconds($elapsed));
            $this->assertNull($queue->pop(), "A reserved job became available again after {$elapsed} seconds.");
        }

        Carbon::setTestNow($startedAt->copy()->addSeconds($retryAfter));
        $retriedJob = $queue->pop();
        $this->assertNotNull($retriedJob);
        $this->assertSame($jobId, $retriedJob->getJobId());
        $this->assertSame(2, $retriedJob->attempts());
    }

    public function test_application_job_timeouts_fit_within_the_database_reservation(): void
    {
        $retryAfter = $this->queueConfig()['connections']['database']['retry_after'];
        $jobs = glob(dirname(__DIR__, 3).'/app/Jobs/*.php');
        $this->assertNotEmpty($jobs);

        foreach ($jobs as $path) {
            $job = new ReflectionClass('App\\Jobs\\'.basename($path, '.php'));
            if (! $job->implementsInterface(ShouldQueue::class)) {
                continue;
            }

            $timeout = $job->getDefaultProperties()['timeout'] ?? self::WORKER_TIMEOUT;
            $this->assertGreaterThan(0, $timeout, $job->getName().' must have a bounded timeout.');
            $this->assertGreaterThan($timeout, $retryAfter, $job->getName().' can outlive its queue reservation.');
        }
    }

    public function test_explicit_longer_database_reservation_is_honored(): void
    {
        $_ENV['DB_QUEUE_RETRY_AFTER'] = '240';
        $_SERVER['DB_QUEUE_RETRY_AFTER'] = '240';
        putenv('DB_QUEUE_RETRY_AFTER=240');

        $this->assertSame(240, $this->queueConfig()['connections']['database']['retry_after']);
    }

    private function queueConfig(): array
    {
        return require dirname(__DIR__, 3).'/config/queue.php';
    }
}
