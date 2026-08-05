<?php

declare(strict_types=1);

namespace Modules\Issue\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Issue\Database\Factories\IssueCategoryFactory;
class IssueCategory extends Model
{
    use HasFactory;


    protected $fillable = [
        'name',
        'is_active',
    ];


    protected $casts = [
        'is_active' => 'boolean',
    ];



    protected static function newFactory()
    {
        return IssueCategoryFactory::new();
    }

    public function issues()
    {
        return $this->hasMany(
            Issue::class,
            'category_id'
        );
    }
}