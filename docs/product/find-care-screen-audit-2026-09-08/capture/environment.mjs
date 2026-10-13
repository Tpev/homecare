import path from 'node:path';
import fs from 'node:fs';

export const root = process.cwd();
export const output = path.join(root, 'docs/product/find-care-screen-audit-2026-09-08');
export const runtime = path.join(root, '.tmp/find-care-screen-audit-2026-09-08');
export const base = 'http://127.0.0.1:8028';
export const galleryBase = 'http://127.0.0.1:8031';
for (const directory of [runtime, path.join(runtime, 'views'), path.join(runtime, 'appdata')]) fs.mkdirSync(directory, {recursive: true});
export const env = {...process.env,
  APP_ENV: 'playwright', APP_DEBUG: 'true', APP_URL: base,
  APP_KEY: 'base64:' + Buffer.alloc(32, 28).toString('base64'),
  APP_CONFIG_CACHE: path.join(runtime, 'config.php'), APP_ROUTES_CACHE: path.join(runtime, 'routes.php'),
  VIEW_COMPILED_PATH: path.join(runtime, 'views'),
  DB_CONNECTION: 'sqlite', DB_DATABASE: path.join(runtime, 'find-care-audit.sqlite'),
  CACHE_STORE: 'array', SESSION_DRIVER: 'database', SESSION_COOKIE: 'find_care_redesign_session', SESSION_LIFETIME: '720',
  QUEUE_CONNECTION: 'database', MAIL_MAILER: 'array', BROADCAST_CONNECTION: 'log',
  STRIPE_BYPASS: 'true', DIDIT_BYPASS: 'true', TWILIO_BYPASS: 'true',
  STRIPE_SECRET: '', STRIPE_KEY: '', STRIPE_WEBHOOK_SECRET: '', OPENAI_API_KEY: '',
  DIDIT_API_KEY: '', TWILIO_AUTH_TOKEN: '', SLACK_OPS_NOTIFICATIONS_ENABLED: 'false',
  AI_SUPPORT_PROVIDER_ENABLED: 'false', AI_SUPPORT_RUNTIME_AVAILABLE: 'false',
  MARKETPLACE_TIME_CORRECTIONS_ENABLED: 'true',
  APPDATA: path.join(runtime, 'appdata'),
};
