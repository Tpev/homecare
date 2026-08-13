<?php

namespace App\Services\AiSupport;

use App\Models\KnowledgeBaseVersion;

class KnowledgeBaseValidationService
{
    public function __construct(private readonly NavigationTargetRegistry $navigation) {}

    /** @return array{passed: bool, errors: array<string, list<string>>, checked_at: string, contract_version: string} */
    public function validate(KnowledgeBaseVersion $version): array
    {
        $version->loadMissing('sources');
        $errors = [];

        $this->requiredText($errors, 'title', $version->title, 3, 255);
        $this->requiredText($errors, 'answer_body', $version->answer_body, 10, 50000);
        $this->requiredText($errors, 'product_area', $version->product_area, 2, 120);
        $this->requiredText($errors, 'change_note', $version->change_note, 5, 500);

        if (! in_array($version->type, ['product_fact', 'task_playbook', 'navigation', 'escalation'], true)) {
            $errors['type'][] = 'Select an approved knowledge-entry type.';
        }

        if (! in_array($version->sensitivity, ['public', 'authenticated', 'shared_care', 'owner_only', 'restricted'], true)) {
            $errors['sensitivity'][] = 'Select an approved sensitivity.';
        }

        $roles = array_values(array_unique((array) $version->roles));
        if ($roles === [] || array_diff($roles, (array) config('ai_support.supported_roles', [])) !== []) {
            $errors['roles'][] = 'Choose at least one supported role.';
        }

        if ((array) $version->capability_ids === []) {
            $errors['capability_ids'][] = 'Add at least one capability ID.';
        }

        if ((array) $version->evaluation_ids === []) {
            $errors['evaluation_ids'][] = 'Add at least one regression evaluation ID.';
        }

        if (! $version->review_by || $version->review_by->isBefore(today())) {
            $errors['review_by'][] = 'Review-by date must be today or later.';
        }

        if ($version->expires_on && $version->expires_on->isBefore(today())) {
            $errors['expires_on'][] = 'Expiration cannot be in the past.';
        }

        if ($version->sources->isEmpty()) {
            $errors['sources'][] = 'Add at least one authoritative source.';
        }

        foreach ($version->sources as $index => $source) {
            $prefix = 'sources.'.($index + 1);
            if (! preg_match('/^SRC-[A-Z0-9-]+$/', $source->source_id)) {
                $errors[$prefix][] = 'Source ID must use the SRC- registry format.';
            }
            if (trim((string) $source->title) === '' || trim((string) $source->fact_supported) === '') {
                $errors[$prefix][] = 'Source title and supported fact are required.';
            }
            if ($source->url && filter_var($source->url, FILTER_VALIDATE_URL) === false) {
                $errors[$prefix][] = 'Source URL must be valid when provided.';
            }
        }

        foreach ((array) $version->route_target_ids as $targetId) {
            if (! $this->navigation->has((string) $targetId)) {
                $errors['route_target_ids'][] = 'Unknown or unresolved semantic target: '.$targetId;
            }
        }

        return [
            'passed' => $errors === [],
            'errors' => $errors,
            'checked_at' => now()->toIso8601String(),
            'contract_version' => 'kb-validation-v1',
        ];
    }

    /** @param array<string, list<string>> $errors */
    private function requiredText(array &$errors, string $field, mixed $value, int $min, int $max): void
    {
        $length = mb_strlen(trim((string) $value));
        if ($length < $min || $length > $max) {
            $errors[$field][] = "Must contain between {$min} and {$max} characters.";
        }
    }
}
