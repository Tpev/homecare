<?php

namespace Tests\Concerns;

use App\Models\BlogPost;
use App\Models\ContentAuthor;
use App\Models\ContentCategory;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\Content\BlogPostReadiness;
use App\Services\Content\BlogPostWorkflow;
use Illuminate\Support\Facades\Queue;

trait CreatesPublishedBlogPosts
{
    /** @return array{post:BlogPost,publisher:User,reviewer_user:User,author:ContentAuthor,reviewer:ContentAuthor,media:MediaAsset,category:ContentCategory} */
    protected function createPublishedBlogPost(array $overrides = []): array
    {
        Queue::fake();
        $publisher = User::factory()->create(['role' => 'admin']);
        $reviewerUser = User::factory()->create(['role' => 'family', 'content_role' => 'editor']);
        $authorUser = User::factory()->create(['role' => 'family']);
        $author = ContentAuthor::query()->create([
            'user_id' => $authorUser->id,
            'name' => 'Jordan Local',
            'slug' => 'jordan-local',
            'headline' => 'Triangle family care researcher',
            'bio' => str_repeat('Jordan researches practical local care choices for Triangle families. ', 2),
            'credentials' => 'Community care researcher',
            'is_active' => true,
        ]);
        $reviewer = ContentAuthor::query()->create([
            'user_id' => $reviewerUser->id,
            'name' => 'Morgan Reviewer',
            'slug' => 'morgan-reviewer',
            'headline' => 'LoLo Care editorial reviewer',
            'bio' => str_repeat('Morgan reviews local care guidance for factual accuracy, scope, sourcing, clarity, and accessibility. ', 2),
            'credentials' => 'Editorial review: sourcing, non-medical boundaries, and local care information',
            'is_active' => true,
        ]);
        $media = MediaAsset::query()->create([
            'disk' => 'public',
            'path' => 'content/test/guide.jpg',
            'original_filename' => 'guide.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1234,
            'width' => 1600,
            'height' => 900,
            'alt_text' => 'A caregiver talking with an older Raleigh resident at home',
            'license' => 'Owned',
            'uploaded_by_user_id' => $publisher->id,
        ]);
        $category = ContentCategory::query()->create([
            'name' => 'Triangle care guides',
            'slug' => 'triangle-care-guides',
            'description' => 'Reviewed local guidance for families arranging flexible support in Raleigh and the Triangle.',
            'is_active' => true,
        ]);

        $text = trim(str_repeat('Families arranging non-medical support in Raleigh should compare availability, experience, communication, transportation needs, scheduling expectations, references, and the exact daily tasks involved. Clear written expectations help everyone make a safer and more confident decision. ', 12));
        $document = [
            'type' => 'doc',
            'content' => [
                ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'How to compare local care options']]],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]],
                ['type' => 'faq', 'content' => [[
                    'type' => 'faqItem', 'attrs' => ['question' => 'What should a family compare first?'],
                    'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Start with the person’s actual needs, timing, location, and preferred way of communicating.']]]],
                ]]],
            ],
        ];

        $workflow = app(BlogPostWorkflow::class);
        $post = $workflow->createDraft($publisher, $overrides['title'] ?? 'How to Compare Home Care Options in Raleigh');
        $post = $workflow->save($post, array_merge([
            'title' => 'How to Compare Home Care Options in Raleigh',
            'slug' => 'compare-home-care-options-raleigh',
            'excerpt' => 'A practical, source-backed framework for comparing flexible non-medical home support options in Raleigh, North Carolina.',
            'content_json' => $document,
            'author_id' => $author->id,
            'featured_media_asset_id' => $media->id,
            'seo_title' => 'Compare Raleigh Home Care Options | LoLo Care',
            'meta_description' => 'Compare Raleigh home care options using a clear framework for needs, schedules, experience, communication, references, and daily support.',
            'editorial_checklist' => array_fill_keys(array_keys(BlogPostReadiness::checklist()), true),
            'research_methodology' => 'This guide synthesizes public state guidance and structured interviews with local families.',
        ], $overrides), $publisher, [$category->id], [], [[
            'title' => 'Home Care Independence Guidance',
            'publisher' => 'North Carolina Department of Health and Human Services',
            'url' => 'https://www.ncdhhs.gov/',
            'published_on' => now()->subYear()->toDateString(),
            'accessed_on' => now()->toDateString(),
            'notes' => 'Used for definitions and service boundaries.',
        ]], 'Test article prepared');
        $post = $workflow->submitForReview($post, $publisher);
        $post = $workflow->approveReview($post, $reviewerUser, 'Claims, links, scope, and accessibility reviewed.');
        $post = $workflow->publish($post, $publisher);

        return [
            'post' => $post,
            'publisher' => $publisher,
            'reviewer_user' => $reviewerUser,
            'author' => $author,
            'reviewer' => $reviewer,
            'media' => $media,
            'category' => $category,
        ];
    }
}
