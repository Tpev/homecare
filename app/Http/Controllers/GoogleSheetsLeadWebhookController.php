<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleSheetsLeadWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $rawBody = (string) $request->getContent();

        if (! $this->hasValidSignature($rawBody, (string) $request->header('X-LoLo-Signature'))) {
            Log::warning('Google Sheets lead webhook signature validation failed', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = $request->json()->all();
        if (! is_array($payload) || $payload === []) {
            return response()->json(['message' => 'Invalid JSON payload'], 422);
        }

        $row = $this->extractRow($payload);
        $normalizedRow = $this->normalizeRow($row);
        $mapped = $this->mapLead($payload, $row, $normalizedRow, $request);

        if (! filled($mapped['name']) && ! filled($mapped['email']) && ! filled($mapped['phone'])) {
            return response()->json(['message' => 'Lead must include a name, email, or phone.'], 422);
        }

        $result = DB::transaction(function () use ($mapped): array {
            $lead = $this->findExistingLead($mapped);
            $created = ! $lead;

            if (! $lead) {
                $lead = new Lead([
                    'lead_type' => $mapped['lead_type'],
                    'status' => $mapped['status'],
                ]);
            }

            foreach ($this->leadAttributes($mapped, $created) as $key => $value) {
                if ($created || filled($value)) {
                    $lead->{$key} = $value;
                }
            }

            $lead->data = array_replace_recursive(
                is_array($lead->data) ? $lead->data : [],
                $mapped['data'],
            );
            $lead->save();

            LeadActivity::query()->create([
                'lead_id' => $lead->id,
                'actor_user_id' => null,
                'type' => LeadActivity::TYPE_NOTE,
                'summary' => $created ? 'Imported from Google Sheet' : 'Google Sheet lead updated',
                'body' => $mapped['notes'],
                'occurred_at' => now(),
                'metadata' => [
                    'source' => 'google_sheets_webhook',
                    'external_source' => $mapped['external_source'],
                    'external_id' => $mapped['external_id'],
                    'spreadsheet_id' => $mapped['spreadsheet_id'],
                    'sheet_name' => $mapped['sheet_name'],
                    'row_number' => $mapped['row_number'],
                ],
            ]);

            return [
                'created' => $created,
                'lead' => $lead,
            ];
        });

        /** @var Lead $lead */
        $lead = $result['lead'];

        return response()->json([
            'ok' => true,
            'created' => $result['created'],
            'lead_id' => $lead->id,
            'status' => $lead->status,
        ]);
    }

    private function hasValidSignature(string $rawBody, string $providedSignature): bool
    {
        $secret = trim((string) config('services.google_sheets_leads.webhook_secret'));
        if ($secret === '' || $providedSignature === '') {
            return false;
        }

        $providedSignature = trim($providedSignature);
        $providedSignature = str_starts_with($providedSignature, 'sha256=')
            ? substr($providedSignature, 7)
            : $providedSignature;

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $providedSignature);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function extractRow(array $payload): array
    {
        $headers = Arr::get($payload, 'headers');
        $values = Arr::get($payload, 'values');

        if (is_array($headers) && is_array($values)) {
            $row = [];

            foreach (array_values($headers) as $index => $header) {
                if (! is_scalar($header)) {
                    continue;
                }

                $row[(string) $header] = $values[$index] ?? null;
            }

            return $row;
        }

        $row = Arr::get($payload, 'row');

        if (is_array($row)) {
            return $row;
        }

        return Arr::except($payload, ['headers', 'values']);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            if (! is_scalar($key)) {
                continue;
            }

            $normalizedKey = Str::of((string) $key)
                ->lower()
                ->replaceMatches('/[^a-z0-9]+/', '_')
                ->trim('_')
                ->toString();

            if ($normalizedKey === '') {
                continue;
            }

            if (! array_key_exists($normalizedKey, $normalized) || ! filled($normalized[$normalizedKey])) {
                $normalized[$normalizedKey] = is_string($value) ? trim($value) : $value;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $normalizedRow
     * @return array<string, mixed>
     */
    private function mapLead(array $payload, array $row, array $normalizedRow, Request $request): array
    {
        $leadType = $this->normalizeLeadType($this->firstValue($normalizedRow, [
            'lead_type',
            'pipeline',
            'type',
            'lead_kind',
        ]));

        $stageOptions = $leadType === Lead::TYPE_REFERRAL ? Lead::REFERRAL_STAGES : Lead::FAMILY_STAGES;
        $status = $this->firstValue($normalizedRow, ['crm_stage', 'stage', 'status']);
        $status = $status && array_key_exists($status, $stageOptions) ? $status : 'new';

        $facebookLeadId = $this->facebookLeadId($normalizedRow);
        $isFacebookLead = filled($facebookLeadId) || $this->isFacebookExportRow($normalizedRow);

        $source = $this->normalizeSource($this->firstValue($normalizedRow, [
            'crm_source',
            'lead_source',
            'source',
            'utm_source',
            'platform',
        ]) ?: ($isFacebookLead ? 'facebook_lead_ad' : 'google_sheet'));

        $spreadsheetId = $this->stringValue(Arr::get($payload, 'spreadsheet_id'));
        $sheetName = $this->stringValue(Arr::get($payload, 'sheet_name'));
        $rowNumber = $this->stringValue(Arr::get($payload, 'row_number'));
        $importKey = $this->firstNonEmpty([
            $this->stringValue(Arr::get($payload, 'import_key')),
            $this->stringValue(Arr::get($payload, 'lolo_import_key')),
            $this->firstValue($normalizedRow, ['lolo_import_key', 'lolo_import_id', 'import_key']),
            $facebookLeadId ? 'facebook:'.$facebookLeadId : null,
            $spreadsheetId && $rowNumber ? 'sheet:'.$spreadsheetId.':'.$sheetName.':'.$rowNumber : null,
        ]);

        $notes = $this->firstValue($normalizedRow, [
            'notes',
            'message',
            'care_needs',
            'comments',
            'description',
            'what_kind_of_care_do_you_need',
            '1_what_kind_of_help_is_needed',
        ]);

        $data = [
            'source' => 'google_sheets_webhook',
            'google_sheets' => [
                'spreadsheet_id' => $spreadsheetId,
                'sheet_name' => $sheetName,
                'row_number' => $rowNumber,
                'import_key' => $importKey,
                'synced_at' => now()->toISOString(),
            ],
            'row' => $row,
            'normalized_row' => $normalizedRow,
            'notes' => $notes,
        ];

        if ($facebookLeadId) {
            $data['facebook'] = [
                'lead_id' => $facebookLeadId,
                'ad_id' => $this->firstValue($normalizedRow, ['ad_id']),
                'adset_id' => $this->firstValue($normalizedRow, ['adset_id']),
                'campaign_id' => $this->firstValue($normalizedRow, ['campaign_id']),
                'form_id' => $this->firstValue($normalizedRow, ['form_id']),
                'campaign' => $this->firstValue($normalizedRow, ['campaign', 'campaign_name', 'utm_campaign']),
                'ad' => $this->firstValue($normalizedRow, ['ad', 'ad_name']),
                'adset' => $this->firstValue($normalizedRow, ['adset', 'adset_name']),
                'form' => $this->firstValue($normalizedRow, ['form', 'form_name']),
            ];
        }

        return [
            'lead_type' => $leadType,
            'status' => $status,
            'name' => $this->firstValue($normalizedRow, ['full_name', 'name', 'contact_name', 'customer_name', 'person_name']),
            'email' => $this->firstValue($normalizedRow, ['email', 'email_address', 'contact_email']),
            'phone' => $this->cleanPhone($this->firstValue($normalizedRow, ['phone', 'phone_number', 'mobile', 'cell', 'contact_phone'])),
            'company' => $this->firstValue($normalizedRow, ['company', 'organization', 'facility', 'practice']),
            'location' => $this->location($normalizedRow),
            'zip' => $this->firstValue($normalizedRow, ['zip', 'zipcode', 'zip_code', 'postal_code', 'postcode']),
            'priority' => $this->normalizePriority($this->firstValue($normalizedRow, ['priority', 'urgency'])),
            'source' => $source,
            'source_detail' => $this->firstValue($normalizedRow, [
                'source_detail',
                'campaign',
                'campaign_name',
                'ad_name',
                'form_name',
            ]) ?: ($sheetName ? 'Google Sheet: '.$sheetName : 'Google Sheet'),
            'external_source' => 'google_sheets',
            'external_id' => $importKey,
            'contact_role' => $this->firstValue($normalizedRow, ['contact_role', 'relationship', 'role']),
            'next_follow_up_at' => $this->parseDateTime($this->firstValue($normalizedRow, [
                'next_follow_up_at',
                'follow_up_at',
                'follow_up',
                'callback_time',
            ])),
            'source_url' => $this->firstValue($normalizedRow, ['source_url', 'landing_page', 'url']) ?: $this->stringValue(Arr::get($payload, 'sheet_url')),
            'referrer_url' => $this->firstValue($normalizedRow, ['referrer_url', 'referrer']),
            'ip' => $request->ip(),
            'user_agent' => 'Google Apps Script',
            'spreadsheet_id' => $spreadsheetId,
            'sheet_name' => $sheetName,
            'row_number' => $rowNumber,
            'notes' => $notes,
            'data' => $data,
        ];
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function findExistingLead(array $mapped): ?Lead
    {
        if (filled($mapped['external_source']) && filled($mapped['external_id'])) {
            $lead = Lead::query()
                ->where('external_source', $mapped['external_source'])
                ->where('external_id', $mapped['external_id'])
                ->first();

            if ($lead) {
                return $lead;
            }
        }

        if (filled($mapped['email'])) {
            $lead = Lead::query()
                ->where('lead_type', $mapped['lead_type'])
                ->where('email', $mapped['email'])
                ->first();

            if ($lead) {
                return $lead;
            }
        }

        if (filled($mapped['phone'])) {
            return Lead::query()
                ->where('lead_type', $mapped['lead_type'])
                ->where('phone', $mapped['phone'])
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<string, mixed>
     */
    private function leadAttributes(array $mapped, bool $created): array
    {
        return [
            'lead_type' => $mapped['lead_type'],
            'name' => $mapped['name'],
            'email' => $mapped['email'],
            'phone' => $mapped['phone'],
            'company' => $mapped['company'],
            'location' => $mapped['location'],
            'zip' => $mapped['zip'],
            'status' => $created ? $mapped['status'] : null,
            'priority' => $mapped['priority'],
            'source' => $mapped['source'],
            'source_detail' => $mapped['source_detail'],
            'external_source' => $mapped['external_source'],
            'external_id' => $mapped['external_id'],
            'contact_role' => $mapped['contact_role'],
            'next_follow_up_at' => $mapped['next_follow_up_at'],
            'source_url' => $mapped['source_url'],
            'referrer_url' => $mapped['referrer_url'],
            'ip' => $mapped['ip'],
            'user_agent' => $mapped['user_agent'],
        ];
    }

    private function normalizeLeadType(?string $leadType): string
    {
        $value = Str::of((string) $leadType)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->toString();

        return str_contains($value, 'referral')
            || str_contains($value, 'partner')
            || str_contains($value, 'provider')
            || str_contains($value, 'pcp')
            || str_contains($value, 'case_manager')
            ? Lead::TYPE_REFERRAL
            : Lead::TYPE_FAMILY;
    }

    private function normalizePriority(?string $priority): string
    {
        $value = Str::of((string) $priority)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->toString();

        return match ((string) $value) {
            Lead::PRIORITY_LOW => Lead::PRIORITY_LOW,
            Lead::PRIORITY_HIGH => Lead::PRIORITY_HIGH,
            Lead::PRIORITY_URGENT, 'asap', 'emergency' => Lead::PRIORITY_URGENT,
            default => Lead::PRIORITY_NORMAL,
        };
    }

    private function normalizeSource(?string $source): string
    {
        $value = Str::of((string) $source)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        return match ((string) $value) {
            'fb', 'facebook', 'meta' => 'facebook_lead_ad',
            default => $value !== '' ? $value : 'google_sheet',
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     */
    private function firstValue(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->stringValue($row[$key] ?? null);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<int, ?string>  $values
     */
    private function firstNonEmpty(array $values): ?string
    {
        foreach ($values as $value) {
            if (filled($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function location(array $row): ?string
    {
        $location = $this->firstValue($row, ['location', 'city_state', 'service_area']);
        if ($location) {
            return $location;
        }

        $facebookCareLocation = $this->firstValue($row, ['4_where_is_care_needed']);
        if ($facebookCareLocation) {
            return $facebookCareLocation;
        }

        $address = $this->firstValue($row, ['address', 'street_address']);
        $city = $this->firstValue($row, ['city', 'town']);
        $state = $this->firstValue($row, ['state', 'province']);
        $zip = $this->firstValue($row, ['zip', 'zipcode', 'zip_code', 'postal_code', 'postcode']);

        $parts = array_values(array_filter([$address, $city, $state, $zip], filled(...)));

        return $parts ? implode(', ', $parts) : null;
    }

    private function parseDateTime(?string $value): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function stringValue(mixed $value): ?string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function facebookLeadId(array $row): ?string
    {
        $explicit = $this->firstValue($row, [
            'facebook_lead_id',
            'fb_lead_id',
            'leadgen_id',
            'meta_lead_id',
        ]);

        if ($explicit) {
            return $explicit;
        }

        $genericId = $this->firstValue($row, ['id']);

        return $genericId && $this->isFacebookExportRow($row) ? $genericId : null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isFacebookExportRow(array $row): bool
    {
        $platform = Str::of((string) $this->firstValue($row, ['platform']))->lower()->toString();

        return in_array($platform, ['fb', 'facebook', 'meta'], true)
            || filled($this->firstValue($row, ['form_id', 'campaign_id', 'ad_id', 'adset_id']));
    }

    private function cleanPhone(?string $phone): ?string
    {
        if (! filled($phone)) {
            return null;
        }

        return Str::of($phone)->replaceMatches('/^p:/i', '')->trim()->toString();
    }
}
