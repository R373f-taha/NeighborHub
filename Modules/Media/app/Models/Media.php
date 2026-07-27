<?php

namespace Modules\Media\app\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Auth\Models\User;

class Media extends Model
{
    protected $fillable = [
        'mediable_type', 'mediable_id', 'uploaded_by',
        'file_path', 'file_name', 'mime_type', 'file_size', 'disk'
    ];


    public function mediable()
    {
        return $this->morphTo();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
