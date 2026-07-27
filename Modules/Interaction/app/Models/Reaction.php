<?php

namespace Modules\Interaction\app\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Auth\app\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Interaction\Database\Factories\ReactionFactory;

class Reaction extends Model
{ use HasFactory;
    protected $fillable = ['reactionable_type', 'reactionable_id', 'user_id', 'type'];



    protected static function newFactory()
    {
        return ReactionFactory::new();
    }
    public function reactionable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
