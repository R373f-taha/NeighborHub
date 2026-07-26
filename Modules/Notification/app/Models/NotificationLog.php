<?php

namespace Modules\Notification\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $table = 'notification_log';

    protected $fillable = ['notification_id', 'channel', 'status', 'sent_at'];

    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }
}
