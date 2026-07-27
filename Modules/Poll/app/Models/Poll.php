<?php

namespace Modules\Poll\app\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Auth\Models\User;
use Modules\Community\Models\Community;

class Poll extends Model
{
    protected $fillable = [
        'community_id', 'created_by', 'title', 'description', 'type',
        'status', 'ends_at', 'activated_at', 'closed_at', 'closed_by_manager'
    ];

    protected $casts = [
        'ends_at' => 'datetime',
        'activated_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function community()
    {
        return $this->belongsTo(Community::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by_manager');
    }

    public function options()
    {
        return $this->hasMany(PollOption::class);
    }

    public function votes()
    {
        return $this->hasMany(PollVote::class);
    }
}
