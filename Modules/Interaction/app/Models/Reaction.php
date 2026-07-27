<?php

namespace Modules\Interaction\app\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Auth\Models\User;

class Reaction extends Model
{
    protected $fillable = ['reactionable_type', 'reactionable_id', 'user_id', 'type'];


    public function reactionable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
