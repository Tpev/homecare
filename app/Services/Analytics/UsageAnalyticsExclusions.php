<?php

namespace App\Services\Analytics;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class UsageAnalyticsExclusions
{
    public function users(Builder $query, string ...$columns): Builder
    {
        foreach ($columns as $column) {
            $column = $query->qualifyColumn($column);
            $query->where(fn (Builder $query) => $query
                ->whereNull($column)
                ->orWhereNotIn($column, $this->excludedUsers()));
        }

        return $query;
    }

    public function familyRecords(Builder $query, string ...$otherUserColumns): Builder
    {
        return $this->users($query, 'family_user_id', ...$otherUserColumns)
            // Historical records may still refer to a previous account owner.
            ->whereDoesntHave('familyAccount', fn (Builder $account) => $account
                ->whereIn('owner_user_id', $this->excludedUsers()));
    }

    private function excludedUsers(): Builder
    {
        $emails = array_map(
            fn (string $email): string => strtolower(trim($email)),
            config('analytics.usage_excluded_emails', []),
        );

        return User::query()
            ->select('id')
            ->whereIn(DB::raw('LOWER(TRIM(email))'), $emails);
    }
}
