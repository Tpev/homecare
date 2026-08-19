<?php

namespace Tests\Feature\Deployment;

use Tests\TestCase;

class AtomicDeploymentScriptTest extends TestCase
{
    public function test_deployment_never_enables_laravel_maintenance_mode(): void
    {
        $script = $this->script();

        $this->assertStringNotContainsString('artisan down', $script);
        $this->assertStringNotContainsString('systemctl restart "$FPM_SERVICE"', $script);
        $this->assertStringContainsString('systemctl reload "$FPM_SERVICE"', $script);
        $this->assertStringContainsString('No Laravel maintenance mode or deployment-long 503 was used.', $script);
    }

    public function test_release_is_prepared_and_validated_before_the_atomic_switch(): void
    {
        $script = $this->script();
        $execution = substr($script, (int) strpos($script, 'CURRENT_RELEASE="$(current_release)"'));

        $prepare = strpos($execution, 'create_release "$CURRENT_RELEASE"');
        $activate = strpos($execution, 'activate_release "$CURRENT_RELEASE"');
        $health = strpos($execution, 'wait_for_health app_is_healthy "Application"');

        $this->assertIsInt($prepare);
        $this->assertIsInt($activate);
        $this->assertIsInt($health);
        $this->assertLessThan($activate, $prepare);
        $this->assertLessThan($health, $activate);
        $this->assertStringContainsString('atomic_symlink "$NEW_RELEASE" "$APP_DIR"', $script);
        $this->assertStringContainsString('atomic_exchange "$APP_DIR" "$LEGACY_RELEASE"', $script);
    }

    public function test_durable_state_and_in_flight_assets_survive_release_switches(): void
    {
        $script = $this->script();

        $this->assertStringContainsString('ln -s "$SHARED_STORAGE" "$NEW_RELEASE/storage"', $script);
        $this->assertStringContainsString('ln -s "$SHARED_ENV" "$NEW_RELEASE/.env"', $script);
        $this->assertStringContainsString('cp -a -n "$current/public/build/assets/."', $script);
        $this->assertStringContainsString('Skipping $release because its storage directory is not a symlink.', $script);
    }

    public function test_failed_activation_rolls_back_code_without_reversing_migrations(): void
    {
        $script = $this->script();

        $this->assertStringContainsString('Rolling the application symlink back to $OLD_RELEASE', $script);
        $this->assertStringContainsString('atomic_symlink "$OLD_RELEASE" "$APP_DIR"', $script);
        $this->assertStringContainsString('run_artisan "$NEW_RELEASE" migrate --force', $script);
        $this->assertStringContainsString('Database migrations were not reversed.', $script);
        $this->assertStringContainsString('./deploy.sh --rollback', $script);
    }

    private function script(): string
    {
        $script = file_get_contents(base_path('deploy.sh'));

        $this->assertIsString($script);

        return $script;
    }
}
