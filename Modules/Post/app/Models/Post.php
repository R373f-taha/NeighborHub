<?php

namespace Modules\Post\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\Models\User;
use Modules\Community\Models\Community;
use Modules\Community\Models\Resident;
use Modules\Interaction\Models\Comment;
use Modules\Interaction\Models\Reaction;
use Modules\Media\Models\Media;

class Post extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'community_id', 'resident_id', 'category', 'content',
        'is_pinned', 'pinned_by'
    ];

    protected $casts = [
        'is_pinned' => 'datetime',
    ];


    public function community()
    {
        return $this->belongsTo(Community::class);
    }

    public function author()
    {
        return $this->belongsTo(Resident::class, 'resident_id');
    }

    public function pinnedBy()
    {
        return $this->belongsTo(User::class, 'pinned_by');
    }

    // Polymorphic
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function reactions()
    {
        return $this->morphMany(Reaction::class, 'reactionable');
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
