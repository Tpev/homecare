<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\ContentCategory;
use App\Services\Content\PublicBlogPresenter;
use Illuminate\Http\Response;

class LlmsController extends Controller
{
    public function index(PublicBlogPresenter $presenter): Response
    {
        $posts = BlogPost::published()->with('publishedRevision')->latest('first_published_at')->limit(30)->get();
        $items = $presenter->presentMany($posts);
        $lines = [
            '# LoLo Care',
            '',
            '> LoLo Care is a Raleigh, North Carolina marketplace where families can find and coordinate independent caregivers for flexible, non-medical support at home.',
            '',
            'LoLo Care supports companionship, meal preparation, errands, transportation assistance, light housekeeping, respite, supervision, reminders, and daily routines. LoLo Care is not a medical provider or emergency service. Families review and choose caregivers; availability varies.',
            '',
            '## Primary pages',
            '',
            '- [LoLo Care home]('.route('landing').'): Service overview.',
            '- [For families]('.route('landing.family').'): How families arrange support.',
            '- [For caregivers]('.route('landing.caregiver').'): Information for independent caregivers.',
            '- [Reviewed resource center]('.route('blog.index').'): Sourced and maintained care guidance.',
            '- [Atom feed]('.route('blog.feed').'): Recently published and updated guides.',
            '',
            '## Reviewed resource categories',
            '',
        ];

        foreach (ContentCategory::active()->orderBy('sort_order')->get()->filter(fn (ContentCategory $category): bool => BlogPost::published()->publishedInCategory($category->id)->exists()) as $category) {
            $lines[] = '- ['.$category->name.']('.route('blog.category', $category).'): '.trim((string) $category->description);
        }

        $lines[] = '';
        $lines[] = '## Reviewed articles';
        $lines[] = '';
        foreach ($items as $post) {
            $byline = $post['author'] ? ' By '.$post['author']->name.'.' : '';
            $lines[] = '- ['.$post['title'].']('.$post['url'].'): '.$post['excerpt'].$byline;
        }

        $lines = array_merge($lines, [
            '', '## Policies and machine discovery', '',
            '- [Legal documents]('.route('legal.index').'): Terms, privacy, payments, and safety policies.',
            '- [Privacy policy]('.route('legal.show', ['slug' => 'privacy-policy']).'): How LoLo Care collects, uses, and protects information.',
            '- [Safety policy]('.route('legal.show', ['slug' => 'safety-policy']).'): Platform scope, safety expectations, and emergency boundaries.',
            '- [XML sitemap]('.route('sitemap.xml').'): Canonical public and indexable URLs with truthful modification dates.',
            '',
            'Only published articles that pass the CMS readiness checks are listed here. Drafts and archived articles are excluded.',
        ]);

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=900',
        ]);
    }
}
