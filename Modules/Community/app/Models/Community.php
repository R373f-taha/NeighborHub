<?php

namespace Modules\Community\app\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Announcement\app\Models\Announcement as AppModelsAnnouncement;
use Modules\Announcement\Models\Announcement;
use Modules\Community\Models\Announcement as ModelsAnnouncement;
use Modules\Community\Models\Resident;
use Modules\Community\Models\Unit;
use Modules\Interaction\Models\Comment;
use Modules\Issue\Models\Issue;
use Modules\Messaging\Models\Conversation;
use Modules\Poll\Models\Poll;
use Modules\Post\Models\Post;
use Modules\ServiceListing\Models\ServiceListing;

class Community extends Model
{
    protected $fillable = ['name', 'city', 'address', 'total_units', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];


    public function units()
    {
        return $this->hasMany(Unit::class);
    }

    public function managers()
    {
        return $this->belongsToMany(CommunityManager::class);
    }

    public function residents()
    {
        return $this->hasMany(Resident::class);
    }

    public function announcements()
    {
        return $this->hasMany(AppModelsAnnouncement::class);
    }

    public function issues()
    {
        return $this->hasMany(Issue::class);
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    public function polls()
    {
        return $this->hasMany(Poll::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function serviceListings()
    {
        return $this->hasMany(ServiceListing::class);
    }
    public function comments(){
        return $this->hasMany(Comment::class);
    }
}
