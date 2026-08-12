<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogPostRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['blog_post_id', 'revision_number', 'snapshot', 'actor_user_id', 'change_summary'];

    protected $casts = ['snapshot' => 'array', 'revision_number' => 'integer', 'created_at' => 'datetime'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
