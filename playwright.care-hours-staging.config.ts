import path from 'node:path';
import { defineConfig } from '@playwright/test';
import { stagingEnvironment, stagingRoot, stagingUrl } from './tests/e2e/care-hours-staging-environment';

const resumeAfterPurchase = process.env.CARE_HOURS_STAGING_RESUME === 'after-purchase';
if (process.env.CARE_HOURS_STAGING_RESUME && !resumeAfterPurchase) throw new Error('Unsupported staging resume checkpoint.');

export default defineConfig({
    testDir: './tests/e2e/specs', testMatch: 'care-hours-staging.spec.ts',
    fullyParallel: false, workers: 1, retries: 0, timeout: 300_000,
    expect: { timeout: 20_000 }, reporter: [['list']],
    outputDir: path.join(stagingRoot, 'test-results'),
    use: {
        baseURL: stagingUrl, viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 2,
        screenshot: 'only-on-failure', trace: 'retain-on-failure',
        permissions: ['geolocation'], geolocation: { latitude: 35.7796, longitude: -78.6382 },
        timezoneId: 'America/New_York',
    },
    projects: [{ name: 'chromium', use: { browserName: 'chromium' } }],
    webServer: {
        command: resumeAfterPurchase
            ? 'php -S 127.0.0.1:8014 -t public tests/e2e/care-hours-staging-router.php'
            : 'php tests/e2e/care-hours-staging-fixtures.php prepare && php tests/e2e/care-hours-staging-fixtures.php migrate && php tests/e2e/care-hours-staging-fixtures.php seed && php -S 127.0.0.1:8014 -t public tests/e2e/care-hours-staging-router.php',
        url: stagingUrl, reuseExistingServer: false, timeout: 180_000,
        env: stagingEnvironment(),
    },
});
