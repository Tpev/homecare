# CRM SMS

Open a lead in either `/admin/crm` or `/admin/family-leads` and choose **Send SMS**. **Free text** is selected by default: type directly into **Message** and choose **Send text**. To start from a saved message, select **Use a template**. Switching back to free text keeps your current draft editable. The composer uses the lead's saved phone number and the existing `TWILIO_SMS_FROM` / `TWILIO_PHONE_NUMBER` configuration. Admin, sales, and SDR CRM users can send messages and manage shared templates. Selecting a template copies its text into an editable draft; later template edits and deletion do not change sent messages.

Outgoing text, sender, recipient, sending user, delivery status, errors, and incoming replies appear in the lead activity timeline. The visible timeline refreshes every 15 seconds. The admin SMS console remains available with its existing controls and also displays CRM messages.

Incoming texts use the existing signed Twilio webhook. A uniquely matching normalized lead phone is associated automatically. When multiple leads share a phone, the latest existing CRM SMS conversation determines the lead; ambiguous messages without a prior conversation stay in the admin inbox. Messages to a different sending number are not automatically attached to a CRM lead. All incoming texts generate an email to users whose current role is `admin` or `sdr`, including Johnny's SDR account, and to `peverelli.t@gmail.com` regardless of account role. Recipient addresses are deduplicated so an address on both lists receives one copy. The email includes the message, lead details when matched, phone numbers, timestamp, attachments, a CRM link, and up to ten previous messages. Unknown senders are still emailed.

Duplicate incoming callbacks preserve the original timestamp and do not duplicate timeline entries or completed email notifications. CRM sends have a request key to prevent duplicate submissions. Delivery callbacks retain the latest state even if they arrive out of order or before the send API responds. Existing do-not-contact flags block CRM sending.

Timeouts, server errors, or incomplete send responses are shown as **Awaiting confirmation**, because Twilio may already have accepted the text. CRM retains the original attempt and blocks identical recipient/text retries while its outcome is unknown, including after a reload or from another CRM lead sharing that number. A signed delivery callback resolves the attempt. Only a definite rejection permits an ordinary replacement send; an unresolved attempt needs provider review rather than an automatic resend.

## Deployment

Run `php artisan migrate --force` before serving the updated application. The migration adds SMS templates and lead/message links, and backfills the normalized phone index for existing leads. Use the existing Twilio credentials, Messaging Service, sender number, inbound webhook, and status callback configuration; no new credentials are needed.

Incoming alerts run through `SendIncomingSmsAlert` on the default queue, with five attempts and increasing delays. Keep the application's queue worker running and restart workers with the deployment. Failed email jobs can be retried with the application's normal queue retry workflow. With the synchronous queue driver, delivery happens in the webhook request. No production SMS or email is sent by the automated tests.

## Verification

- `php artisan test tests/Feature/Admin/CrmSmsMessagingTest.php tests/Feature/Admin/AdminSmsMessagesTest.php`
- `npm run build`
- `npx playwright test --config=playwright.crm-sms.config.ts`

Browser tests use a guarded, separate SQLite database, simulated Twilio sends, captured emails, and desktop/mobile Edge. They cover template management, personalized and free-text sends from both CRM views, and a reply appearing through timeline polling.
