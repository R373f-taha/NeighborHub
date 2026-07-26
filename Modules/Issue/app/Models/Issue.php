<?php

namespace Modules\Issue\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Auth\Models\User;
use Modules\Community\Models\Community;
use Modules\Interaction\Models\Comment;
use Modules\Interaction\Models\Reaction;
use Modules\Media\Models\Media;

class Issue extends Model
{
    protected $fillable = [
        'community_id', 'title', 'description', 'category', 'location',
        'priority', 'status', 'reported_by', 'assigned_to'
    ];


    public function community()
    {
        return $this->belongsTo(Community::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function statusLogs()
    {
        return $this->hasOne(IssueStatusLog::class);
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
