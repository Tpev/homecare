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
                if ($this->isUnbufferedTypingField($tag)) {
                    $violations[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()).': '.trim(preg_replace('/\s+/', ' ', $tag));
                }
            }
        }

        $this->assertSame([], $violations);
    }

    public function test_guard_allows_debounced_search_and_native_pickers_but_rejects_unbuffered_text(): void
    {
        foreach (['<input wire:model.live="name">', '<x-input type="text" wire:model.live="name">',
            '<textarea wire:model.live="notes">', '<input type="search" wire:model.live.debounce.0ms="q">'] as $tag) {
            $this->assertTrue($this->isUnbufferedTypingField($tag), $tag);
        }

        foreach (['<input type="search" wire:model.live.debounce.300ms="q">',
            '<input type="date" wire:model.live="date">', '<input type="time" wire:model.live="time">',
            '<input type="checkbox" wire:model.live="enabled">'] as $tag) {
            $this->assertFalse($this->isUnbufferedTypingField($tag), $tag);
        }
    }

    private function isUnbufferedTypingField(string $tag): bool
    {
        if (preg_match('/type=["\'](?:checkbox|radio|hidden|date|time)["\']/i', $tag) === 1) {
            return false;
        }

        // Search fields deliberately update while typing, with an explicit pause.
        return preg_match('/wire:model\.live\.debounce\.(\d+)ms\s*=/', $tag, $debounce) !== 1
            || (int) $debounce[1] < 300;
    }
}
