<?php

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LivewireInputStabilityTest extends TestCase
{
    public function test_typing_fields_do_not_live_update_on_each_keystroke(): void
    {
        $violations = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = File::get($file->getPathname());
            preg_match_all('/<(x-input|x-textarea|input|textarea)\b[^>]*wire:model\.live[^>]*>/is', $contents, $matches);

            foreach ($matches[0] as $tag) {
                $isClickControl = preg_match('/type=["\'](?:checkbox|radio|hidden)["\']/i', $tag) === 1;
                if (! $isClickControl) {
                    $violations[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()).': '.trim(preg_replace('/\s+/', ' ', $tag));
                }
            }
        }

        $this->assertSame([], $violations);
    }
}
