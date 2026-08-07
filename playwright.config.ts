import path from 'node:path';
import { defineConfig } from '@playwright/test';

const sqlitePath = path.join(process.cwd(), 'database', 'playwright.sqlite');

const laravelEnv = {
    APP_ENV: 'playwright',
    APP_URL: 'http://127.0.0.1:8010',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: sqlitePath,
    CACHE_STORE: 'file',
    SESSION_DRIVER: 'file',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'array',
    DIDIT_BYPASS: 'true',
    STRIPE_BYPASS: 'true',
    MARKETPLACE_TIME_CORRECTIONS_ENABLED: 'true',
};

export default defineConfig({
    testDir: './tests/e2e/specs',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 90_000,
    expect: {
        timeout: 10_000,
    },
    reporter: [['list'], ['html', { outputFolder: 'playwright-report', open: 'never' }]],
    use: {
        baseURL: 'http://127.0.0.1:8010',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    webServer: {
        command: 'php -r "if (!is_dir(\'database\')) { mkdir(\'database\', 0777, true); } if (!file_exists(\'database/playwright.sqlite\')) { touch(\'database/playwright.sqlite\'); }" && php artisan migrate:fresh --force && php artisan homecare:e2e-seed && php artisan homecare:verify-family-accounts && php artisan serve --host=127.0.0.1 --port=8010',
        url: 'http://127.0.0.1:8010',
        reuseExistingServer: false,
        timeout: 180_000,
        env: laravelEnv,
    },
});
