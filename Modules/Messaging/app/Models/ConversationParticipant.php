<?php

namespace Modules\Messaging\app\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Auth\app\Models\User;

class ConversationParticipant extends Model
{
    protected $fillable = ['conversation_id', 'user_id', 'joined_at', 'left_at'];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
