<?php

namespace Modules\Messaging\app\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Messaging\Database\Factories\ConversationFactory;

class Conversation extends Model
{  use HasFactory;
    protected $fillable = ['community_id', 'type', 'status'];


    protected static function newFactory()
    {
        return ConversationFactory::new();
    }


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
