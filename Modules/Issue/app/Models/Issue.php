<?php

namespace Modules\Issue\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Issue\app\Models\IssueStatusLog;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Interaction\app\Models\Comment;
use Modules\Interaction\app\Models\Reaction;
use Modules\Media\app\Models\Media;

use Modules\Issue\Database\Factories\IssueFactory;
use Modules\Issue\app\Enums\IssuePriority;
use Modules\Issue\app\Enums\IssueStatus;
use Illuminate\Database\Eloquent\SoftDeletes;

class Issue extends Model
{

    use HasFactory, SoftDeletes;


    protected $fillable = [
        'community_id',
        'category_id',
        'title',
        'description',
        'location',
        'priority',
        'status',
        'reported_by',
        'assigned_to',
    ];


    protected $casts = [

        'priority' => IssuePriority::class,

        'status' => IssueStatus::class,

    ];

    protected static function newFactory()
    {
        return IssueFactory::new();
    }

    public function community()
    {
        return $this->belongsTo(
            Community::class
        );
    }

    public function category()
    {
        return $this->belongsTo(
            IssueCategory::class
        );
    }

    public function reporter()
    {
        return $this->belongsTo(
            User::class,
            'reported_by'
        );
    }

    public function assignee()
    {
        return $this->belongsTo(
            User::class,
            'assigned_to'
        );
    }

 public function statusLogs()
{
    return $this->hasMany(
        IssueStatusLog::class
    );
}

    public function comments()
    {
        return $this->morphMany(
            Comment::class,
            'commentable'
        );
    }
    public function reactions()
    {
        return $this->morphMany(
            Reaction::class,
            'reactionable'
        );
    }

    public function media()
    {
        return $this->morphMany(
            Media::class,
            'mediable'
        );
    }


}