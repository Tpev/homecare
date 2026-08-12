<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\RedirectResponse;

class BlogCoverController extends Controller
{
    public function __invoke(string $blogSlug): RedirectResponse
    {
        $post = BlogPost::published()
            ->with('publishedRevision')
            ->whereHas('publishedRevision', fn ($query) => $query->where('snapshot->slug', $blogSlug))
            ->firstOrFail();
        $assetId = data_get($post->publishedRevision?->snapshot, 'featured_media_asset_id');
        $asset = \App\Models\MediaAsset::query()->findOrFail($assetId);

        return redirect()->to($asset->variantUrl('large'), 302, ['Cache-Control' => 'public, max-age=86400']);
    }
}
