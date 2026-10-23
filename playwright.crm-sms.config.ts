import path from 'node:path';
import { defineConfig, devices } from '@playwright/test';

const root = process.cwd();
const baseURL = 'http://127.0.0.1:8033';

export default defineConfig({
    testDir: './tests/e2e/specs',
    testMatch: 'crm-sms.spec.ts',
    workers: 1,
    retries: 0,
    timeout: 180_000,
    expect: { timeout: 20_000 },
    reporter: [['list']],
    outputDir: 'test-results/crm-sms',
    use: { baseURL, actionTimeout: 20_000, screenshot: 'only-on-failure', trace: 'retain-on-failure' },
    projects: [
        { name: 'desktop', use: { ...devices['Desktop Chrome'], channel: 'msedge', viewport: { width: 1440, height: 1000 } } },
        { name: 'mobile', use: { ...devices['Pixel 7'], channel: 'msedge' } },
    ],
    webServer: {
        command: 'php tests/e2e/crm-sms-fixtures.php serve',
        url: `${baseURL}/login`,
        reuseExistingServer: false,
        timeout: 180_000,
        env: {
            APP_ENV: 'testing', APP_URL: baseURL, ASSET_URL: baseURL, APP_DEBUG: 'true',
            APP_KEY: 'base64:Y3VzdG9tZXItb25ib2FyZGluZy1xYS1rZXktMDAwMDA=',
            APP_CONFIG_CACHE: path.join(root, 'storage', 'app', 'crm-sms-qa-config.php'),
            DB_CONNECTION: 'sqlite', DB_DATABASE: path.join(root, 'storage', 'app', 'crm-sms-qa.sqlite'), DB_URL: '',
            CACHE_STORE: 'array', SESSION_DRIVER: 'database', SESSION_COOKIE: 'crm_sms_qa_session',
            SESSION_SECURE_COOKIE: 'false', SESSION_DOMAIN: '127.0.0.1',
            QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array', BROADCAST_CONNECTION: 'null', FILESYSTEM_DISK: 'local',
            STRIPE_BYPASS: 'true', STRIPE_SECRET: '', STRIPE_KEY: '', DIDIT_BYPASS: 'true',
            TWILIO_BYPASS: 'true', TWILIO_SMS_FROM: '+19195550000', TWILIO_SMS_STATUS_CALLBACK_URL: '', TWILIO_WEBHOOK_BASE_URL: '',
            SLACK_OPS_NOTIFICATIONS_ENABLED: 'false', SLACK_OPS_WEBHOOK_URL: '', AI_SUPPORT_PROVIDER_ENABLED: 'false',
            AI_SUPPORT_SAFETY_IDENTIFIER_SECRET: 'crm-sms-browser-test-only',
            PULSE_ENABLED: 'false', TELESCOPE_ENABLED: 'false', NIGHTWATCH_ENABLED: 'false',
        },
    },
});
