<?php

declare(strict_types=1);

namespace Modules\Community\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Auth\app\Models\User;
use Modules\Interaction\app\Models\Comment;
use Modules\Interaction\app\Models\Reaction;
use Modules\Media\app\Models\Media;
use  \Modules\Community\Database\Factories\AnnouncementFactory;
class Announcement extends Model
{

    use SoftDeletes, HasFactory;


    protected $fillable = [
        'community_id',
        'created_by_manager',
        'title',
        'content',
        'priority',
        'pinned_until'
    ];


    protected $casts = [
        'pinned_until'=>'datetime'
    ];



    protected static function newFactory()
    {
        return AnnouncementFactory::new();
    }



    public function community()
    {
        return $this->belongsTo(Community::class);
    }



    public function creator()
    {
        return $this->belongsTo(
            User::class,
            'created_by_manager'
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

    public function getRouteKeyName(): string
{
    return 'id';
}
}