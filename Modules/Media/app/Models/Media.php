<?php

namespace Modules\Media\app\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Auth\app\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Media\Database\Factories\MediaFactory;
class Media extends Model
{   use HasFactory;
    protected $fillable = [
        'mediable_type', 'mediable_id', 'uploaded_by',
        'file_path', 'file_name', 'mime_type', 'file_size', 'disk'
    ];

  protected static function newFactory()
    {
        return MediaFactory::new();
    }

    public function mediable()
    {
        return $this->morphTo();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
