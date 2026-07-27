<?php

namespace Modules\Messaging\app\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Auth\Models\User;
use Modules\Community\Models\Community;

class Message extends Model
{
    protected $fillable = ['conversation_id', 'sender_id', 'content', 'is_read', 'read_at', 'community_id'];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

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
