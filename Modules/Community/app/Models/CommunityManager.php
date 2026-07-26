<?php

namespace Modules\Community\app\Models;

use App\Models\User as ModelsUser;
use Illuminate\Database\Eloquent\Model;
use Modules\Auth\Models\User;

class CommunityManager extends Model
{
    protected $table = 'community_managers';

    protected $fillable = ['community_id', 'manager_id'];

    public function community()
    {
        return $this->belongsTo(Community::class);
    }

    public function manager()
    {
        return $this->belongsTo(ModelsUser::class, 'manager_id');
    }
}
