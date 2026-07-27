<?php

namespace Modules\Messaging\app\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Messaging\Database\Factories\MessageFactory;

class Message extends Model
{ use HasFactory;
    protected $fillable = ['conversation_id', 'sender_id', 'content', 'is_read', 'read_at', 'community_id'];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

   protected static function newFactory()
    {
        return MessageFactory::new();
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function community()
    {
        return $this->belongsTo(Community::class);
    }
}
