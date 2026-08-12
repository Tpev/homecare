<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Models\User;
use App\Services\Content\BlogPostWorkflow;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class PublishScheduledBlogPosts extends Command
{
    protected $signature = 'content:publish-scheduled';

    protected $description = 'Publish reviewed blog posts whose scheduled time has arrived';

    public function handle(BlogPostWorkflow $workflow): int
    {
        $published = 0;
        $failed = 0;
        BlogPost::query()
            ->where('status', BlogPost::STATUS_SCHEDULED)
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->orderBy('id')
            ->each(function (BlogPost $post) use ($workflow, &$published, &$failed): void {
                $publisher = User::query()->find($post->published_by_user_id);
                if (! $publisher?->canPublishContent()) {
                    $publisher = User::query()->where('role', 'admin')->first();
                }
                if (! $publisher) {
                    $post->forceFill([
                        'status' => BlogPost::STATUS_DRAFT,
                        'scheduled_for' => null,
                        'submitted_for_review_at' => null,
                        'submitted_by_user_id' => null,
                        'reviewed_at' => null,
                        'reviewed_by_user_id' => null,
                        'reviewer_id' => null,
                    ])->save();
                    $workflow->recordRevision($post, null, 'Scheduled publication stopped because no publisher account was available');
                    $this->error("Post {$post->id} returned to draft because no publisher account is available.");
                    $failed++;

                    return;
                }
                try {
                    $workflow->publishNow($post, $publisher, true);
                    $published++;
                } catch (ValidationException $exception) {
                    $post->refresh();
                    if ($post->status === BlogPost::STATUS_SCHEDULED) {
                        $post->forceFill([
                            'status' => BlogPost::STATUS_DRAFT,
                            'scheduled_for' => null,
                            'submitted_for_review_at' => null,
                            'submitted_by_user_id' => null,
                            'reviewed_at' => null,
                            'reviewed_by_user_id' => null,
                            'reviewer_id' => null,
                        ])->save();
                        $workflow->recordRevision($post, $publisher, 'Scheduled publication stopped by readiness validation');
                    }
                    $this->error("Post {$post->id} returned to draft: ".implode(' ', $exception->errors()['publish'] ?? [$exception->getMessage()]));
                    $failed++;
                } catch (\Throwable $exception) {
                    report($exception);
                    $this->error("Post {$post->id} could not be published: {$exception->getMessage()}");
                    $failed++;
                }
            });

        $this->info("Published {$published} scheduled post(s); {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
