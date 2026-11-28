import path from 'node:path';
import { defineConfig, devices } from '@playwright/test';

const root = process.cwd();
const baseURL = 'http://127.0.0.1:8041';
export default defineConfig({
    testDir: './tests/e2e/specs', testMatch: 'care-roster.spec.ts', workers: 1, retries: 0,
    timeout: 90_000, expect: { timeout: 15_000 }, reporter: [['list']],
    outputDir: 'test-results/care-roster',
    use: { baseURL, screenshot: 'only-on-failure', trace: 'retain-on-failure' },
    projects: [
        { name: 'desktop', use: { ...devices['Desktop Chrome'], channel: 'msedge', viewport: { width: 1440, height: 1000 } } },
        { name: 'mobile', use: { ...devices['Pixel 7'], channel: 'msedge' } },
    ],
    webServer: {
        command: 'php tests/e2e/care-roster-fixtures.php serve', url: `${baseURL}/login`, reuseExistingServer: false, timeout: 180_000,
        env: {
            APP_ENV: 'testing', APP_URL: baseURL, ASSET_URL: baseURL, APP_DEBUG: 'true',
            APP_KEY: 'base64:Y3VzdG9tZXItb25ib2FyZGluZy1xYS1rZXktMDAwMDA=',
            APP_CONFIG_CACHE: path.join(root, 'storage/app/care-roster-qa-config.php'),
            DB_CONNECTION: 'sqlite', DB_DATABASE: path.join(root, 'storage/app/care-roster-qa.sqlite'), DB_URL: '',
            CACHE_STORE: 'array', SESSION_DRIVER: 'database', SESSION_COOKIE: 'care_roster_qa',
            SESSION_SECURE_COOKIE: 'false', SESSION_DOMAIN: '127.0.0.1',
            QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array', BROADCAST_CONNECTION: 'null',
            STRIPE_BYPASS: 'true', STRIPE_SECRET: '', STRIPE_KEY: '', STRIPE_WEBHOOK_SECRET: '',
            DIDIT_BYPASS: 'true', TWILIO_BYPASS: 'true', SLACK_OPS_WEBHOOK_URL: '',
            AI_SUPPORT_PROVIDER_ENABLED: 'false', AI_SUPPORT_RUNTIME_AVAILABLE: 'false',
            OPENAI_API_KEY: 'synthetic-browser-test', OPENAI_BASE_URL: 'https://api.openai.test/v1',
        },
    },
});
