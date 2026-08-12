<?php

namespace App\Policies;

use App\Models\BlogPost;
use App\Models\User;

class BlogPostPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdministrator() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isContentTeamMember();
    }

    public function view(User $user, BlogPost $post): bool
    {
        return $user->isContentTeamMember();
    }

    public function create(User $user): bool
    {
        return $user->isContentTeamMember();
    }

    public function update(User $user, BlogPost $post): bool
    {
        if ($user->canReviewContent()) {
            return true;
        }

        return $user->content_role === 'author' && (int) $post->created_by_user_id === (int) $user->id;
    }

    public function review(User $user, BlogPost $post): bool
    {
        return $user->canReviewContent();
    }

    public function publish(User $user, BlogPost $post): bool
    {
        return $user->canPublishContent();
    }

    public function archive(User $user, BlogPost $post): bool
    {
        return $user->canPublishContent();
    }

    public function delete(User $user, BlogPost $post): bool
    {
        return $user->canPublishContent() && $post->published_revision_id === null;
    }

    public function restore(User $user, BlogPost $post): bool
    {
        return $user->canPublishContent();
    }
}
