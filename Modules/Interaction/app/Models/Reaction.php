<?php

namespace Modules\Interaction\app\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Auth\app\Models\User;
use Modules\Interaction\app\Enums\ReactionType;
use Modules\Interaction\Database\Factories\ReactionFactory;

class Reaction extends Model
{
    use HasFactory;

    // user_id is set server-side from the authenticated user by the reaction
    // actions (never from client input); it must be mass-assignable for them.
    protected $fillable = ['type', 'user_id'];

    protected $casts = [
        'type' => ReactionType::class,
    ];

    protected static function newFactory()
    {
        return ReactionFactory::new();
    }

    public function reactionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
