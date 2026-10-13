<?php

namespace Tests\Feature\Screening;

use App\Models\ScreeningDatasetImport;
use App\Services\Screening\Imports\ScreeningDatasetImporter;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule as ScheduleFacade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScreeningScheduleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'app.timezone' => 'America/New_York']);
        Http::preventStrayRequests();
    }

    public static function refreshSettings(): array
    {
        return [
            'omitted defaults to disabled' => [null, false],
            'explicitly disabled' => ['false', false],
            'explicitly enabled' => ['true', true],
        ];
    }

    #[DataProvider('refreshSettings')]
    public function test_automatic_refresh_requires_opt_in_and_keeps_other_screening_schedules(?string $setting, bool $enabled): void
    {
        $configuration = $this->screeningConfiguration($setting);
        $this->assertSame($enabled, $configuration['auto_refresh_enabled']);
        config(['screening' => $configuration]);

        $events = $this->screeningEvents();
        $this->assertSame($enabled, isset($events['screening:refresh-datasets']));
        if ($enabled) {
            $this->assertSame('0 3 * * *', $events['screening:refresh-datasets']->expression);
        }
        $this->assertSame('0 5 * * *', $events['screening:sync']->expression);
        $this->assertSame('* * * * *', $events['screening:export-audit']->expression);
        foreach ($events as $event) {
            $this->assertSame('America/New_York', $event->timezone);
            $this->assertTrue($event->withoutOverlapping);
        }
    }

    public function test_missing_cached_setting_also_leaves_automatic_refresh_disabled(): void
    {
        $configuration = config('screening');
        unset($configuration['auto_refresh_enabled']);
        config(['screening' => $configuration]);

        $this->assertSame(['screening:sync', 'screening:export-audit'], array_keys($this->screeningEvents()));
    }

    public function test_manual_import_and_refresh_still_run_when_automatic_refresh_is_disabled(): void
    {
        config(['screening.auto_refresh_enabled' => false, 'screening.required_sources' => ['nc_dac', 'nc_sbi']]);
        $importer = $this->mock(ScreeningDatasetImporter::class);
        $importer->shouldReceive('import')->with('nc_dac', null, null)->twice()
            ->andReturn(new ScreeningDatasetImport(['id' => 1, 'source' => 'nc_dac', 'record_count' => 100]));
        $importer->shouldReceive('import')->with('nc_sbi', null, null)->once()
            ->andReturn(new ScreeningDatasetImport(['id' => 2, 'source' => 'nc_sbi', 'record_count' => 10]));

        $this->artisan('screening:import', ['source' => 'nc_dac'])->assertSuccessful();
        $this->artisan('screening:refresh-datasets')->assertSuccessful();
        Http::assertNothingSent();
    }

    private function screeningConfiguration(?string $setting): array
    {
        $key = 'SCREENING_AUTO_REFRESH_ENABLED';
        $originalEnv = $_ENV[$key] ?? null;
        $originalServer = $_SERVER[$key] ?? null;
        $originalProcess = getenv($key);
        try {
            if ($setting === null) {
                unset($_ENV[$key], $_SERVER[$key]);
                putenv($key);
            } else {
                $_ENV[$key] = $_SERVER[$key] = $setting;
                putenv($key.'='.$setting);
            }

            return require config_path('screening.php');
        } finally {
            unset($_ENV[$key], $_SERVER[$key]);
            if ($originalEnv !== null) {
                $_ENV[$key] = $originalEnv;
            }
            if ($originalServer !== null) {
                $_SERVER[$key] = $originalServer;
            }
            putenv($originalProcess === false ? $key : $key.'='.$originalProcess);
        }
    }

    private function screeningEvents(): array
    {
        $original = ScheduleFacade::getFacadeRoot();
        $schedule = new Schedule(config('app.timezone'));
        ScheduleFacade::swap($schedule);
        try {
            require base_path('routes/console.php');
        } finally {
            ScheduleFacade::swap($original);
        }

        $events = [];
        foreach ($schedule->events() as $event) {
            if (preg_match('/\bscreening:[a-z-]+\b/', $event->command ?? '', $matches)) {
                $this->assertArrayNotHasKey($matches[0], $events, 'Screening commands must not be registered twice.');
                $events[$matches[0]] = $event;
            }
        }

        return $events;
    }
}
