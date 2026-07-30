<?php

namespace Modules\Post\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
use Modules\Interaction\app\Models\Comment;
use Modules\Interaction\app\Models\Reaction;
use Modules\Media\app\Models\Media;

class Post extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'community_id',
        'resident_id',
        'category',
        'content',
    ];

    protected $casts = [
        'is_pinned' => 'datetime',
    ];

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'resident_id');
    }

    public function pinnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pinned_by');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactionable');
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    protected static function newFactory()
    {
        return \Modules\Post\Database\Factories\PostFactory::new();
    }
}
