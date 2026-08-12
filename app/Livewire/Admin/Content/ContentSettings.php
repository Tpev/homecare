<?php

namespace App\Livewire\Admin\Content;

use App\Models\ContentAuthor;
use App\Models\ContentCategory;
use App\Models\ContentTag;
use App\Models\MediaAsset;
use App\Models\UrlRedirect;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ContentSettings extends Component
{
    public ?int $authorId = null;

    public array $authorForm = ['name' => '', 'schema_type' => ContentAuthor::SCHEMA_PERSON, 'slug' => '', 'headline' => '', 'bio' => '', 'credentials' => '', 'user_id' => '', 'avatar_media_asset_id' => '', 'profile_url' => '', 'same_as' => '', 'is_active' => true];

    public ?int $categoryId = null;

    public array $categoryForm = ['name' => '', 'slug' => '', 'description' => '', 'seo_title' => '', 'meta_description' => '', 'sort_order' => 0, 'is_active' => true];

    public string $categoryMergeTargetId = '';

    public ?int $tagId = null;

    public array $tagForm = ['name' => '', 'slug' => '', 'description' => ''];

    public string $tagMergeTargetId = '';

    public string $contentRoleUserId = '';

    public string $contentRole = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->canPublishContent(), 403);
    }

    public function updatedAuthorFormName(string $value): void
    {
        if (! $this->authorId || $this->authorForm['slug'] === '') {
            $this->authorForm['slug'] = Str::slug($value);
        }
    }

    public function updatedCategoryFormName(string $value): void
    {
        if (! $this->categoryId || $this->categoryForm['slug'] === '') {
            $this->categoryForm['slug'] = Str::slug($value);
        }
    }

    public function updatedTagFormName(string $value): void
    {
        if (! $this->tagId || $this->tagForm['slug'] === '') {
            $this->tagForm['slug'] = Str::slug($value);
        }
    }

    public function saveAuthor(): void
    {
        $data = $this->validate([
            'authorForm.name' => ['required', 'string', 'max:255'],
            'authorForm.schema_type' => ['required', Rule::in(array_keys(ContentAuthor::SCHEMA_TYPES))],
            'authorForm.slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('content_authors', 'slug')->ignore($this->authorId)],
            'authorForm.headline' => ['nullable', 'string', 'max:255'],
            'authorForm.bio' => ['required', 'string', 'min:60', 'max:5000'],
            'authorForm.credentials' => ['nullable', 'string', 'max:1000'],
            'authorForm.user_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'admin')->orWhereNotNull('content_role')),
                Rule::unique('content_authors', 'user_id')->ignore($this->authorId),
            ],
            'authorForm.avatar_media_asset_id' => ['nullable', 'integer', 'exists:media_assets,id'],
            'authorForm.profile_url' => ['nullable', 'url:http,https', 'max:2048'],
            'authorForm.same_as' => ['nullable', 'string', 'max:4000'],
            'authorForm.is_active' => ['boolean'],
        ])['authorForm'];
        $data['user_id'] = $data['user_id'] ?: null;
        $data['avatar_media_asset_id'] = $data['avatar_media_asset_id'] ?: null;
        $data['profile_url'] = $data['profile_url'] ?: null;
        $sameAs = collect(preg_split('/\r?\n/', (string) $data['same_as']))
            ->map(fn (string $url): string => trim($url))
            ->filter()
            ->values();
        if ($sameAs->contains(fn (string $url): bool => filter_var($url, FILTER_VALIDATE_URL) === false
            || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true))) {
            throw ValidationException::withMessages(['authorForm.same_as' => 'Every identity reference must be a complete HTTP or HTTPS URL.']);
        }
        $data['same_as'] = $sameAs->all();
        $author = $this->authorId ? ContentAuthor::query()->findOrFail($this->authorId) : null;
        $oldSlug = $author?->slug;
        $author = ContentAuthor::query()->updateOrCreate(['id' => $this->authorId], $data);
        $this->redirectChangedSlug($oldSlug, $author->slug, '/blog/authors/');
        $this->resetAuthor();
        session()->flash('status', 'Author profile saved.');
    }

    public function editAuthor(int $id): void
    {
        $author = ContentAuthor::query()->findOrFail($id);
        $this->authorId = $author->id;
        $this->authorForm = [
            'name' => $author->name, 'schema_type' => $author->schema_type, 'slug' => $author->slug, 'headline' => $author->headline ?? '',
            'bio' => $author->bio ?? '', 'credentials' => $author->credentials ?? '',
            'user_id' => $author->user_id ?? '', 'same_as' => implode("\n", (array) $author->same_as),
            'avatar_media_asset_id' => $author->avatar_media_asset_id ?? '', 'profile_url' => $author->profile_url ?? '',
            'is_active' => $author->is_active,
        ];
    }

    public function resetAuthor(): void
    {
        $this->authorId = null;
        $this->authorForm = ['name' => '', 'schema_type' => ContentAuthor::SCHEMA_PERSON, 'slug' => '', 'headline' => '', 'bio' => '', 'credentials' => '', 'user_id' => '', 'avatar_media_asset_id' => '', 'profile_url' => '', 'same_as' => '', 'is_active' => true];
    }

    public function saveCategory(): void
    {
        $data = $this->validate([
            'categoryForm.name' => ['required', 'string', 'max:255'],
            'categoryForm.slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('content_categories', 'slug')->ignore($this->categoryId)],
            'categoryForm.description' => ['required', 'string', 'min:40', 'max:5000'],
            'categoryForm.seo_title' => ['nullable', 'string', 'max:220'],
            'categoryForm.meta_description' => ['nullable', 'string', 'max:320'],
            'categoryForm.sort_order' => ['integer', 'min:0', 'max:9999'],
            'categoryForm.is_active' => ['boolean'],
        ])['categoryForm'];
        $category = $this->categoryId ? ContentCategory::query()->findOrFail($this->categoryId) : null;
        $oldSlug = $category?->slug;
        $category = ContentCategory::query()->updateOrCreate(['id' => $this->categoryId], $data);
        $this->redirectChangedSlug($oldSlug, $category->slug, '/blog/category/');
        Cache::forget('content.public-category-counts');
        $this->resetCategory();
        session()->flash('status', 'Category saved.');
    }

    public function editCategory(int $id): void
    {
        $item = ContentCategory::query()->findOrFail($id);
        $this->categoryId = $item->id;
        $this->categoryForm = $item->only(['name', 'slug', 'description', 'seo_title', 'meta_description', 'sort_order', 'is_active']);
    }

    public function resetCategory(): void
    {
        $this->categoryId = null;
        $this->categoryMergeTargetId = '';
        $this->categoryForm = ['name' => '', 'slug' => '', 'description' => '', 'seo_title' => '', 'meta_description' => '', 'sort_order' => 0, 'is_active' => true];
    }

    public function saveTag(): void
    {
        $data = $this->validate([
            'tagForm.name' => ['required', 'string', 'max:255'],
            'tagForm.slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('content_tags', 'slug')->ignore($this->tagId)],
            'tagForm.description' => ['nullable', 'string', 'max:5000'],
        ])['tagForm'];
        $tag = $this->tagId ? ContentTag::query()->findOrFail($this->tagId) : null;
        $oldSlug = $tag?->slug;
        $tag = ContentTag::query()->updateOrCreate(['id' => $this->tagId], $data);
        $this->redirectChangedSlug($oldSlug, $tag->slug, '/blog/topic/');
        $this->resetTag();
        session()->flash('status', 'Tag saved.');
    }

    public function editTag(int $id): void
    {
        $item = ContentTag::query()->findOrFail($id);
        $this->tagId = $item->id;
        $this->tagForm = $item->only(['name', 'slug', 'description']);
    }

    public function resetTag(): void
    {
        $this->tagId = null;
        $this->tagMergeTargetId = '';
        $this->tagForm = ['name' => '', 'slug' => '', 'description' => ''];
    }

    public function mergeCategory(): void
    {
        abort_unless(auth()->user()?->canPublishContent(), 403);
        $data = $this->validate([
            'categoryId' => ['required', 'integer', 'exists:content_categories,id'],
            'categoryMergeTargetId' => ['required', 'integer', 'different:categoryId', 'exists:content_categories,id'],
        ]);

        DB::transaction(function () use ($data): void {
            $source = ContentCategory::query()->lockForUpdate()->findOrFail($data['categoryId']);
            $target = ContentCategory::query()->lockForUpdate()->findOrFail($data['categoryMergeTargetId']);
            if ($source->merged_into_id) {
                throw ValidationException::withMessages(['categoryId' => 'This category has already been merged.']);
            }
            if ($target->merged_into_id) {
                throw ValidationException::withMessages(['categoryMergeTargetId' => 'Choose a category that has not already been merged.']);
            }
            foreach ($source->posts()->pluck('blog_posts.id') as $postId) {
                DB::table('blog_post_category')->insertOrIgnore(['blog_post_id' => $postId, 'content_category_id' => $target->id]);
            }
            $source->posts()->detach();
            ContentCategory::query()->where('merged_into_id', $source->id)->update(['merged_into_id' => $target->id]);
            UrlRedirect::query()
                ->where('destination_path', '/blog/category/'.$source->slug)
                ->update(['destination_path' => '/blog/category/'.$target->slug]);
            $source->update(['is_active' => false, 'merged_into_id' => $target->id]);
            $this->redirectChangedSlug($source->slug, $target->slug, '/blog/category/');
        });

        Cache::forget('content.public-category-counts');
        $this->resetCategory();
        session()->flash('status', 'Category merged. Historical public revisions now resolve to the destination category.');
    }

    public function mergeTag(): void
    {
        abort_unless(auth()->user()?->canPublishContent(), 403);
        $data = $this->validate([
            'tagId' => ['required', 'integer', 'exists:content_tags,id'],
            'tagMergeTargetId' => ['required', 'integer', 'different:tagId', 'exists:content_tags,id'],
        ]);

        DB::transaction(function () use ($data): void {
            $source = ContentTag::query()->lockForUpdate()->findOrFail($data['tagId']);
            $target = ContentTag::query()->lockForUpdate()->findOrFail($data['tagMergeTargetId']);
            if ($source->merged_into_id) {
                throw ValidationException::withMessages(['tagId' => 'This tag has already been merged.']);
            }
            if ($target->merged_into_id) {
                throw ValidationException::withMessages(['tagMergeTargetId' => 'Choose a tag that has not already been merged.']);
            }
            foreach ($source->posts()->pluck('blog_posts.id') as $postId) {
                DB::table('blog_post_tag')->insertOrIgnore(['blog_post_id' => $postId, 'content_tag_id' => $target->id]);
            }
            $source->posts()->detach();
            ContentTag::query()->where('merged_into_id', $source->id)->update(['merged_into_id' => $target->id]);
            UrlRedirect::query()
                ->where('destination_path', '/blog/topic/'.$source->slug)
                ->update(['destination_path' => '/blog/topic/'.$target->slug]);
            $source->update(['merged_into_id' => $target->id]);
            $this->redirectChangedSlug($source->slug, $target->slug, '/blog/topic/');
        });

        $this->resetTag();
        session()->flash('status', 'Tag merged. Historical public revisions now resolve to the destination topic.');
    }

    public function assignContentRole(): void
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);

        $data = $this->validate([
            'contentRoleUserId' => ['required', 'integer', 'exists:users,id'],
            'contentRole' => ['nullable', Rule::in(['author', 'editor', 'publisher'])],
        ]);

        User::query()->findOrFail((int) $data['contentRoleUserId'])->update([
            'content_role' => $data['contentRole'] !== '' ? $data['contentRole'] : null,
        ]);

        $this->contentRoleUserId = '';
        $this->contentRole = '';
        session()->flash('status', 'Content team permissions updated.');
    }

    public function render(): View
    {
        $canManageRoles = auth()->user()?->isAdministrator() === true;

        return view('livewire.admin.content.content-settings', [
            'authors' => ContentAuthor::with('user')->orderBy('name')->get(),
            'categories' => ContentCategory::orderBy('sort_order')->orderBy('name')->get(),
            'tags' => ContentTag::orderBy('name')->get(),
            'users' => $canManageRoles ? User::query()->orderBy('name')->limit(500)->get() : collect(),
            'authorUsers' => User::query()
                ->where(fn ($query) => $query->where('role', 'admin')->orWhereNotNull('content_role'))
                ->orderBy('name')->get(),
            'authorMedia' => MediaAsset::query()->where('mime_type', 'like', 'image/%')->latest()->limit(100)->get(),
            'contentTeam' => User::query()->whereNotNull('content_role')->orderBy('name')->get(),
            'canManageRoles' => $canManageRoles,
        ]);
    }

    private function redirectChangedSlug(?string $oldSlug, string $newSlug, string $prefix): void
    {
        if (! $oldSlug || $oldSlug === $newSlug) {
            return;
        }

        UrlRedirect::query()->updateOrCreate(['source_path' => $prefix.$oldSlug], [
            'destination_path' => $prefix.$newSlug,
            'status_code' => 301,
            'is_active' => true,
            'created_by_user_id' => auth()->id(),
        ]);
    }
}
