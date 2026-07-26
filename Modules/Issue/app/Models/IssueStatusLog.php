<?php

namespace Modules\Issue\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Auth\Models\User;

class IssueStatusLog extends Model
{
    protected $table = 'issue_status_log';

    protected $fillable = ['issue_id', 'old_status', 'new_status', 'changed_by', 'note'];

    public function issue()
    {
        return $this->belongsTo(Issue::class);
    }

    public function changer()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
