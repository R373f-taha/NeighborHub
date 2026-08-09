<?php

namespace Modules\Community\app\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Unit;
use Modules\Poll\Models\PollVote;
use Modules\Post\app\Models\Post;
use Modules\ServiceListing\Models\ServiceListing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Poll\app\Models\PollVote as ModelsPollVote;
use Modules\ServiceListing\app\Models\ServiceListing as ModelsServiceListing;
use  Modules\Community\Database\Factories\ResidentFactory;
use Modules\Community\app\Models\Community;
class Resident extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'unit_id', 'residence_type', 'status',
        'joined_at', 'left_at', 'current_marker', 'approved_by'
        ,'community_id'
    ];

    protected $casts = [
        'current_marker' => 'boolean',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];


    protected static function newFactory()
    {
        return ResidentFactory::new();
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }


    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }


    public function posts()
    {
        return $this->hasMany(Post::class, 'resident_id');
    }


    public function serviceListings()
    {
        return $this->hasMany(ModelsServiceListing::class, 'resident_id');
    }
    public function community(){
        return $this->belongsTo(Community::class, 'community_id');
    }


    public function pollVotes()
    {
        return $this->hasMany(ModelsPollVote::class, 'voter_id');
    }
}
