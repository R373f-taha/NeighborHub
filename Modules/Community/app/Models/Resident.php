<?php

namespace Modules\Community\app\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Auth\app\Models\User as ModelsUser;
use Modules\Auth\Models\User;
use Modules\Community\Models\Unit;
use Modules\Poll\Models\PollVote;
use Modules\Post\Models\Post;
use Modules\ServiceListing\Models\ServiceListing;

class Resident extends Model
{
    protected $fillable = [
        'user_id', 'unit_id', 'residence_type', 'status',
        'joined_at', 'left_at', 'current_marker', 'approved_by'
    ];

    protected $casts = [
        'current_marker' => 'boolean',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];


    public function user()
    {
        return $this->belongsTo(ModelsUser::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(ModelsUser::class, 'approved_by');
    }

    public function posts()
    {
        return $this->hasMany(Post::class, 'resident_id');
    }

    public function serviceListings()
    {
        return $this->hasMany(ServiceListing::class, 'resident_id');
    }

    public function pollVotes()
    {
        return $this->hasMany(PollVote::class, 'voter_id');
    }
}
