<?php

namespace App\Services\Screening\Imports;

use Illuminate\Support\Str;

class NameNormalizer
{
    public static function normalize(?string $name): string
    {
        $name = Str::ascii(mb_strtolower(trim($name ?? ''), 'UTF-8'));
        // Remove apostrophes so O'Neil and ONeil agree; hyphens preserve word boundaries.
        $name = str_replace(["'", '’', '`'], '', $name);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $name));
    }

    public static function names(array $row, array $fields): array
    {
        $first = trim($row[$fields['first_name'] ?? ''] ?? '');
        $last = trim($row[$fields['last_name'] ?? ''] ?? '');
        $full = trim($row[$fields['full_name'] ?? ''] ?? '');
        if ($full === '') {
            $full = trim(implode(' ', array_filter([$first, trim($row[$fields['middle_name'] ?? ''] ?? ''), $last])));
        }
        if ($first === '' || $last === '') {
            [$first, $last, $full] = self::parts($full, $fields['name_order'] ?? 'first_last');
        }
        $names = [self::entry($full, $first, $last, false)];
        foreach ($fields['aliases'] ?? [] as $field) {
            $value = trim($row[$field] ?? '');
            if ($value === '') {
                continue;
            }
            $aliases = isset($fields['alias_separator']) ? explode($fields['alias_separator'], $value) : [$value];
            foreach ($aliases as $alias) {
                [$aliasFirst, $aliasLast, $aliasFull] = self::parts(trim($alias), $fields['alias_name_order'] ?? $fields['name_order'] ?? 'first_last');
                if ($aliasFull !== '') {
                    $names[] = self::entry($aliasFull, $aliasFirst, $aliasLast, true);
                }
            }
        }

        return $names;
    }

    private static function parts(string $name, string $order): array
    {
        if ($order === 'last_comma_first') {
            $parts = explode(',', $name, 2);
            if (count($parts) !== 2) {
                throw new \RuntimeException('A name does not match the configured last_comma_first layout.');
            }
            $last = trim($parts[0]);
            $given = trim($parts[1]);

            return [explode(' ', $given)[0], $last, trim($given.' '.$last)];
        }
        if ($order !== 'first_last') {
            throw new \RuntimeException('Unsupported name order.');
        }
        $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);

        return [$parts[0] ?? '', count($parts) > 1 ? end($parts) : '', $name];
    }

    private static function entry(string $full, string $first, string $last, bool $alias): array
    {
        if (mb_strlen($full) > 250 || mb_strlen($first) > 100 || mb_strlen($last) > 100 || self::normalize($full) === '') {
            throw new \RuntimeException('Missing or invalid searchable name.');
        }

        return ['display_name' => $full, 'normalized_name' => self::normalize($full), 'first_name' => self::normalize($first), 'last_name' => self::normalize($last), 'is_alias' => $alias];
    }
}
