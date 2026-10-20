<?php

namespace App\Services\CustomerOnboarding;

use App\Models\CareRequest;
use App\Models\FamilyAccount;
use App\Models\FamilyOnboarding;
use App\Models\Lead;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CustomerOnboardingDirectory
{
    public function paginate(string $search = '', string $filter = 'all', int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->query();
        $search = mb_substr(trim($search), 0, 200);
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                foreach (['name', 'email', 'phone'] as $column) {
                    $query->orWhere($column, 'like', '%'.$search.'%');
                }
            });
        }

        match ($filter) {
            'no_account' => $query->whereNull('account_id'),
            'payment_missing' => $query->where('account_status', FamilyAccount::STATUS_ACTIVE)->where('payment_status', 'missing'),
            'payment_unknown' => $query->where('account_status', FamilyAccount::STATUS_ACTIVE)->where('payment_status', 'unknown'),
            'no_request' => $query->where('request_posted', 0),
            'no_hire' => $query->where('hired', 0),
            'hired' => $query->where('hired', 1),
            default => null,
        };

        return $query->orderByDesc('updated_at')->orderBy('source_type')->orderByDesc('source_id')
            ->paginate(max(1, min(100, $perPage)))
            ->through(fn (object $row): array => $this->row($row));
    }

    public function summary(): array
    {
        $summary = $this->query()->selectRaw(
            "COUNT(*) AS total,
            COALESCE(SUM(CASE WHEN account_id IS NOT NULL THEN 1 ELSE 0 END), 0) AS accounts,
            COALESCE(SUM(CASE WHEN account_id IS NULL THEN 1 ELSE 0 END), 0) AS no_account,
            COALESCE(SUM(CASE WHEN account_status = 'active' AND payment_status = 'missing' THEN 1 ELSE 0 END), 0) AS payment_missing,
            COALESCE(SUM(CASE WHEN account_status = 'active' AND payment_status = 'unknown' THEN 1 ELSE 0 END), 0) AS payment_unknown,
            COALESCE(SUM(request_posted), 0) AS request_posted,
            COALESCE(SUM(hired), 0) AS hired"
        )->first();

        return array_map('intval', (array) $summary);
    }

    public function find(string $key): array
    {
        abort_unless(preg_match('/\A(account|lead):([1-9][0-9]*)\z/', $key, $parts) === 1, 404);
        $record = $this->query()->where('source_type', $parts[1])->where('source_id', $parts[2])->first();
        abort_unless($record, 404);
        $row = $this->row($record);

        return $row + [
            'account' => $row['account_id'] ? FamilyAccount::with('owner')->find($row['account_id']) : null,
            'lead' => $row['lead_id'] ? Lead::with('assignedAdmin')->find($row['lead_id']) : null,
            'onboarding' => $row['onboarding_id'] ? FamilyOnboarding::with('welcomeVisit')->find($row['onboarding_id']) : null,
            'request' => $row['latest_request_id'] ? CareRequest::query()->find($row['latest_request_id']) : null,
        ];
    }

    private function query(): Builder
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);

        // One row per household, regardless of how many people or CRM records belong to it.
        $linkedLeads = DB::table('leads')->where('lead_type', Lead::TYPE_FAMILY)
            ->whereNotNull('family_account_id')
            ->selectRaw('family_account_id, MAX(id) AS lead_id')
            ->groupBy('family_account_id');
        $requests = DB::table('care_requests')
            ->where('is_system_generated', false)
            ->whereIn('status', [
                CareRequest::STATUS_OPEN, CareRequest::STATUS_FILLED,
                CareRequest::STATUS_CANCELLED, CareRequest::STATUS_EXPIRED,
            ])
            ->selectRaw("family_account_id, MAX(id) AS latest_request_id, MIN(first_hire_at) AS first_hire_at,
                MAX(CASE WHEN first_hire_at IS NOT NULL
                    OR EXISTS (SELECT 1 FROM care_request_applications application
                        WHERE application.care_request_id = care_requests.id AND application.status = 'hired')
                    OR EXISTS (SELECT 1 FROM care_bookings booking
                        WHERE booking.care_request_id = care_requests.id)
                    THEN 1 ELSE 0 END) AS hired")
            ->groupBy('family_account_id');
        $expiryYear = $this->cardExpiryPart('exp_year');
        $expiryMonth = $this->cardExpiryPart('exp_month');
        $today = now();

        $accounts = DB::table('family_accounts as account')
            ->leftJoin('users as owner', 'owner.id', '=', 'account.owner_user_id')
            ->leftJoinSub($linkedLeads, 'linked', 'linked.family_account_id', '=', 'account.id')
            ->leftJoinSub($requests, 'requests', 'requests.family_account_id', '=', 'account.id')
            ->leftJoin('family_onboardings as onboarding', 'onboarding.family_account_id', '=', 'account.id')
            ->selectRaw(
                "'account' AS source_type, account.id AS source_id, account.id AS account_id,
                linked.lead_id, COALESCE(owner.name, 'Family account') AS name,
                owner.email, owner.phone, account.status AS account_status,
                CASE WHEN COALESCE(NULLIF(account.stripe_customer_id, ''), NULLIF(owner.stripe_customer_id, '')) IS NULL THEN 'missing'
                    WHEN account.payment_method_status = 'ready' AND {$expiryYear} > 0 AND {$expiryMonth} > 0
                        AND ({$expiryYear} < ? OR ({$expiryYear} = ? AND {$expiryMonth} < ?)) THEN 'missing'
                    ELSE COALESCE(account.payment_method_status, 'unknown') END AS payment_status,
                CASE WHEN requests.latest_request_id IS NOT NULL THEN 1 ELSE 0 END AS request_posted,
                COALESCE(requests.hired, 0) AS hired,
                requests.latest_request_id, requests.first_hire_at, onboarding.id AS onboarding_id,
                account.created_at, account.updated_at",
                [$today->year, $today->year, $today->month]
            );
        $leads = DB::table('leads')
            ->where('lead_type', Lead::TYPE_FAMILY)
            ->whereNull('family_account_id')
            ->selectRaw(
                "'lead' AS source_type, id AS source_id, NULL AS account_id, id AS lead_id,
                name, email, phone, 'not_created' AS account_status,
                'not_applicable' AS payment_status, 0 AS request_posted, 0 AS hired,
                NULL AS latest_request_id, NULL AS first_hire_at, NULL AS onboarding_id,
                created_at, updated_at"
            );

        return DB::query()->fromSub($accounts->unionAll($leads), 'customers');
    }

    private function cardExpiryPart(string $field): string
    {
        $value = "JSON_EXTRACT(account.payment_method_summary, '$.{$field}')";

        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST({$value} AS INTEGER)"
            : "CAST(JSON_UNQUOTE({$value}) AS UNSIGNED)";
    }

    private function row(object $record): array
    {
        $row = (array) $record;
        $row['key'] = $row['source_type'].':'.$row['source_id'];
        unset($row['source_type'], $row['source_id']);
        foreach (['account_id', 'lead_id', 'latest_request_id', 'onboarding_id'] as $column) {
            $row[$column] = $row[$column] === null ? null : (int) $row[$column];
        }
        $row['request_posted'] = (bool) $row['request_posted'];
        $row['hired'] = (bool) $row['hired'];

        return $row;
    }
}
