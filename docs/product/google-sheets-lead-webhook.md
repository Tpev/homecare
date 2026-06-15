# Google Sheets Lead Import Without Google Cloud

This integration uses Google Apps Script inside the lead spreadsheet. No Google Cloud project, service account, or API key is required.

## LoLo server setup

Add a shared secret to the production `.env`:

```env
GOOGLE_SHEETS_LEADS_WEBHOOK_SECRET=make-this-a-long-random-secret
```

Then clear the config cache:

```bash
php artisan config:clear
```

Webhook URL:

```text
https://carelolo.com/webhooks/google-sheets/leads
```

The Apps Script signs each JSON payload with `X-LoLo-Signature: sha256=...`. LoLo rejects unsigned or incorrectly signed requests.

## Recommended sheet columns

The importer accepts flexible header names, but these are the cleanest:

```text
Full Name | Phone Number | Email | ZIP | City | State | Source | Campaign Name | Relationship | Care Needs | Facebook Lead ID
```

The script will add these operational columns if they are missing:

```text
lolo_import_key | lolo_synced_at | lolo_crm_lead_id | lolo_sync_error
```

## Apps Script

Open the Google Sheet, then go to `Extensions > Apps Script`, paste this script, and update `WEBHOOK_URL`, `SHARED_SECRET`, and `SHEET_NAME`.

```javascript
const WEBHOOK_URL = 'https://carelolo.com/webhooks/google-sheets/leads';
const SHARED_SECRET = 'make-this-a-long-random-secret';
const SHEET_NAME = 'Leads';

const IMPORT_KEY_HEADER = 'lolo_import_key';
const SYNCED_AT_HEADER = 'lolo_synced_at';
const CRM_LEAD_ID_HEADER = 'lolo_crm_lead_id';
const SYNC_ERROR_HEADER = 'lolo_sync_error';

function syncAllUnsyncedRows() {
  const sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(SHEET_NAME);
  if (!sheet) throw new Error(`Sheet not found: ${SHEET_NAME}`);

  ensureOperationalColumns_(sheet);

  const lastRow = sheet.getLastRow();
  if (lastRow < 2) return;

  const headers = getHeaders_(sheet);
  const syncedAtIndex = headers.indexOf(SYNCED_AT_HEADER) + 1;

  for (let rowNumber = 2; rowNumber <= lastRow; rowNumber++) {
    const syncedAt = sheet.getRange(rowNumber, syncedAtIndex).getValue();
    if (!syncedAt) {
      syncRow_(sheet, rowNumber);
    }
  }
}

function syncSelectedRow() {
  const sheet = SpreadsheetApp.getActiveSheet();
  ensureOperationalColumns_(sheet);

  const rowNumber = sheet.getActiveCell().getRow();
  if (rowNumber < 2) throw new Error('Select a lead row, not the header row.');

  syncRow_(sheet, rowNumber);
}

function onFormSubmit(e) {
  const sheet = e.range.getSheet();
  if (sheet.getName() !== SHEET_NAME) return;

  ensureOperationalColumns_(sheet);
  syncRow_(sheet, e.range.getRow());
}

function syncRow_(sheet, rowNumber) {
  const headers = getHeaders_(sheet);
  const indexes = {
    importKey: headers.indexOf(IMPORT_KEY_HEADER) + 1,
    syncedAt: headers.indexOf(SYNCED_AT_HEADER) + 1,
    crmLeadId: headers.indexOf(CRM_LEAD_ID_HEADER) + 1,
    syncError: headers.indexOf(SYNC_ERROR_HEADER) + 1,
  };

  let importKey = String(sheet.getRange(rowNumber, indexes.importKey).getValue() || '').trim();
  if (!importKey) {
    importKey = Utilities.getUuid();
    sheet.getRange(rowNumber, indexes.importKey).setValue(importKey);
  }

  const values = sheet.getRange(rowNumber, 1, 1, headers.length).getValues()[0];
  const row = {};
  headers.forEach((header, index) => {
    row[header] = values[index];
  });

  const payload = {
    spreadsheet_id: SpreadsheetApp.getActiveSpreadsheet().getId(),
    sheet_name: sheet.getName(),
    row_number: rowNumber,
    import_key: importKey,
    row: row,
  };

  const body = JSON.stringify(payload);
  const response = UrlFetchApp.fetch(WEBHOOK_URL, {
    method: 'post',
    contentType: 'application/json',
    payload: body,
    headers: {
      'X-LoLo-Signature': `sha256=${hmacSha256Hex_(body, SHARED_SECRET)}`,
    },
    muteHttpExceptions: true,
  });

  const code = response.getResponseCode();
  const text = response.getContentText();

  if (code < 200 || code >= 300) {
    sheet.getRange(rowNumber, indexes.syncError).setValue(`HTTP ${code}: ${text}`);
    throw new Error(`LoLo sync failed for row ${rowNumber}: HTTP ${code}`);
  }

  const result = JSON.parse(text);
  sheet.getRange(rowNumber, indexes.syncedAt).setValue(new Date());
  sheet.getRange(rowNumber, indexes.crmLeadId).setValue(result.lead_id || '');
  sheet.getRange(rowNumber, indexes.syncError).setValue('');
}

function ensureOperationalColumns_(sheet) {
  [IMPORT_KEY_HEADER, SYNCED_AT_HEADER, CRM_LEAD_ID_HEADER, SYNC_ERROR_HEADER].forEach(header => {
    ensureColumn_(sheet, header);
  });
}

function ensureColumn_(sheet, header) {
  const headers = getHeaders_(sheet);
  if (headers.indexOf(header) !== -1) return;

  sheet.getRange(1, headers.length + 1).setValue(header);
}

function getHeaders_(sheet) {
  const lastColumn = sheet.getLastColumn();
  return sheet.getRange(1, 1, 1, lastColumn).getValues()[0].map(value => String(value).trim());
}

function hmacSha256Hex_(value, secret) {
  const signature = Utilities.computeHmacSha256Signature(value, secret);
  let hex = '';

  for (let i = 0; i < signature.length; i++) {
    const byte = signature[i] < 0 ? signature[i] + 256 : signature[i];
    const piece = byte.toString(16);
    hex += piece.length === 1 ? `0${piece}` : piece;
  }

  return hex;
}
```

## Trigger setup

Use one of these:

- Time based: run `syncAllUnsyncedRows` every 5 minutes.
- Google Form lead sheet: create an installable trigger for `onFormSubmit`.
- Manual testing: select a row and run `syncSelectedRow`.

## Testing

1. Add one fake row.
2. Run `syncSelectedRow`.
3. Confirm `lolo_synced_at` and `lolo_crm_lead_id` are filled.
4. Open `/admin/crm` and confirm the lead appears in the family pipeline.
5. Run `syncSelectedRow` again on the same row and confirm no duplicate CRM lead is created.
