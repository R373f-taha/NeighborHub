<?php

namespace Modules\Community\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Modules\Community\app\Models\Announcement as AppModelsAnnouncement;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Models\Unit;
use Modules\Interaction\app\Models\Comment;
use Modules\Issue\app\Models\Issue;
use Modules\Messagingapp\app\Models\Conversation;
use Modules\Poll\app\Models\Poll;
use Modules\Post\app\Models\Post;
use Modules\ServiceListing\Models\ServiceListing;
use Modules\Auth\app\Models\User;
use Modules\Messaging\app\Models\Conversation as ModelsConversation;

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
        return $this->belongsToMany(
            User::class,
            'community_managers',
            'community_id',
            'manager_id'
        );
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
        return $this->hasMany(ModelsConversation::class);
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




     public function activeResidents()
    {
        return $this->residents()->where('status', 'active')->where('current_marker', true);
    }
    protected static function booted(): void
    {
        static::saved(function ($community) {
            static::clearCache($community->id);
        });

        static::deleted(function ($community) {
            static::clearCache($community->id);
        });
    }

    public static function clearCache(int $id): void
    {
        Cache::forget("community_stats_{$id}");
        Cache::forget("community_residents_stats_{$id}");
        Cache::forget("community_single_{$id}");
        Cache::forget('community_list');
    }
}
