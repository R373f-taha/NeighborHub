<?php

namespace Modules\Issue\app\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Interaction\app\Models\Comment;
use Modules\Interaction\app\Models\Reaction;
use Modules\Media\app\Models\Media;
use Modules\Issue\Database\Factories\IssueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Issue extends Model
{ use HasFactory;
    protected $fillable = [
        'community_id', 'title', 'description', 'category', 'location',
        'priority', 'status', 'reported_by', 'assigned_to'
    ];


    protected static function newFactory()
    {
        return IssueFactory::new();
    }
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
        return $this->hasMany
        (IssueStatusLog::class);
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
