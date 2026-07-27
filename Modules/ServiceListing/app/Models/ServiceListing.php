<?php

namespace Modules\ServiceListing\app\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
use Modules\Interaction\app\Models\Comment;
use Modules\Interaction\app\Models\Reaction;
use Modules\Media\app\Models\Media;

class ServiceListing extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'community_id', 'resident_id', 'title', 'description', 'type',
        'price', 'status', 'expires_at', 'closed_at'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'expires_at' => 'datetime',
        'closed_at' => 'datetime',
    ];


    public function community()
    {
        return $this->belongsTo(Community::class);
    }


    public function author()
    {
        return $this->belongsTo(Resident::class, 'resident_id');
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

      protected static function newFactory()
    {
        return \Modules\ServiceListing\Database\Factories\ServiceListingFactory::new();
    }
}
