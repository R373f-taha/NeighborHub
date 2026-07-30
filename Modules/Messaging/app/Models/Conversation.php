<?php

namespace Modules\Messaging\app\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;

class Conversation extends Model
{
    protected $fillable = ['community_id', 'type', 'status'];

    public function community()
    {
        return $this->belongsTo(Community::class);
    }

    public function participants()
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'conversation_participants', 'conversation_id', 'user_id')
                    ->withPivot('joined_at', 'left_at')
                    ->withTimestamps();
    }
}
