<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ZapierFacebookLeadWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->hasValidToken($request)) {
            Log::warning('Zapier Facebook lead webhook token validation failed', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json(['message' => 'Invalid webhook token.'], 401);
        }

        $payload = $request->isJson() ? $request->json()->all() : $request->all();

        if (! is_array($payload) || $payload === [] || array_is_list($payload)) {
            return response()->json(['message' => 'The webhook payload must be a non-empty JSON object.'], 422);
        }

        $normalizedPayload = $this->normalizePayload($payload);
        $mapped = $this->mapLead($payload, $normalizedPayload, $request);

        $validator = Validator::make($mapped, [
            'external_id' => ['required', 'string', 'max:160'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:255'],
            'source_detail' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid Facebook lead data.',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (! filled($mapped['name']) && ! filled($mapped['email']) && ! filled($mapped['phone'])) {
            return response()->json(['message' => 'Lead must include a name, email, or phone.'], 422);
        }

        $result = DB::transaction(function () use ($mapped): array {
            $lead = Lead::query()
                ->where('external_source', $mapped['external_source'])
                ->where('external_id', $mapped['external_id'])
                ->first();
            $created = ! $lead;

            if (! $lead) {
                $lead = new Lead([
                    'lead_type' => Lead::TYPE_FAMILY,
                    'status' => 'new',
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
                'summary' => $created
                    ? 'Imported from Facebook Lead Ads via Zapier'
                    : 'Facebook Lead Ads lead updated via Zapier',
                'body' => $mapped['notes'],
                'occurred_at' => now(),
                'metadata' => [
                    'source' => 'zapier_facebook_lead_webhook',
                    'external_source' => $mapped['external_source'],
                    'external_id' => $mapped['external_id'],
                    'page_id' => data_get($mapped, 'data.facebook.page_id'),
                    'form_id' => data_get($mapped, 'data.facebook.form_id'),
                    'campaign_id' => data_get($mapped, 'data.facebook.campaign_id'),
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
            'external_id' => $lead->external_id,
        ]);
    }

    private function hasValidToken(Request $request): bool
    {
        $expected = trim((string) config('services.zapier_facebook_leads.webhook_secret'));
        $provided = trim((string) ($request->bearerToken() ?: $request->header('X-LoLo-Zapier-Token')));

        return $expected !== '' && $provided !== '' && hash_equals($expected, $provided);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        $raw = is_array($payload['raw'] ?? null) ? $payload['raw'] : [];
        $values = array_merge($raw, $payload);
        $normalized = [];

        foreach ($values as $key => $value) {
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
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    private function mapLead(array $payload, array $normalized, Request $request): array
    {
        $leadId = $this->firstValue($normalized, [
            'lead_id',
            'facebook_lead_id',
            'fb_lead_id',
            'leadgen_id',
            'raw_lead_id',
        ]);
        $name = $this->firstValue($normalized, ['full_name', 'name', 'raw_full_name']);
        $email = $this->firstValue($normalized, ['email', 'email_address', 'raw_email']);
        $phone = $this->cleanPhone($this->firstValue($normalized, [
            'phone_number',
            'phone',
            'mobile',
            'raw_phone_number',
        ]));

        $helpNeeded = $this->firstValue($normalized, [
            'help_needed',
            'what_kind_of_help_is_needed',
            '1_what_kind_of_help_is_needed',
            'raw_1_what_kind_of_help_is_needed',
        ]);
        $startTiming = $this->firstValue($normalized, [
            'start_timing',
            'when_would_you_like_help_to_start',
            '2_when_would_you_like_help_to_start',
            'raw_2_when_would_you_like_help_to_start',
        ]);
        $bestContactTime = $this->firstValue($normalized, [
            'best_contact_time',
            'when_is_the_best_time_to_reach_you',
            '3_when_is_the_best_time_to_reach_you',
            'raw_3_when_is_the_best_time_to_reach_you',
        ]);
        $careLocation = $this->firstValue($normalized, [
            'care_location',
            'where_is_care_needed_city_or_zip',
            '4_where_is_care_needed_city_or_zip',
            '4_where_is_care_needed',
            'raw_4_where_is_care_needed_city_or_zip',
            'raw_4_where_is_care_needed',
        ]);
        $zip = $this->firstValue($normalized, ['zip', 'zipcode', 'zip_code', 'postal_code'])
            ?: $this->extractZip($careLocation);
        $notes = $this->buildNotes($helpNeeded, $startTiming, $bestContactTime, $careLocation);

        $facebook = [
            'lead_id' => $leadId,
            'created_time' => $this->firstValue($normalized, ['created_time', 'created_at']),
            'form_id' => $this->firstValue($normalized, ['form_id']),
            'form_name' => $this->firstValue($normalized, ['form_name']),
            'page_id' => $this->firstValue($normalized, ['page_id']),
            'page_name' => $this->firstValue($normalized, ['page_name']),
            'ad_id' => $this->firstValue($normalized, ['ad_id']),
            'ad_name' => $this->firstValue($normalized, ['ad_name']),
            'adset_id' => $this->firstValue($normalized, ['adset_id', 'ad_set_id']),
            'adset_name' => $this->firstValue($normalized, ['adset_name', 'ad_set_name']),
            'campaign_id' => $this->firstValue($normalized, ['campaign_id']),
            'campaign_name' => $this->firstValue($normalized, ['campaign_name']),
            'platform' => $this->firstValue($normalized, ['platform']),
            'partner_name' => $this->firstValue($normalized, ['partner_name']),
        ];

        return [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'location' => $careLocation,
            'zip' => $zip,
            'source_detail' => $this->firstNonEmpty([
                $facebook['campaign_name'],
                $facebook['form_name'],
                $facebook['page_name'],
                'Facebook Lead Ads',
            ]),
            'external_source' => 'facebook_lead_ads',
            'external_id' => $leadId,
            'submitted_at' => $this->parseDateTime($facebook['created_time']),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'notes' => $notes,
            'data' => [
                'source' => 'zapier_facebook_lead_webhook',
                'facebook' => $facebook,
                'care_preferences' => [
                    'help_needed' => $helpNeeded,
                    'start_timing' => $startTiming,
                    'best_contact_time' => $bestContactTime,
                    'care_location' => $careLocation,
                ],
                'notes' => $notes,
                'zapier' => [
                    'received_at' => now()->toISOString(),
                ],
                'raw_payload' => $payload,
                'normalized_payload' => $normalized,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<string, mixed>
     */
    private function leadAttributes(array $mapped, bool $created): array
    {
        return [
            'lead_type' => Lead::TYPE_FAMILY,
            'name' => $mapped['name'],
            'email' => $mapped['email'],
            'phone' => $mapped['phone'],
            'location' => $mapped['location'],
            'zip' => $mapped['zip'],
            'status' => $created ? 'new' : null,
            'priority' => Lead::PRIORITY_NORMAL,
            'source' => 'facebook_lead_ad',
            'source_detail' => $mapped['source_detail'],
            'external_source' => $mapped['external_source'],
            'external_id' => $mapped['external_id'],
            'submitted_at' => $mapped['submitted_at'],
            'ip' => $mapped['ip'],
            'user_agent' => $mapped['user_agent'],
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<int, string>  $keys
     */
    private function firstValue(array $values, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->stringValue($values[$key] ?? null);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /** @param array<int, ?string> $values */
    private function firstNonEmpty(array $values): ?string
    {
        foreach ($values as $value) {
            if (filled($value)) {
                return $value;
            }
        }

        return null;
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

    private function cleanPhone(?string $phone): ?string
    {
        if (! filled($phone)) {
            return null;
        }

        return Str::of($phone)->replaceMatches('/^(?:p:|tel:)/i', '')->trim()->toString();
    }

    private function extractZip(?string $location): ?string
    {
        if (! filled($location) || preg_match('/\b(\d{5})(?:-\d{4})?\b/', $location, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function parseDateTime(?string $value): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->setTimezone((string) config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildNotes(
        ?string $helpNeeded,
        ?string $startTiming,
        ?string $bestContactTime,
        ?string $careLocation,
    ): ?string {
        $details = [
            'Help needed' => $helpNeeded,
            'Preferred start' => $startTiming,
            'Best time to reach' => $bestContactTime,
            'Care location' => $careLocation,
        ];
        $lines = [];

        foreach ($details as $label => $value) {
            if (filled($value)) {
                $lines[] = $label.': '.$value;
            }
        }

        return $lines === [] ? null : implode(PHP_EOL, $lines);
    }
}
