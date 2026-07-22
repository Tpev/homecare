<?php

namespace App\Livewire\Admin;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Support\SdrOutreach;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class SdrOutreachCenter extends Component
{
    public string $pasteRows = '';

    public string $tags = '';

    public string $defaultStatus = 'new';

    public string $defaultPriority = Lead::PRIORITY_NORMAL;

    public string $defaultOwnerId = '';

    public string $metricsWindow = '7';

    public string $outcomeFilter = '';

    public int $recentCallsLimit = 12;

    /** @var array<string, mixed> */
    public array $importResult = [];

    public function updatedOutcomeFilter(): void
    {
        $this->recentCallsLimit = 12;
    }

    public function updatedMetricsWindow(): void
    {
        $this->recentCallsLimit = 12;
    }

    public function loadMoreRecentCalls(): void
    {
        $this->recentCallsLimit += 12;
    }

    public function importLeads(): void
    {
        $validated = $this->validate([
            'pasteRows' => ['required', 'string', 'max:120000'],
            'tags' => ['nullable', 'string', 'max:500'],
            'defaultStatus' => ['required', Rule::in(array_keys(Lead::REFERRAL_STAGES))],
            'defaultPriority' => ['required', Rule::in(array_keys($this->priorityOptions()))],
            'defaultOwnerId' => ['nullable', 'integer', Rule::in(array_keys($this->sdrOwnerOptions()))],
        ]);

        $rows = $this->parseRows($validated['pasteRows']);
        $tags = SdrOutreach::normalizeTags($validated['tags'] ?? '');
        $batchId = (string) Str::uuid();

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $examples = [];

        foreach ($rows as $row) {
            $mapped = $this->mapRow($row);

            if (! $this->rowHasEnoughContact($mapped)) {
                $skipped++;

                continue;
            }

            $existing = $this->findExistingLead($mapped);
            $lead = $existing ?: new Lead(['lead_type' => Lead::TYPE_REFERRAL]);
            $wasRecentlyCreated = ! $existing;
            $data = $lead->data ?: [];
            $existingTags = SdrOutreach::normalizeTags(data_get($data, 'sdr.tags', []));
            $mergedTags = collect($existingTags)->merge($tags)->unique()->values()->all();

            data_set($data, 'sdr.tags', $mergedTags);
            data_set($data, 'sdr.last_import_batch_id', $batchId);
            data_set($data, 'sdr.last_imported_at', now()->toISOString());
            data_set($data, 'sdr.last_imported_by', auth()->id());
            data_set($data, 'sdr.original_rows.'.$batchId, $row);

            $lead->fill([
                'lead_type' => Lead::TYPE_REFERRAL,
                'name' => $mapped['name'] ?: ($mapped['company'] ?: 'Referral source'),
                'email' => $mapped['email'],
                'phone' => $mapped['phone'],
                'company' => $mapped['company'],
                'location' => $mapped['location'],
                'zip' => $mapped['zip'],
                'status' => $wasRecentlyCreated ? $validated['defaultStatus'] : $lead->status,
                'priority' => $validated['defaultPriority'],
                'source' => SdrOutreach::SOURCE,
                'source_detail' => $tags === [] ? 'SDR call list' : 'SDR call list: '.implode(', ', $tags),
                'contact_role' => $mapped['contact_role'],
                'assigned_admin_id' => filled($validated['defaultOwnerId']) ? (int) $validated['defaultOwnerId'] : $lead->assigned_admin_id,
                'data' => $data,
            ]);

            $lead->save();

            $lead->activities()->create([
                'actor_user_id' => auth()->id(),
                'type' => LeadActivity::TYPE_NOTE,
                'summary' => $wasRecentlyCreated ? 'SDR lead imported' : 'SDR lead refreshed',
                'body' => $mapped['notes'] ?: null,
                'occurred_at' => now(),
                'metadata' => [
                    'source' => 'sdr_import',
                    'batch_id' => $batchId,
                    'tags' => $tags,
                    'row' => $row,
                ],
            ]);

            $wasRecentlyCreated ? $created++ : $updated++;

            if (count($examples) < 5) {
                $examples[] = $lead->company ?: $lead->name;
            }
        }

        $this->importResult = [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'batch_id' => $batchId,
            'examples' => $examples,
        ];

        if (($created + $updated) > 0) {
            $this->pasteRows = '';
        }

        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Import complete',
            'message' => "{$created} created, {$updated} updated, {$skipped} skipped.",
        ]);
    }

    public function render(): View
    {
        $days = max(1, min(30, (int) $this->metricsWindow));
        $start = now()->subDays($days - 1)->startOfDay();
        $activities = $this->sdrCallActivities($start);
        $recentCallActivities = $activities
            ->when(
                filled($this->outcomeFilter),
                fn ($calls) => $calls->filter(
                    fn (LeadActivity $activity): bool => data_get($activity->metadata, 'sdr_outcome') === $this->outcomeFilter
                )
            )
            ->values();

        return view('livewire.admin.sdr-outreach-center', [
            'dailyStats' => $this->dailyStats($activities),
            'ownerOptions' => $this->sdrOwnerOptions(),
            'outcomeOptions' => SdrOutreach::outcomeOptions(),
            'poolStats' => $this->poolStats($activities),
            'priorityOptions' => $this->priorityOptions(),
            'hasMoreRecentCalls' => $recentCallActivities->count() > $this->recentCallsLimit,
            'recentCalls' => $recentCallActivities->take($this->recentCallsLimit),
            'stageOptions' => Lead::REFERRAL_STAGES,
        ]);
    }

    /** @return list<array<string, string>> */
    private function parseRows(string $text): array
    {
        $lines = collect(preg_split('/\r\n|\r|\n/', $text) ?: [])
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->values();

        if ($lines->isEmpty()) {
            return [];
        }

        $delimiter = str_contains((string) $lines->first(), "\t") ? "\t" : ',';
        $rawRows = $lines
            ->map(fn ($line) => array_map('trim', str_getcsv((string) $line, $delimiter)))
            ->filter(fn ($row) => collect($row)->contains(fn ($value) => trim((string) $value) !== ''))
            ->values();

        if ($rawRows->isEmpty()) {
            return [];
        }

        $first = $rawRows->first();
        $normalizedFirst = collect($first)->map(fn ($header) => $this->headerKey((string) $header))->all();
        $knownHeaderCount = collect($normalizedFirst)
            ->filter(fn ($header) => in_array($header, ['company', 'name', 'contact_role', 'phone', 'email', 'location', 'zip', 'notes'], true))
            ->count();

        $hasHeader = $knownHeaderCount >= 2;
        $headers = $hasHeader
            ? $normalizedFirst
            : ['company', 'name', 'contact_role', 'phone', 'email', 'location', 'notes'];

        return $rawRows
            ->skip($hasHeader ? 1 : 0)
            ->map(function (array $row) use ($headers): array {
                $data = [];

                foreach ($row as $index => $value) {
                    $key = $headers[$index] ?? 'extra_'.$index;
                    if ($key !== '') {
                        $data[$key] = trim((string) $value);
                    }
                }

                return $data;
            })
            ->values()
            ->all();
    }

    /** @param array<string, string> $row */
    private function mapRow(array $row): array
    {
        $company = $this->first($row, ['company', 'practice', 'organization', 'facility']);
        $name = $this->first($row, ['name', 'contact_name', 'person', 'provider']);
        $phone = SdrOutreach::cleanPhone($this->first($row, ['phone', 'phone_number', 'telephone', 'mobile']));

        if ($company === '' && $name === '' && $phone !== '') {
            $company = 'Practice '.$phone;
        }

        return [
            'company' => $company,
            'name' => $name,
            'contact_role' => $this->first($row, ['contact_role', 'role', 'title', 'position']),
            'phone' => $phone,
            'email' => Str::lower($this->first($row, ['email', 'email_address'])),
            'location' => $this->first($row, ['location', 'city', 'address', 'county', 'area']),
            'zip' => $this->first($row, ['zip', 'zipcode', 'zip_code', 'postal_code']),
            'notes' => $this->first($row, ['notes', 'note', 'comment', 'comments', 'context']),
        ];
    }

    /** @param array<string, string> $mapped */
    private function rowHasEnoughContact(array $mapped): bool
    {
        return filled($mapped['company'])
            || filled($mapped['name'])
            || filled($mapped['phone'])
            || filled($mapped['email']);
    }

    /** @param array<string, string> $mapped */
    private function findExistingLead(array $mapped): ?Lead
    {
        if (filled($mapped['phone'])) {
            $lead = Lead::query()
                ->where('lead_type', Lead::TYPE_REFERRAL)
                ->where('phone', $mapped['phone'])
                ->first();

            if ($lead) {
                return $lead;
            }
        }

        if (filled($mapped['email'])) {
            $lead = Lead::query()
                ->where('lead_type', Lead::TYPE_REFERRAL)
                ->where('email', $mapped['email'])
                ->first();

            if ($lead) {
                return $lead;
            }
        }

        if (filled($mapped['company']) && filled($mapped['name'])) {
            return Lead::query()
                ->where('lead_type', Lead::TYPE_REFERRAL)
                ->where('company', $mapped['company'])
                ->where('name', $mapped['name'])
                ->first();
        }

        return null;
    }

    private function headerKey(string $header): string
    {
        $key = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($header)), '_');

        return match (true) {
            in_array($key, ['practice_name', 'practice', 'organization', 'organisation', 'company', 'clinic', 'facility', 'office', 'business', 'account', 'account_name'], true) => 'company',
            in_array($key, ['contact_name', 'contact', 'person', 'person_name', 'full_name', 'provider', 'provider_name', 'physician', 'doctor', 'pcp', 'social_worker', 'case_manager', 'name'], true) => 'name',
            in_array($key, ['contact_role', 'role', 'title', 'position', 'job_title'], true) => 'contact_role',
            in_array($key, ['phone', 'phone_number', 'telephone', 'mobile', 'office_phone', 'number'], true) => 'phone',
            in_array($key, ['email', 'email_address', 'mail'], true) => 'email',
            in_array($key, ['zip', 'zipcode', 'zip_code', 'postal_code', 'postcode'], true) => 'zip',
            in_array($key, ['location', 'city', 'address', 'street', 'county', 'area'], true) => 'location',
            in_array($key, ['notes', 'note', 'context', 'comments', 'comment'], true) => 'notes',
            default => $key,
        };
    }

    /** @param array<string, string> $row */
    private function first(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (filled($row[$key] ?? null)) {
                return trim((string) $row[$key]);
            }
        }

        return '';
    }

    /** @return array<string, string> */
    private function priorityOptions(): array
    {
        return [
            Lead::PRIORITY_LOW => 'Low',
            Lead::PRIORITY_NORMAL => 'Normal',
            Lead::PRIORITY_HIGH => 'High',
            Lead::PRIORITY_URGENT => 'Urgent',
        ];
    }

    /** @return array<int, string> */
    private function sdrOwnerOptions(): array
    {
        return User::query()
            ->where(function (Builder $query) {
                $query->whereIn('role', ['sdr', 'sales', 'admin'])
                    ->orWhereRaw('lower(email) = ?', ['test@test.com']);
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private function sdrCallActivities(Carbon $start): \Illuminate\Support\Collection
    {
        return LeadActivity::query()
            ->with(['actor:id,name,email,role', 'lead:id,lead_type,name,company,phone,status'])
            ->where('type', LeadActivity::TYPE_CALL)
            ->where('occurred_at', '>=', $start)
            ->latest('occurred_at')
            ->get()
            ->filter(fn (LeadActivity $activity): bool => filled(data_get($activity->metadata, 'sdr_outcome')));
    }

    private function dailyStats(\Illuminate\Support\Collection $activities): \Illuminate\Support\Collection
    {
        return $activities
            ->groupBy(fn (LeadActivity $activity): string => ($activity->occurred_at?->toDateString() ?? $activity->created_at->toDateString()).'|'.($activity->actor_user_id ?: 0))
            ->map(function (\Illuminate\Support\Collection $items): array {
                /** @var LeadActivity $first */
                $first = $items->first();
                $outcomes = $items->countBy(fn (LeadActivity $activity): string => (string) data_get($activity->metadata, 'sdr_outcome'));

                return [
                    'date' => $first->occurred_at?->format('M j') ?? $first->created_at->format('M j'),
                    'sdr' => $first->actor?->name ?: 'Unknown',
                    'total' => $items->count(),
                    'resource_requested' => (int) $outcomes->get('resource_requested', 0),
                    'meeting_requested' => (int) $outcomes->get('meeting_requested', 0),
                    'follow_up_later' => (int) $outcomes->get('follow_up_later', 0),
                    'no_answer' => (int) $outcomes->get('no_answer', 0) + (int) $outcomes->get('left_voicemail', 0),
                    'not_interested' => (int) $outcomes->get('not_interested', 0) + (int) $outcomes->get('do_not_call', 0),
                ];
            })
            ->sortByDesc('date')
            ->values();
    }

    private function poolStats(\Illuminate\Support\Collection $activities): array
    {
        $base = Lead::query()
            ->where('lead_type', Lead::TYPE_REFERRAL)
            ->where('source', SdrOutreach::SOURCE);

        return [
            'total' => (clone $base)->count(),
            'unassigned' => (clone $base)->whereNull('assigned_admin_id')->count(),
            'claimed' => (clone $base)->whereNotNull('assigned_admin_id')->count(),
            'due' => (clone $base)
                ->whereNotNull('next_follow_up_at')
                ->where('next_follow_up_at', '<=', now())
                ->whereNotIn('status', ['active_referral', 'not_fit', 'lost', 'closed'])
                ->count(),
            'calls' => $activities->count(),
            'resource_requested' => $activities
                ->filter(fn (LeadActivity $activity): bool => data_get($activity->metadata, 'sdr_outcome') === 'resource_requested')
                ->count(),
        ];
    }
}
