<div class="min-h-screen bg-[#F7F3ED] px-4 py-6 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl space-y-6">
        <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div><p class="hc-brand-kicker">Content governance</p><h1 class="mt-2 font-display text-4xl font-semibold">Authors and taxonomy</h1><p class="mt-2 text-sm text-[#526474]">Public expertise, topic ownership, and durable information architecture.</p></div>
            <a href="{{ route('admin.content.posts.index') }}" wire:navigate class="hc-secondary-button">Back to articles</a>
        </header>

        @if(session('status'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        @if($canManageRoles)
            <section class="hc-brand-card space-y-4">
                <div><p class="hc-brand-kicker">Least privilege</p><h2 class="mt-1 font-display text-2xl font-semibold">Content team permissions</h2><p class="mt-1 text-sm text-[#526474]">Authors can draft, editors can independently review, and publishers can schedule or publish. Platform administrators retain all three capabilities.</p></div>
                <form wire:submit="assignContentRole" class="grid gap-3 md:grid-cols-[minmax(0,1fr)_220px_auto] md:items-end">
                    <label class="text-sm font-semibold">Team member<select wire:model="contentRoleUserId" class="mt-1 min-h-11 w-full rounded-xl border border-[#D8D1C7] bg-white px-3"><option value="">Choose a user</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }} · {{ $user->email }}</option>@endforeach</select></label>
                    <label class="text-sm font-semibold">Permission<select wire:model="contentRole" class="mt-1 min-h-11 w-full rounded-xl border border-[#D8D1C7] bg-white px-3"><option value="">No content access</option><option value="author">Author</option><option value="editor">Editor</option><option value="publisher">Publisher</option></select></label>
                    <button class="hc-primary-button">Update access</button>
                </form>
                <div class="flex flex-wrap gap-2">@forelse($contentTeam as $member)<span class="hc-muted-chip">{{ $member->name }} · {{ ucfirst($member->content_role) }}</span>@empty<span class="text-sm text-[#6A7784]">No dedicated content roles assigned yet.</span>@endforelse</div>
            </section>
        @endif

        <section class="grid gap-6 lg:grid-cols-2">
            <form wire:submit="saveAuthor" class="hc-brand-card space-y-3">
                <h2 class="font-display text-2xl font-semibold">{{ $authorId ? 'Edit' : 'Add' }} author or reviewer</h2>
                <div class="grid gap-3 sm:grid-cols-2"><label class="text-sm font-semibold">Name<input type="text" wire:model.blur="authorForm.name" class="mt-1 min-h-11 w-full rounded-xl border border-[#D8D1C7] px-3"></label><label class="text-sm font-semibold">Profile slug<input type="text" wire:model="authorForm.slug" class="mt-1 min-h-11 w-full rounded-xl border border-[#D8D1C7] px-3"></label></div>
                <label class="block text-sm font-semibold">Public identity type<select wire:model="authorForm.schema_type" class="mt-1 min-h-11 w-full rounded-xl border border-[#D8D1C7] px-3"><option value="Person">Person</option><option value="Organization">Organization or editorial team</option></select><span class="mt-1 block text-xs font-normal text-[#6A7784]">Choose Person for an individual such as Julie, even when the profile is not connected to a login.</span></label>
                <label class="block text-sm font-semibold">Headline<input type="text" wire:model="authorForm.headline" class="mt-1 min-h-11 w-full rounded-xl border border-[#D8D1C7] px-3"></label>
                <label class="block text-sm font-semibold">Public bio<textarea wire:model="authorForm.bio" rows="4" class="mt-1 w-full rounded-xl border border-[#D8D1C7] px-3 py-2"></textarea></label>
                <label class="block text-sm font-semibold">Credentials and review scope<textarea wire:model="authorForm.credentials" rows="2" class="mt-1 w-full rounded-xl border border-[#D8D1C7] px-3 py-2"></textarea></label>
                <label class="block text-sm font-semibold">Connected content-team user<select wire:model="authorForm.user_id" class="mt-1 min-h-11 w-full rounded-xl border border-[#D8D1C7] px-3"><option value="">Public profile only</option>@foreach($authorUsers as $user)<option value="{{ $user->id }}">{{ $user->name }} · {{ $user->content_role ?: $user->role }}</option>@endforeach</select></label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm font-semibold">Profile photo<select wire:model="authorForm.avatar_media_asset_id" class="mt-1 min-h-11 w-full rounded-xl border border-[#D8D1C7] px-3"><option value="">No profile photo</option>@foreach($authorMedia as $asset)<option value="{{ $asset->id }}">{{ $asset->original_filename }}</option>@endforeach</select></label>
                    <label class="block text-sm font-semibold">Professional profile URL<input type="url" wire:model="authorForm.profile_url" class="mt-1 min-h-11 w-full rounded-xl border border-[#D8D1C7] px-3" placeholder="https://…"></label>
                </div>
                <label class="block text-sm font-semibold">Identity URLs, one per line<textarea wire:model="authorForm.same_as" rows="3" class="mt-1 w-full rounded-xl border border-[#D8D1C7] px-3 py-2" placeholder="LinkedIn, professional profile, organization page…"></textarea></label>
                <label class="flex gap-2 text-sm"><input type="checkbox" wire:model="authorForm.is_active" class="rounded border-[#B9B0A5] text-[#0F5B52]">Available for new assignments</label>
                <div class="flex gap-2"><button class="hc-primary-button">Save author</button>@if($authorId)<button type="button" wire:click="resetAuthor" class="hc-secondary-button">Cancel</button>@endif</div>
            </form>
            <div class="hc-brand-card space-y-3"><h2 class="font-display text-2xl font-semibold">Public profiles</h2>@foreach($authors as $author)<article class="rounded-xl border border-[#E5DED5] p-3"><div class="flex justify-between gap-3"><div><p class="font-semibold">{{ $author->name }}</p><p class="text-xs text-[#526474]">{{ $author->headline }} · /blog/authors/{{ $author->slug }}</p><p class="mt-1 text-xs">{{ $author->credentials }}</p></div><button type="button" wire:click="editAuthor({{ $author->id }})" class="text-sm font-semibold text-[#0F5B52] underline">Edit</button></div></article>@endforeach</div>
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <div class="hc-brand-card space-y-4">
                <form wire:submit="saveCategory" class="space-y-3">
                    <h2 class="font-display text-2xl font-semibold">{{ $categoryId ? 'Edit' : 'Add' }} category</h2>
                    <div class="grid gap-3 sm:grid-cols-2"><input type="text" wire:model.blur="categoryForm.name" placeholder="Category name" class="min-h-11 rounded-xl border border-[#D8D1C7] px-3"><input type="text" wire:model="categoryForm.slug" placeholder="category-slug" class="min-h-11 rounded-xl border border-[#D8D1C7] px-3"></div>
                    <textarea wire:model="categoryForm.description" rows="3" placeholder="Useful category introduction" class="w-full rounded-xl border border-[#D8D1C7] px-3 py-2"></textarea><input type="text" wire:model="categoryForm.seo_title" placeholder="SEO title" class="min-h-11 w-full rounded-xl border border-[#D8D1C7] px-3"><textarea wire:model="categoryForm.meta_description" rows="2" placeholder="Meta description" class="w-full rounded-xl border border-[#D8D1C7] px-3 py-2"></textarea>
                    <div class="flex items-center gap-3"><input type="number" wire:model="categoryForm.sort_order" class="min-h-11 w-24 rounded-xl border border-[#D8D1C7] px-3"><label class="flex gap-2 text-sm"><input type="checkbox" wire:model="categoryForm.is_active" class="rounded border-[#B9B0A5] text-[#0F5B52]">Active</label><button class="hc-primary-button">Save category</button>@if($categoryId)<button type="button" wire:click="resetCategory" class="hc-secondary-button">Cancel</button>@endif</div>
                </form>
                @if($categoryId)<div class="rounded-xl border border-amber-200 bg-amber-50 p-3"><p class="text-sm font-semibold">Merge this category</p><div class="mt-2 flex gap-2"><select wire:model="categoryMergeTargetId" class="min-h-10 flex-1 rounded-lg border border-amber-300 px-2 text-sm"><option value="">Destination category</option>@foreach($categories->whereNull('merged_into_id')->where('id','!=',$categoryId) as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select><button type="button" wire:click="mergeCategory" wire:confirm="Merge this category into the selected destination?" class="text-sm font-semibold text-amber-900 underline">Merge</button></div></div>@endif
                <div class="space-y-2 border-t border-[#E5DED5] pt-4">@foreach($categories as $category)<div class="flex justify-between rounded-xl border border-[#E5DED5] p-3"><div><p class="font-semibold">{{ $category->name }}</p><p class="text-xs text-[#526474]">/blog/category/{{ $category->slug }} @if($category->merged_into_id) · merged @endif</p></div><button type="button" wire:click="editCategory({{ $category->id }})" class="text-sm font-semibold text-[#0F5B52] underline">Edit</button></div>@endforeach</div>
            </div>

            <div class="hc-brand-card space-y-4">
                <form wire:submit="saveTag" class="space-y-3"><h2 class="font-display text-2xl font-semibold">{{ $tagId ? 'Edit' : 'Add' }} tag</h2><div class="grid gap-3 sm:grid-cols-2"><input type="text" wire:model.blur="tagForm.name" placeholder="Tag name" class="min-h-11 rounded-xl border border-[#D8D1C7] px-3"><input type="text" wire:model="tagForm.slug" placeholder="tag-slug" class="min-h-11 rounded-xl border border-[#D8D1C7] px-3"></div><textarea wire:model="tagForm.description" rows="3" placeholder="Optional tag description" class="w-full rounded-xl border border-[#D8D1C7] px-3 py-2"></textarea><div class="flex gap-2"><button class="hc-primary-button">Save tag</button>@if($tagId)<button type="button" wire:click="resetTag" class="hc-secondary-button">Cancel</button>@endif</div></form>
                @if($tagId)<div class="rounded-xl border border-amber-200 bg-amber-50 p-3"><p class="text-sm font-semibold">Merge this tag</p><div class="mt-2 flex gap-2"><select wire:model="tagMergeTargetId" class="min-h-10 flex-1 rounded-lg border border-amber-300 px-2 text-sm"><option value="">Destination tag</option>@foreach($tags->whereNull('merged_into_id')->where('id','!=',$tagId) as $tag)<option value="{{ $tag->id }}">{{ $tag->name }}</option>@endforeach</select><button type="button" wire:click="mergeTag" wire:confirm="Merge this tag into the selected destination?" class="text-sm font-semibold text-amber-900 underline">Merge</button></div></div>@endif
                <div class="flex flex-wrap gap-2 border-t border-[#E5DED5] pt-4">@foreach($tags as $tag)<button type="button" wire:click="editTag({{ $tag->id }})" class="rounded-full border border-[#D8D1C7] px-3 py-1.5 text-sm hover:bg-[#F5F2ED]">{{ $tag->name }} @if($tag->merged_into_id) · merged @endif</button>@endforeach</div>
            </div>
        </section>
    </div>
</div>
