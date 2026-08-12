<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Content\DocxBlogImporter;
use Illuminate\Console\Command;

class ImportDocxBlogPosts extends Command
{
    protected $signature = 'content:import-docx
        {path=blogs : A DOCX file or directory}
        {--user= : User ID recorded as importer}
        {--dry-run : Inspect files without writing posts or media}
        {--force : Update an existing import with the same source filename}';

    protected $description = 'Import legacy DOCX blog files as safe, noindex editorial drafts';

    public function handle(DocxBlogImporter $importer): int
    {
        $actor = $this->option('user')
            ? User::query()->find($this->option('user'))
            : User::query()->where('role', 'admin')->first();

        if (! $actor || ! $actor->isContentTeamMember()) {
            $this->error('Choose an admin or content-team user with --user=.');

            return self::FAILURE;
        }

        $path = (string) $this->argument('path');
        if (! str_starts_with($path, DIRECTORY_SEPARATOR) && ! preg_match('~^[A-Za-z]:[\\\\/]~', $path)) {
            $path = base_path($path);
        }
        $files = $importer->discover($path);
        if ($files === []) {
            $this->error('No DOCX files found.');

            return self::FAILURE;
        }

        $rows = [];
        $imported = 0;
        $skipped = 0;
        $failed = 0;
        foreach ($files as $file) {
            try {
                $result = $importer->import($file, $actor, (bool) $this->option('dry-run'), (bool) $this->option('force'));
                $result['skipped'] ? $skipped++ : $imported++;
                $rows[] = [
                    $result['source'],
                    $result['skipped'] ? 'skipped' : ($this->option('dry-run') ? 'reviewed' : 'draft #'.$result['post']?->id),
                    count($result['warnings']),
                ];
                foreach ($result['warnings'] as $warning) {
                    $this->warn($result['source'].': '.$warning);
                }
            } catch (\Throwable $exception) {
                $failed++;
                $rows[] = [basename($file), 'failed', 1];
                $this->error(basename($file).': '.$exception->getMessage());
            }
        }

        $this->table(['Source', 'Result', 'Warnings'], $rows);
        $this->info(($this->option('dry-run') ? 'Inspected' : 'Imported')." {$imported} file(s); skipped {$skipped}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
