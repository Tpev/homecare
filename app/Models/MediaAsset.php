<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class MediaAsset extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'disk', 'path', 'original_filename', 'mime_type', 'size_bytes', 'width', 'height',
        'alt_text', 'caption', 'credit', 'license', 'source_url', 'focal_x', 'focal_y',
        'metadata', 'uploaded_by_user_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'size_bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'focal_x' => 'decimal:4',
        'focal_y' => 'decimal:4',
    ];

    protected $appends = ['url'];

    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function blogPosts(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'featured_media_asset_id');
    }

    public function authorProfiles(): HasMany
    {
        return $this->hasMany(ContentAuthor::class, 'avatar_media_asset_id');
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => Storage::disk($this->disk)->url($this->path));
    }

    public function variantUrl(string $variant): string
    {
        $item = $this->relationLoaded('variants')
            ? $this->variants->firstWhere('variant', $variant)
            : $this->variants()->where('variant', $variant)->first();

        return $item ? Storage::disk($item->disk)->url($item->path) : $this->url;
    }
}
