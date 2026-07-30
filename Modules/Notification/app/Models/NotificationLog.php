<?php

namespace Modules\Notification\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Notification\Database\Factories\NotificationLogFactory;

class NotificationLog extends Model
{  use HasFactory;
    protected $table = 'notification_log';

    protected $fillable = ['notification_id', 'channel', 'status', 'sent_at'];

       protected static function newFactory()
    {
        return NotificationLogFactory::new();
    }
    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }
}
