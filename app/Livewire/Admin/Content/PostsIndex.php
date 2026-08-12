<?php

namespace App\Livewire\Admin\Content;

use App\Models\BlogPost;
use App\Models\BlogPostEvent;
use App\Services\Content\BlogPostWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class PostsIndex extends Component
{
    use WithPagination;

    public string $q = '';

    public string $status = 'all';

    public string $sort = 'updated_at';

    protected $queryString = [
        'q' => ['except' => ''],
        'status' => ['except' => 'all'],
        'sort' => ['except' => 'updated_at'],
    ];

    public function mount(): void
    {
        Gate::authorize('viewAny', BlogPost::class);
    }

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function createPost(BlogPostWorkflow $workflow): void
    {
        Gate::authorize('create', BlogPost::class);
        $post = $workflow->createDraft(auth()->user());
        $this->redirectRoute('admin.content.posts.edit', ['blogPost' => $post->id], navigate: true);
    }

    public function archive(int $postId, BlogPostWorkflow $workflow): void
    {
        $post = BlogPost::query()->findOrFail($postId);
        Gate::authorize('archive', $post);
        $workflow->archive($post, auth()->user());
        session()->flash('status', 'Article archived and removed from public discovery.');
    }

    public function render(): View
    {
        $posts = BlogPost::query()
            ->with(['author', 'featuredMedia', 'publishedRevision'])
            ->withCount([
                'sources',
                'revisions',
                'events as views_30d_count' => fn ($query) => $query->where('event', 'page_view')->where('occurred_at', '>=', now()->subDays(30)),
                'events as reads_30d_count' => fn ($query) => $query->where('event', 'read_complete')->where('occurred_at', '>=', now()->subDays(30)),
                'events as cta_30d_count' => fn ($query) => $query->where('event', 'cta_click')->where('occurred_at', '>=', now()->subDays(30)),
                'events as signups_30d_count' => fn ($query) => $query->where('event', 'signup_completed')->where('occurred_at', '>=', now()->subDays(30)),
            ])
            ->when(auth()->user()?->content_role === 'author' && ! auth()->user()?->isAdministrator(), fn ($query) => $query->where('created_by_user_id', auth()->id()))
            ->when($this->q !== '', function ($query): void {
                $query->where(function ($inner): void {
                    $inner->where('title', 'like', '%'.$this->q.'%')
                        ->orWhere('slug', 'like', '%'.$this->q.'%')
                        ->orWhere('plain_text', 'like', '%'.$this->q.'%');
                });
            })
            ->when($this->status !== 'all', fn ($query) => $query->where('status', $this->status))
            ->orderBy(in_array($this->sort, ['updated_at', 'first_published_at', 'title'], true) ? $this->sort : 'updated_at', $this->sort === 'title' ? 'asc' : 'desc')
            ->paginate(20);

        $metrics = [
            'drafts' => BlogPost::where('status', BlogPost::STATUS_DRAFT)->count(),
            'review' => BlogPost::where('status', BlogPost::STATUS_IN_REVIEW)->count(),
            'scheduled' => BlogPost::where('status', BlogPost::STATUS_SCHEDULED)->count(),
            'published' => BlogPost::published()->count(),
            'due_review' => BlogPost::published()->dueForReview()->count(),
            'views_30d' => BlogPostEvent::where('event', 'page_view')->where('occurred_at', '>=', now()->subDays(30))->count(),
            'cta_30d' => BlogPostEvent::where('event', 'cta_click')->where('occurred_at', '>=', now()->subDays(30))->count(),
            'ai_referrals_30d' => BlogPostEvent::query()
                ->where('event', 'page_view')
                ->where('occurred_at', '>=', now()->subDays(30))
                ->where(function ($query): void {
                    foreach (['chatgpt.com', 'perplexity.ai', 'claude.ai', 'copilot.microsoft.com', 'gemini.google.com'] as $source) {
                        $query->orWhere('metadata->referrer_host', 'like', '%'.$source.'%')
                            ->orWhere('metadata->utm_source', 'like', '%'.$source.'%');
                    }
                })->count(),
        ];

        return view('livewire.admin.content.posts-index', compact('posts', 'metrics'));
    }
}
