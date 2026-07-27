<?php

namespace Modules\Community\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\app\Auth\Models\User as ModelsUser;
use Modules\app\Community\Models\Community as ModelsCommunity;
use Modules\Auth\app\Models\User as AppModelsUser;
use Modules\Auth\Models\User;
use Modules\Community\Models\Community;
use Modules\Interaction\Models\Comment;
use Modules\Interaction\Models\Reaction;
use Modules\Media\Models\Media;

class Announcement extends Model
{
    use SoftDeletes;

    protected $fillable = ['community_id', 'created_by_manager', 'title', 'content', 'priority', 'pinned_until'];

    protected $casts = ['pinned_until' => 'datetime'];

    // ========== العلاقات ==========

    public function community()
    {
        return $this->belongsTo(ModelsCommunity::class);
    }

    public function creator()
    {
        return $this->belongsTo(AppModelsUser::class, 'created_by_manager');
    }

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
