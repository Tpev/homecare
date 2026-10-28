import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

export const stagingRoot = path.resolve(process.cwd(), 'tmp', 'care-hours-launch-validation');
export const stagingUrl = 'http://127.0.0.1:8015';
export const stagingDatabase = path.join(stagingRoot, 'care-hours-launch-validation.sqlite');

type ProviderSettings = {
    payment_mode: 'stripe_test' | 'bypass';
    stripe_secret?: string;
    connect_account_id?: string;
    stripe_webhook_secret?: string;
    stripe_publishable_key?: string;
};

export function stagingEnvironment(): Record<string, string> {
    const providerFile = path.join(stagingRoot, 'provider.json');
    if (!fs.existsSync(providerFile)) throw new Error('Prepare the isolated staging provider.json before running this journey.');
    const provider = JSON.parse(fs.readFileSync(providerFile, 'utf8')) as ProviderSettings;
    const secret = provider.stripe_secret ?? '';
    if (!['stripe_test', 'bypass'].includes(provider.payment_mode)) throw new Error('An explicit staging payment mode is required.');
    if (secret.startsWith('sk_live_') || secret.startsWith('rk_live_')) throw new Error('Live Stripe credentials are prohibited in this staging journey.');
    if (provider.payment_mode === 'stripe_test' && !/^(sk|rk|rkcs)_test_/.test(secret)) throw new Error('Stripe sandbox credentials are required.');
    if (provider.payment_mode === 'stripe_test' && !/^acct_/.test(provider.connect_account_id ?? '')) throw new Error('A Stripe test connected account is required.');
    if ((provider.stripe_publishable_key ?? '').startsWith('pk_live_')) throw new Error('Live publishable keys are prohibited.');

    return {
        APP_ENV: 'playwright', APP_NAME: 'LoLo', APP_URL: stagingUrl, APP_DEBUG: 'false', APP_TIMEZONE: 'America/New_York',
        APP_KEY: `base64:${crypto.createHash('sha256').update('care-hours-launch-validation-only:' + stagingRoot).digest('base64')}`,
        // Laravel resolves these relative to base_path; a Windows drive path is treated as relative.
        APP_CONFIG_CACHE: 'tmp/care-hours-launch-validation/config.php',
        APP_SERVICES_CACHE: 'tmp/care-hours-launch-validation/services.php',
        APP_PACKAGES_CACHE: 'tmp/care-hours-launch-validation/packages.php',
        APP_ROUTES_CACHE: 'tmp/care-hours-launch-validation/routes.php',
        LARAVEL_STORAGE_PATH: path.join(stagingRoot, 'storage'),
        VIEW_COMPILED_PATH: path.join(stagingRoot, 'storage', 'framework', 'views'),
        DB_CONNECTION: 'sqlite', DB_DATABASE: stagingDatabase, DB_URL: '',
        CACHE_STORE: 'array', SESSION_DRIVER: 'file', SESSION_COOKIE: 'care_hours_staging_session',
        SESSION_DOMAIN: '', SESSION_SECURE_COOKIE: 'false', SESSION_LIFETIME: '10080',
        QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array', LOG_CHANNEL: 'single',
        TWILIO_BYPASS: 'true', DIDIT_BYPASS: 'true',
        STRIPE_BYPASS: provider.payment_mode === 'bypass' ? 'true' : 'false',
        STRIPE_SECRET: provider.payment_mode === 'stripe_test' ? secret : '',
        STRIPE_KEY: provider.stripe_publishable_key ?? '',
        STRIPE_WEBHOOK_SECRET: provider.stripe_webhook_secret ?? '',
        CARE_HOURS_LAUNCH_VALIDATION_PAYMENT_MODE: provider.payment_mode,
        CARE_HOURS_LAUNCH_VALIDATION_CONNECT_ACCOUNT: provider.connect_account_id ?? 'acct_staging_simulated',
        CARE_HOURS_LAUNCH_VALIDATION_MARKER: 'isolated-launch-validation',
        CARE_HOURS_ENABLED: 'true', CARE_HOURS_ENROLLMENT_ENABLED: 'true',
        CARE_HOURS_PILOT_ONLY: 'false',
        CARE_HOURS_SALES_ENABLED: 'true', CARE_HOURS_CONTRIBUTIONS_ENABLED: 'true', CARE_HOURS_WELCOME_BONUS_ENABLED: 'true',
        CARE_HOURS_GIFTS_ENABLED: 'true',
        MARKETPLACE_PRICING_V2_ENABLED: 'true', MARKETPLACE_FAMILY_CARE_HOURLY_CENTS: '3000',
        MARKETPLACE_FAMILY_PROCESSING_FEE_HOURLY_CENTS: '100', MARKETPLACE_CAREGIVER_GROSS_HOURLY_CENTS: '2700',
        MARKETPLACE_FAMILY_PRELAUNCH_AUTO_APPLICANTS_ENABLED: 'false', MARKETPLACE_CAREGIVER_PRELAUNCH_MODE: 'false',
        AI_SUPPORT_SAFETY_IDENTIFIER_SECRET: 'isolated-care-hours-launch-validation-only',
    };
}
