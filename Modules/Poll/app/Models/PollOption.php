<?php

namespace Modules\Poll\Models;

use Illuminate\Database\Eloquent\Model;

class PollOption extends Model
{
    protected $fillable = ['poll_id', 'text'];

    public function poll()
    {
        return $this->belongsTo(Poll::class);
    }

    public function votes()
    {
        return $this->hasMany(PollVote::class, 'option_id');
    }
}
