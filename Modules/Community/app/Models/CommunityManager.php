<?php

namespace Modules\Community\app\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Auth\app\Models\User;

class CommunityManager extends Model
{
    protected $table = 'community_mangers';

    protected $fillable = [  'community_id',  'manager_id' ];


    public function community()
    {
        return $this->belongsTo(Community::class);
    }


    public function manager()
    {
        return $this->belongsTo(User::class,'manager_id');
    }
}
