import path from 'node:path';
import { defineConfig } from '@playwright/test';
import { stagingEnvironment, stagingRoot, stagingUrl } from './tests/e2e/care-hours-launch-validation-environment';

const reuse = process.env.CARE_HOURS_LAUNCH_VALIDATION_REUSE === '1';
export default defineConfig({
    testDir: './tests/e2e/specs', testMatch: 'care-hours-launch-validation.spec.ts',
    fullyParallel: false, workers: 1, retries: 0, timeout: 360_000,
    expect: { timeout: 20_000 }, reporter: [['list']],
    outputDir: path.join(stagingRoot, 'test-results'),
    use: { baseURL: stagingUrl, viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 2, screenshot: 'only-on-failure', trace: 'retain-on-failure', timezoneId: 'America/New_York', geolocation: { latitude: 35.7796, longitude: -78.6382 }, permissions: ['geolocation'] },
    projects: [{ name: 'chromium', use: { browserName: 'chromium' } }],
    webServer: {
        command: (reuse ? '' : 'php tests/e2e/care-hours-launch-validation-fixtures.php prepare && php tests/e2e/care-hours-launch-validation-fixtures.php migrate && php tests/e2e/care-hours-launch-validation-fixtures.php seed && ')
            + 'php -S 127.0.0.1:8015 -t public tests/e2e/care-hours-launch-validation-router.php',
        url: stagingUrl, reuseExistingServer: false, timeout: 180_000, env: stagingEnvironment(),
    },
});
