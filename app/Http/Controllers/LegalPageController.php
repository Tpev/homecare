<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class LegalPageController extends Controller
{
    public function index(): View
    {
        $pages = collect(config('legal_pages.pages', []))
            ->map(function (array $page, string $slug): array {
                return [
                    'slug' => $slug,
                    'title' => (string) Arr::get($page, 'title', $slug),
                    'description' => (string) Arr::get($page, 'description', ''),
                    'url' => route('legal.show', ['slug' => $slug]),
                ];
            })
            ->values()
            ->all();

        return view('legal.index', [
            'pages' => $pages,
        ]);
    }

    public function show(string $slug): View
    {
        $page = config("legal_pages.pages.$slug");
        abort_unless(is_array($page), 404);

        $path = resource_path('legal/'.Arr::get($page, 'file', ''));
        abort_unless(File::exists($path), 404);

        $lines = collect(file($path, FILE_IGNORE_NEW_LINES))
            ->map(static fn ($line) => trim((string) $line))
            ->filter()
            ->values();

        $documentHeader = null;
        if ($lines->isNotEmpty() && $this->looksLikeDocumentHeader((string) $lines->first())) {
            $documentHeader = (string) $lines->shift();
        }

        $documentTitle = null;
        if ($lines->isNotEmpty()) {
            $documentTitle = (string) $lines->shift();
        }

        $effectiveLine = null;
        if ($lines->isNotEmpty() && str_starts_with(strtolower((string) $lines->first()), 'effective date')) {
            $effectiveLine = (string) $lines->shift();
        }

        return view('legal.show', [
            'slug' => $slug,
            'page' => $page,
            'documentHeader' => $documentHeader,
            'documentTitle' => $documentTitle ?: Arr::get($page, 'title'),
            'effectiveLine' => $effectiveLine,
            'lines' => $lines,
            'allPages' => collect(config('legal_pages.pages', []))
                ->map(function (array $item, string $itemSlug): array {
                    return [
                        'slug' => $itemSlug,
                        'title' => (string) Arr::get($item, 'title', $itemSlug),
                        'url' => route('legal.show', ['slug' => $itemSlug]),
                    ];
                })
                ->values()
                ->all(),
        ]);
    }

    private function looksLikeDocumentHeader(string $line): bool
    {
        $normalized = strtolower(trim($line));

        return str_contains($normalized, 'lolo care inc')
            || str_contains($normalized, 'healthcare')
            || str_ends_with($normalized, ' llc')
            || str_ends_with($normalized, ' inc');
    }
}
