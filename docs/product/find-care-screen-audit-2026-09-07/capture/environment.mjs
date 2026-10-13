import path from 'node:path';
import fs from 'node:fs';
export const root = process.cwd();
export const output = path.join(root, 'docs/product/find-care-screen-audit-2026-09-07');
export const runtime = path.join(root, '.tmp/find-care-screen-audit');
fs.mkdirSync(runtime, {recursive:true});
export const env = {...process.env,
 APP_ENV:'playwright', APP_DEBUG:'true', APP_URL:'http://127.0.0.1:8027',
 APP_KEY:'base64:'+Buffer.alloc(32,27).toString('base64'),
 DB_CONNECTION:'sqlite', DB_DATABASE:path.join(runtime,'find-care-audit.sqlite'),
 CACHE_STORE:'array', SESSION_DRIVER:'file', SESSION_COOKIE:'find_care_audit_session',
 QUEUE_CONNECTION:'database', MAIL_MAILER:'array',
 STRIPE_BYPASS:'true', DIDIT_BYPASS:'true', TWILIO_BYPASS:'true',
 STRIPE_SECRET:'', STRIPE_KEY:'', STRIPE_WEBHOOK_SECRET:'', OPENAI_API_KEY:'',
 AI_SUPPORT_PROVIDER_ENABLED:'false', AI_SUPPORT_RUNTIME_AVAILABLE:'false',
 MARKETPLACE_TIME_CORRECTIONS_ENABLED:'true',
 APPDATA:path.join(runtime,'appdata')
};
