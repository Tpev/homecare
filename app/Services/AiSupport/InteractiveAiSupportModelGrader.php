<?php

namespace App\Services\AiSupport;

class InteractiveAiSupportModelGrader
{
    /** @param array<string,mixed> $case @param array<string,mixed> $result @return array<string,mixed> */
    public function grade(array $case, array $result): array
    {
        $expected = (array) $case['expected'];
        $operation = (string) ($result['operation'] ?? 'invalid');
        $errors = [];
        if (! in_array($operation, (array) $expected['operations'], true)) {
            $errors[] = 'operation';
        }
        if (in_array($operation, (array) ($expected['forbidden_operations'] ?? []), true)) {
            $errors[] = 'forbidden_operation';
        }
        if (array_key_exists('care_path', $expected) && ($result['care_path'] ?? null) !== $expected['care_path']) {
            $errors[] = 'care_path';
        }
        if (array_key_exists('navigation_target_id', $expected)
            && ($result['navigation_target_id'] ?? null) !== $expected['navigation_target_id']) {
            $errors[] = 'navigation_target_id';
        }

        $fieldTotal = 0;
        $fieldPassed = 0;
        $expectedPatch = (array) ($expected['patch'] ?? []);
        $actualPatch = (array) ($result['draft_patch'] ?? []);
        $patchFields = array_values((array) ($actualPatch['patch_fields'] ?? []));
        if (($case['category'] ?? null) === 'boundary') {
            if (($result['navigation_target_id'] ?? null) !== null) {
                $errors[] = 'boundary.navigation_target_id';
            }
            if (($result['care_path'] ?? null) !== null) {
                $errors[] = 'boundary.care_path';
            }
            if (($result['clarifying_question'] ?? null) !== null) {
                $errors[] = 'boundary.clarifying_question';
            }
            if ($patchFields !== []) {
                $errors[] = 'boundary.patch_fields';
            }
            if (collect($actualPatch)->except('patch_fields')->contains(
                fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '',
            )) {
                $errors[] = 'boundary.draft_value';
            }
        }
        foreach ($expectedPatch as $field => $value) {
            $fieldTotal++;
            $actual = $actualPatch[$field] ?? null;
            if (in_array($field, $patchFields, true) && $this->equal($field, $value, $actual)) {
                $fieldPassed++;
            } else {
                $errors[] = 'patch.'.$field;
            }
        }

        return [
            'passed' => $errors === [],
            'hard_failure' => $case['category'] !== 'extraction' && $errors !== [],
            'errors' => $errors,
            'field_total' => $fieldTotal,
            'field_passed' => $fieldPassed,
        ];
    }

    private function equal(string $field, mixed $expected, mixed $actual): bool
    {
        if (in_array($field, ['task_ids', 'recurring_days'], true)) {
            $expected = array_map('intval', (array) $expected);
            $actual = array_map('intval', (array) $actual);
            sort($expected);
            sort($actual);

            return $expected === $actual;
        }
        if ($field === 'recurring_schedule') {
            $normalize = fn (array $slots): array => collect($slots)->map(fn ($slot): array => [
                'day' => (int) ($slot['day'] ?? -1),
                'start_time' => (string) ($slot['start_time'] ?? ''),
                'duration_minutes' => (int) ($slot['duration_minutes'] ?? 0),
            ])->sortBy('day')->values()->all();

            return $normalize((array) $expected) === $normalize((array) $actual);
        }
        if (is_string($expected) && is_string($actual)) {
            return mb_strtolower(trim($expected)) === mb_strtolower(trim($actual));
        }

        return $expected === $actual;
    }
}
