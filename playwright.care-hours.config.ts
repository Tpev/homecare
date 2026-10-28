import path from 'node:path';
import { defineConfig, devices } from '@playwright/test';

const database = path.join(process.cwd(), 'database', 'care-hours-e2e.sqlite');

export default defineConfig({
    testDir: './tests/e2e/specs',
    testMatch: 'care-hours.spec.ts',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 90_000,
    expect: { timeout: 15_000 },
    reporter: [['list']],
    outputDir: 'test-results/care-hours',
    use: {
        baseURL: 'http://127.0.0.1:8012',
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
    webServer: {
        command: 'php tests/e2e/care-hours-fixtures.php prepare && php artisan migrate:fresh --force && php tests/e2e/care-hours-fixtures.php seed && php artisan serve --host=127.0.0.1 --port=8012',
        url: 'http://127.0.0.1:8012',
        reuseExistingServer: false,
        timeout: 180_000,
        env: {
            APP_ENV: 'playwright', APP_URL: 'http://127.0.0.1:8012',
            APP_CONFIG_CACHE: path.join(process.cwd(), 'bootstrap', 'cache', 'care-hours-e2e-config.php'),
            DB_CONNECTION: 'sqlite', DB_DATABASE: database,
            CACHE_STORE: 'array', SESSION_DRIVER: 'file', QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array',
            DIDIT_BYPASS: 'true', STRIPE_BYPASS: 'true', STRIPE_SECRET: '',
            CARE_HOURS_ENABLED: 'true', CARE_HOURS_ENROLLMENT_ENABLED: 'true',
            CARE_HOURS_PILOT_ONLY: 'false',
            CARE_HOURS_SALES_ENABLED: 'true', CARE_HOURS_CONTRIBUTIONS_ENABLED: 'true', CARE_HOURS_WELCOME_BONUS_ENABLED: 'true',
            AI_SUPPORT_SAFETY_IDENTIFIER_SECRET: 'care-hours-browser-test-only',
        },
    },
});
