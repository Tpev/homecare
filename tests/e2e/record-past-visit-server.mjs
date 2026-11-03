import fs from 'node:fs';
import path from 'node:path';
import { spawn, spawnSync } from 'node:child_process';

const root = process.cwd();
const output = path.join(root, 'tmp', 'record-past-visit-e2e');
fs.mkdirSync(path.join(output, 'views'), { recursive: true });
const database = path.join(output, 'test.sqlite');
fs.closeSync(fs.openSync(database, 'a'));
const php = process.env.PHP_BIN || (process.platform === 'win32' ? 'C:/php/php.exe' : 'php');
const env = {
    ...process.env,
    APP_ENV: 'playwright', APP_URL: 'http://127.0.0.1:8037', APP_DEBUG: 'true',
    DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '',
    APP_CONFIG_CACHE: path.join(output, 'config.php'), APP_ROUTES_CACHE: path.join(output, 'routes.php'),
    VIEW_COMPILED_PATH: path.join(output, 'views'),
    SESSION_DRIVER: 'database', SESSION_CONNECTION: 'sqlite', SESSION_SECURE_COOKIE: 'false',
    CACHE_STORE: 'database', DB_CACHE_CONNECTION: 'sqlite', QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array',
    STRIPE_SECRET: '', STRIPE_KEY: '', STRIPE_BYPASS: 'true', STRIPE_CURRENCY: 'usd',
    STRIPE_BYPASS_PROCESSING_FEE_PERCENT: '0', STRIPE_BYPASS_PROCESSING_FEE_FIXED_CENTS: '0',
    DIDIT_BYPASS: 'true', TWILIO_BYPASS: 'true',
    MARKETPLACE_FAMILY_CARE_HOURLY_CENTS: '3000', MARKETPLACE_FAMILY_PROCESSING_FEE_HOURLY_CENTS: '100',
    MARKETPLACE_CAREGIVER_GROSS_HOURLY_CENTS: '2700',
};
// Destructive migration is confined to this explicitly named, disposable SQLite database.
for (const args of [['artisan', 'migrate:fresh', '--force', '--no-interaction'], ['tests/e2e/record-past-visit-fixtures.php']]) {
    const result = spawnSync(php, args, { cwd: root, env, stdio: 'inherit' });
    if (result.status !== 0) process.exit(result.status || 1);
}
const server = spawn(php, ['artisan', 'serve', '--host=127.0.0.1', '--port=8037', '--no-reload'], { cwd: root, env, stdio: 'inherit' });
for (const signal of ['SIGINT', 'SIGTERM']) process.on(signal, () => server.kill(signal));
server.on('exit', (code) => process.exit(code || 0));
