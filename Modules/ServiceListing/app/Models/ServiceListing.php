<?php

namespace Modules\ServiceListing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Community\Models\Community;
use Modules\Community\Models\Resident;
use Modules\Interaction\Models\Comment;
use Modules\Interaction\Models\Reaction;
use Modules\Media\Models\Media;

class ServiceListing extends Model
{
    use SoftDeletes;

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
}
