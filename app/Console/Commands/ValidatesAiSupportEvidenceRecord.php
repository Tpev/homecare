<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use JsonException;

abstract class ValidatesAiSupportEvidenceRecord extends Command
{
    private const MAX_RECORD_BYTES = 131_072;

    /** @return array{record:array<string,mixed>,path:string,expected_commit:string}|null */
    protected function loadEvidenceRecord(): ?array
    {
        $argument = trim((string) $this->argument('record'));
        $path = $this->absolutePath($argument);
        $realPath = realpath($path);
        if ($argument === '' || $realPath === false || ! is_file($realPath)) {
            $this->error('Evidence record was not found.');

            return null;
        }
        $size = filesize($realPath);
        if ($size === false || $size < 2 || $size > self::MAX_RECORD_BYTES) {
            $this->error('Evidence record must be a JSON file no larger than 128 KiB.');

            return null;
        }
        try {
            $record = json_decode((string) file_get_contents($realPath), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->error('Evidence record is not valid bounded JSON.');

            return null;
        }
        if (! is_array($record) || array_is_list($record)) {
            $this->error('Evidence record root must be an object.');

            return null;
        }

        $expectedCommit = trim((string) $this->option('expected-commit'));
        if ($expectedCommit === '') {
            $result = Process::path(base_path())->timeout(10)->run(['git', 'rev-parse', 'HEAD']);
            $expectedCommit = $result->successful() ? trim($result->output()) : '';
        }
        if (preg_match('/^[a-f0-9]{40}$/', $expectedCommit) !== 1) {
            $this->error('Expected commit must resolve to a full 40-character lowercase Git commit.');

            return null;
        }
        $commitExists = Process::path(base_path())->timeout(10)->run([
            'git', 'cat-file', '-e', $expectedCommit.'^{commit}',
        ]);
        if (! $commitExists->successful()) {
            $this->error('Expected commit does not exist in this repository.');

            return null;
        }

        return ['record' => $record, 'path' => $realPath, 'expected_commit' => $expectedCommit];
    }

    /** @param list<string> $errors */
    protected function renderFailure(array $errors): int
    {
        $this->error('EVIDENCE RECORD BLOCKED');
        $this->table(['#', 'Blocking reason'], collect($errors)
            ->values()
            ->map(fn (string $error, int $index): array => [$index + 1, $error])
            ->all());
        $this->warn('No Admin evidence, runtime control, pilot grant, or application record was changed.');

        return self::FAILURE;
    }

    protected function contentFreeHash(string $path): string
    {
        return hash_file('sha256', $path) ?: 'unavailable';
    }

    private function absolutePath(string $path): string
    {
        if (str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}
