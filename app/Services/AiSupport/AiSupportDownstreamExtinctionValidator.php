<?php

namespace App\Services\AiSupport;

use Carbon\CarbonImmutable;
use Throwable;

class AiSupportDownstreamExtinctionValidator
{
    public const SCHEMA = 'ai-support-downstream-extinction-v1';

    /** @var list<string> */
    public const SCOPES = [
        'retired_legacy_copilot',
        'current_ai_support',
    ];

    /** @var list<string> */
    public const DESTINATION_CATEGORIES = [
        'primary_database',
        'read_and_delayed_replicas',
        'database_backups_and_snapshots',
        'analytics_and_warehouse',
        'search_and_vector_indexes',
        'application_and_edge_caches',
        'logs_and_error_monitoring',
        'manual_exports_and_workstations',
        'production_fixtures_and_clones',
    ];

    /** @var list<string> */
    public const COMPLETE_STATUSES = [
        'not_present',
        'verified_zero',
        'destroyed',
        'expired_and_verified',
    ];

    /** @var list<string> */
    public const RESTORE_CHECKS = [
        'restored_environment_inaccessible_before_redeletion',
        'retirement_code_present_before_access',
        'deletion_manifest_applied_before_access',
        'target_zero_verified_before_access',
        'preserved_domain_records_verified',
        'human_support_available_after_release',
    ];

    /** @return array{passed:bool,errors:list<string>,release_commit:?string,destination_count:int,restore_check_count:int} */
    public function validate(array $record, string $expectedCommit): array
    {
        $errors = [];
        $this->exactKeys($record, [
            'schema_version', 'evidence_reference', 'release_commit', 'observed_at',
            'operator_reference', 'destinations', 'restore_redeletion_rehearsal',
        ], 'Extinction record', $errors);
        if (($record['schema_version'] ?? null) !== self::SCHEMA) {
            $errors[] = 'Extinction record schema is not recognized.';
        }
        $this->safeReference($record['evidence_reference'] ?? null, 'EXT-', 'Extinction evidence reference is invalid.', $errors);
        $this->releaseCommit($record['release_commit'] ?? null, $expectedCommit, $errors);
        $this->pastTimestamp($record['observed_at'] ?? null, 'Extinction observation timestamp is invalid or in the future.', $errors);
        if (! is_string($record['operator_reference'] ?? null)
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9 ._-]{1,79}$/', $record['operator_reference']) !== 1) {
            $errors[] = 'Extinction operator reference is invalid.';
        }

        $destinations = is_array($record['destinations'] ?? null) && array_is_list($record['destinations'])
            ? $record['destinations']
            : [];
        if (! is_array($record['destinations'] ?? null) || ! array_is_list($record['destinations'] ?? [])) {
            $errors[] = 'Destinations must be a list.';
        }
        $expectedCount = count(self::SCOPES) * count(self::DESTINATION_CATEGORIES);
        if (count($destinations) !== $expectedCount) {
            $errors[] = "Exactly {$expectedCount} scoped destination records are required.";
        }

        $combinations = [];
        $completeDestinations = 0;
        foreach ($destinations as $index => $destination) {
            $row = $index + 1;
            if (! is_array($destination)) {
                $errors[] = "Destination row {$row} must be an object.";

                continue;
            }
            $this->exactKeys($destination, [
                'scope', 'category', 'status', 'checked_at', 'evidence_reference',
            ], "Destination row {$row}", $errors);
            $scope = $destination['scope'] ?? null;
            $category = $destination['category'] ?? null;
            if (! in_array($scope, self::SCOPES, true)) {
                $errors[] = "Destination row {$row} has an invalid scope.";
            }
            if (! in_array($category, self::DESTINATION_CATEGORIES, true)) {
                $errors[] = "Destination row {$row} has an invalid category.";
            }
            if (is_string($scope) && is_string($category)) {
                $combinations[] = $scope.'|'.$category;
            }

            $status = $destination['status'] ?? null;
            if (! in_array($status, self::COMPLETE_STATUSES, true)) {
                $errors[] = "Destination row {$row} is pending or has an invalid extinction status.";
            } else {
                $completeDestinations++;
            }
            if ($scope === 'current_ai_support' && $category === 'primary_database' && $status !== 'verified_zero') {
                $errors[] = 'Current AI Support primary-database evidence must be verified_zero before the pilot.';
            }
            if ($scope === 'retired_legacy_copilot'
                && $category === 'primary_database'
                && ! in_array($status, ['verified_zero', 'destroyed'], true)) {
                $errors[] = 'Retired legacy primary-database evidence must be verified_zero or destroyed.';
            }
            $this->pastTimestamp($destination['checked_at'] ?? null, "Destination row {$row} checked_at is invalid or in the future.", $errors);
            $this->safeReference($destination['evidence_reference'] ?? null, 'EVD-', "Destination row {$row} evidence reference is invalid.", $errors);
        }

        $expectedCombinations = [];
        foreach (self::SCOPES as $scope) {
            foreach (self::DESTINATION_CATEGORIES as $category) {
                $expectedCombinations[] = $scope.'|'.$category;
            }
        }
        sort($combinations);
        sort($expectedCombinations);
        if ($combinations !== $expectedCombinations) {
            $errors[] = 'Every scope/category combination must appear exactly once.';
        }

        $restore = is_array($record['restore_redeletion_rehearsal'] ?? null)
            ? $record['restore_redeletion_rehearsal']
            : [];
        if (! is_array($record['restore_redeletion_rehearsal'] ?? null)) {
            $errors[] = 'Restore/re-deletion rehearsal must be an object.';
        }
        $this->exactKeys($restore, [
            'rehearsal_reference', 'performed_at', 'environment', 'checks',
        ], 'Restore/re-deletion rehearsal', $errors);
        $this->safeReference($restore['rehearsal_reference'] ?? null, 'RESTORE-', 'Restore rehearsal reference is invalid.', $errors);
        $this->pastTimestamp($restore['performed_at'] ?? null, 'Restore rehearsal timestamp is invalid or in the future.', $errors);
        if (($restore['environment'] ?? null) !== 'isolated') {
            $errors[] = 'Restore/re-deletion rehearsal environment must be isolated.';
        }
        $checks = is_array($restore['checks'] ?? null) ? $restore['checks'] : [];
        if (! is_array($restore['checks'] ?? null)) {
            $errors[] = 'Restore/re-deletion checks must be an object.';
        }
        $this->exactKeys($checks, self::RESTORE_CHECKS, 'Restore/re-deletion checks', $errors);
        foreach (self::RESTORE_CHECKS as $check) {
            if (($checks[$check] ?? null) !== true) {
                $errors[] = 'Required restore/re-deletion check did not pass: '.$check.'.';
            }
        }

        return [
            'passed' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'release_commit' => is_string($record['release_commit'] ?? null) ? $record['release_commit'] : null,
            'destination_count' => $completeDestinations,
            'restore_check_count' => count(array_filter(
                self::RESTORE_CHECKS,
                fn (string $key): bool => ($checks[$key] ?? null) === true,
            )),
        ];
    }

    /** @param list<string> $expected @param list<string> $errors */
    private function exactKeys(array $value, array $expected, string $label, array &$errors): void
    {
        $actual = array_keys($value);
        sort($actual);
        sort($expected);
        if ($actual !== $expected) {
            $errors[] = $label.' has missing or unsupported fields.';
        }
    }

    /** @param list<string> $errors */
    private function safeReference(mixed $value, string $prefix, string $message, array &$errors): void
    {
        if (! is_string($value)
            || preg_match('/^'.preg_quote($prefix, '/').'[A-Za-z0-9][A-Za-z0-9._:-]{2,100}$/', $value) !== 1) {
            $errors[] = $message;
        }
    }

    /** @param list<string> $errors */
    private function releaseCommit(mixed $actual, string $expected, array &$errors): void
    {
        if (! is_string($actual) || preg_match('/^[a-f0-9]{40}$/', $actual) !== 1) {
            $errors[] = 'Release commit must be a full 40-character lowercase Git commit.';

            return;
        }
        if (! hash_equals($expected, $actual)) {
            $errors[] = 'Release commit does not match the expected commit.';
        }
    }

    /** @param list<string> $errors */
    private function pastTimestamp(mixed $value, string $message, array &$errors): ?CarbonImmutable
    {
        if (! is_string($value)
            || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/', $value) !== 1) {
            $errors[] = $message;

            return null;
        }
        try {
            $timestamp = CarbonImmutable::parse($value);
        } catch (Throwable) {
            $errors[] = $message;

            return null;
        }
        if ($timestamp->greaterThan(now()->addMinutes(5))) {
            $errors[] = $message;

            return null;
        }

        return $timestamp;
    }
}
