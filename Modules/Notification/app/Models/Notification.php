<?php

namespace Modules\Notification\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Auth\Models\User;

class Notification extends Model
{
    protected $fillable = [
        'user_id', 'title', 'body', 'type', 'data', 'read_at',
        'notifiable_type', 'notifiable_id'
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function notifiable()
    {
        return $this->morphTo();
    }

    public function logs()
    {
        return $this->hasOne(NotificationLog::class);
    }
}
