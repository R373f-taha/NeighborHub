<?php

namespace Modules\Poll\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Poll\Database\Factories\PollOptionFactory;

class PollOption extends Model
{ use HasFactory;
    protected $fillable = ['poll_id', 'text'];

    public function poll()
    {
        return $this->belongsTo(Poll::class);
    }


    protected static function newFactory()
    {
        return PollOptionFactory::new();
    }

    public function votes()
    {
        return $this->hasMany(PollVote::class, 'option_id');
    }
}
