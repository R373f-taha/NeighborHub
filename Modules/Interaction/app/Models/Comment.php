<?php

namespace Modules\Interaction\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\app\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Interaction\Database\Factories\CommentFactory;


class Comment extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = ['commentable_type', 'commentable_id', 'author_id', 'parent_id', 'content'];

  protected static function newFactory()
    {
        return CommentFactory::new();
    }


    // ========== العلاقات ==========


    public function commentable()
    {
        return $this->morphTo();
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }
}
