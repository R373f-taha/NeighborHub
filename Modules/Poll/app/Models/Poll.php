<?php

namespace Modules\Poll\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Poll\Database\Factories\PollFactory;

class Poll extends Model
{ use HasFactory;
    protected $fillable = [
        'community_id', 'created_by', 'title', 'description', 'type',
        'status', 'ends_at', 'activated_at', 'closed_at', 'closed_by_manager'
    ];

    protected $casts = [
        'ends_at' => 'datetime',
        'activated_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected static function newFactory()
    {
        return PollFactory::new();
    }
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
