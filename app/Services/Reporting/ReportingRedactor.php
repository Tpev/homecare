<?php

namespace App\Services\Reporting;

final class ReportingRedactor
{
    private array $allowed;

    public int $omittedKeys = 0;

    public int $redactedStrings = 0;

    public function __construct()
    {
        $this->allowed = array_fill_keys(ReportingCatalog::businessJsonKeys(), true);
    }

    public function value(mixed $value): mixed
    {
        if (is_object($value)) {
            $result = new \stdClass;
            foreach (get_object_vars($value) as $key => $child) {
                if (! isset($this->allowed[$key])) {
                    $this->omittedKeys++;

                    continue;
                }
                $result->{$key} = $this->value($child);
            }

            return $result;
        }
        if (is_array($value)) {
            return array_map($this->value(...), $value);
        }
        if (! is_string($value)) {
            return $value;
        }
        // Action tokens can be in URL paths as well as queries. Preserve prose, not executable links.
        $clean = preg_replace('~(?:https?://|www\.)[^\s<>"\x27]+~iu', '[link removed]', $value);
        $clean = preg_replace('~/(?:payment-setup|password/reset|reset-password|magic-link|checkout|verify-email)/[^\s<>"\x27]+~iu', '[action link removed]', $clean);
        $clean = preg_replace('~\b(?:sk|rk|pk)_(?:live|test)_[A-Za-z0-9]+\b|\b(?:pi|seti)_[A-Za-z0-9]+_secret_[A-Za-z0-9]+\b|\bBearer\s+[A-Za-z0-9._\~+/=-]+~i', '[credential removed]', $clean);
        $clean = preg_replace('~\b(?:password|client_secret|access_token|api_key|token|signature)\s*[=:]\s*["\x27]?[^\s,"\x27&<>]+~i', '[credential removed]', $clean);
        if ($clean !== $value) {
            $this->redactedStrings++;
        }

        return $clean;
    }

    public function record(object $row, array $jsonFields): array
    {
        $record = [];
        foreach (get_object_vars($row) as $key => $value) {
            if ($value !== null && in_array($key, $jsonFields, true)) {
                // Invalid structured data fails the snapshot instead of silently dropping evidence.
                $value = json_decode($value, false, 512, JSON_THROW_ON_ERROR);
            }
            $record[$key] = $this->value($value);
        }

        return $record;
    }
}
