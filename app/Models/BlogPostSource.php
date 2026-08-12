<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BlogPostSource extends Model
{
    protected $fillable = ['blog_post_id', 'uuid', 'position', 'title', 'publisher', 'url', 'published_on', 'accessed_on', 'notes'];

    protected $casts = ['position' => 'integer', 'published_on' => 'date', 'accessed_on' => 'date'];

    protected static function booted(): void
    {
        static::creating(function (BlogPostSource $source): void {
            $source->uuid ??= (string) Str::uuid();
        });
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }
}
