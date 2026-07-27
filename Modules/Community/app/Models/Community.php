<?php

namespace Modules\Community\app\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Community\app\Models\Announcement as AppModelsAnnouncement;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Models\Unit;
use Modules\Interaction\app\Models\Comment;
use Modules\Issue\app\Models\Issue;
use Modules\Messaging\app\Models\Conversation;
use Modules\Poll\app\Models\Poll;
use Modules\Post\app\Models\Post;
use Modules\ServiceListing\app\Models\ServiceListing;
use Modules\Auth\app\Models\User;

class Community extends Model
{  use HasFactory;
    protected $fillable = ['name', 'city', 'address', 'total_units', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function newFactory()
{
    return \Modules\Community\Database\Factories\CommunityFactory::new();
}

    public function units()
    {
        return $this->hasMany(Unit::class);
    }

 public function communityManagers()
{
    return $this->hasMany(CommunityManager::class);
}

public function managers()
{
    return $this->belongsToMany( User::class, 'community_mangers', 'community_id',  'manager_id');
}

    public function residents()
{
    return $this->hasManyThrough(Resident::class,Unit::class);
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
