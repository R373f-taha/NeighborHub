<?php

declare(strict_types=1);

namespace Modules\Media\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Auth\app\Models\User;
use Modules\Media\Database\Factories\MediaFactory;

class Media extends Model
{
    use HasFactory;

    // public const int MAX_PER_PARENT = 5;

    // public const int MAX_POSITION = self::MAX_PER_PARENT;

    // public const int MAX_FILE_KB = 5120; // 5 MB


    // public const array ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public const MAX_PER_PARENT = 5;

    public const MAX_POSITION = self::MAX_PER_PARENT;

    public const  MAX_FILE_KB = 5120; // 5 MB


    public const  ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    protected $fillable = [
        'uploaded_by',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'disk',
        'position',
    ];

    protected static function newFactory()
    {
        return MediaFactory::new();
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
