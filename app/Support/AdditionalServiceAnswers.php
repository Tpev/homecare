<?php

namespace App\Support;

final class AdditionalServiceAnswers
{
    /**
     * Null means the question was omitted; an empty list is an explicit empty answer.
     *
     * @return list<string>|null
     */
    public static function fromPayload(array $payload): ?array
    {
        // Zapier's explicitly mapped field takes precedence over its raw source copy.
        if (array_key_exists('additional_services', $payload)) {
            return self::values($payload['additional_services']);
        }

        $found = false;
        $answers = [];
        self::collect($payload, $found, $answers);

        return $found ? array_values(array_unique($answers)) : null;
    }

    /** @return list<string> */
    public static function fromLeadData(array $data): array
    {
        // A mapped answer, including an empty list, supersedes older raw payloads.
        foreach (['care_preferences', 'form_answers'] as $section) {
            if (is_array($data[$section] ?? null) && array_key_exists('additional_services', $data[$section])) {
                return self::values($data[$section]['additional_services']);
            }
        }

        // Existing imports can be displayed without rewriting historical records.
        foreach (['raw_payload', 'normalized_payload'] as $section) {
            if (is_array($data[$section] ?? null)) {
                $answers = self::fromPayload($data[$section]);
                if ($answers !== null) {
                    return $answers;
                }
            }
        }

        return [];
    }

    /** @param list<string> $answers */
    private static function collect(array $fields, bool &$found, array &$answers): void
    {
        // Meta may represent each selected choice as a separate name/values entry.
        $question = $fields['name'] ?? $fields['label'] ?? $fields['question'] ?? null;
        if (is_string($question) && self::isQuestion($question)) {
            foreach (['values', 'value', 'answer'] as $key) {
                if (array_key_exists($key, $fields)) {
                    $found = true;
                    array_push($answers, ...self::values($fields[$key]));
                }
            }
        }

        foreach ($fields as $key => $value) {
            if (is_string($key) && self::isQuestion($key)) {
                $found = true;
                array_push($answers, ...self::values($value));
            } elseif (is_array($value) && (is_int($key) || in_array(self::normalizeKey($key), [
                'raw', 'field_data', 'fields', 'answers', 'form_answers', 'care_preferences',
            ], true))) {
                self::collect($value, $found, $answers);
            }
        }
    }

    private static function isQuestion(string $key): bool
    {
        return preg_match(
            '/^(?:raw_)?(?:\d+_)?(?:additional_services(?:_of_interest)?|other_services|which_additional_services_would_you_like_more_information_about(?:_select_all_that_apply)?)(?:_\d+)?$/',
            self::normalizeKey($key),
        ) === 1;
    }

    private static function normalizeKey(string $key): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($key)), '_');
    }

    /** @return list<string> */
    private static function values(mixed $value): array
    {
        if (is_array($value)) {
            $answers = [];
            foreach ($value as $key => $item) {
                if (is_int($key) || in_array($key, ['values', 'value', 'answer'], true)) {
                    array_push($answers, ...self::values($item));
                }
            }

            return array_values(array_unique($answers));
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $value = trim($value);
        $decoded = json_decode($value, true);
        if (is_array($decoded) && array_is_list($decoded)) {
            return self::values($decoded);
        }

        // Commas are part of option labels (e.g. ramps, grab bars, stair lifts).
        return array_values(array_unique(array_filter(
            array_map('trim', preg_split('/[\r\n;]+/', $value)),
            static fn (string $answer): bool => $answer !== '',
        )));
    }
}
