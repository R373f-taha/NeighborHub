<?php

namespace Modules\Poll\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Community\app\Models\Resident;
use Modules\Poll\Database\Factories\PollVoteFactory;

class PollVote extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'poll_id',
        'option_id',
        'voter_id',
        'submitted_at',
        'voted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'voted_at' => 'datetime',
    ];

    protected static function newFactory()
    {
        return PollVoteFactory::new();
    }

    public function poll()
    {
        return $this->belongsTo(Poll::class);
    }

    public function option()
    {
        return $this->belongsTo(PollOption::class, 'option_id');
    }

    public function voter()
    {
        return $this->belongsTo(Resident::class, 'voter_id');
    }
}