<?php

namespace Modules\Interaction\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Auth\app\Models\User;
use Modules\Interaction\Database\Factories\CommentFactory;

class Comment extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = ['commentable_type', 'commentable_id', 'author_id', 'parent_id', 'content'];

    protected static function newFactory()
    {
        return CommentFactory::new();
    }

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }
}
