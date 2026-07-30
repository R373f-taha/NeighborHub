<?php

namespace Modules\Community\app\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
use Modules\Community\Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Unit extends Model
{use HasFactory;
    protected $fillable = ['community_id', 'unit_number', 'building_name', 'rooms', 'unit_type', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function community()
    {
        return $this->belongsTo(Community::class);
    }

    protected static function newFactory()
    {
        return UnitFactory::new();
    }
    public function residents()
    {
        return $this->hasMany(Resident::class);
    }
}
