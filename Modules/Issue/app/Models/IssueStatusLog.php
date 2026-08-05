<?php

namespace Modules\Issue\app\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Modules\Auth\app\Models\User;

use Modules\Issue\Database\Factories\IssueStatusLogFactory;
use Modules\Issue\app\Enums\IssueStatus;



class IssueStatusLog extends Model
{

    use HasFactory;

    protected $table = 'issue_status_logs';
    protected $fillable = [

        'issue_id',

        'old_status',

        'new_status',

        'changed_by',

        'note',

    ];

    protected $casts = [

        'old_status' => IssueStatus::class,

        'new_status' => IssueStatus::class,

    ];

    protected static function newFactory()
    {
        return IssueStatusLogFactory::new();
    }

    public function issue()
    {
        return $this->belongsTo(
            Issue::class
        );
    }


    public function changer()
    {
        return $this->belongsTo(
            User::class,
            'changed_by'
        );
    }

}