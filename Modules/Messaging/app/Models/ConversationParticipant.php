<?php

namespace Modules\Messaging\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Auth\app\Models\User;
use Modules\Messaging\Database\Factories\ConversationParticipantFactory;

class ConversationParticipant extends Model
{
    use HasFactory;

    
    protected $fillable = ['conversation_id', 'user_id', 'joined_at', 'left_at'];

    protected $casts = [
        'last_read_message_id' => 'integer',
    ];

    protected static function newFactory()
    {
        return ConversationParticipantFactory::new();
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    
    public function lastReadMessage()
    {
        return $this->belongsTo(Message::class, 'last_read_message_id');
    }
}
