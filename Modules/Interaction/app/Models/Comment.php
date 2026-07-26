<?php

namespace Modules\Interaction\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\Models\User;

class Comment extends Model
{
    use SoftDeletes;

    protected $fillable = ['commentable_type', 'commentable_id', 'author_id', 'parent_id', 'content'];

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
