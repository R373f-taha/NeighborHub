<?php

declare(strict_types=1);

namespace Modules\Issue\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\app\Models\User;
use Modules\Issue\app\Models\Issue;
use Modules\Issue\app\Models\IssueStatusLog;

class IssueStatusLogSeeder extends Seeder
{
    public function run(): void
    {
        $managers = User::query()
            ->where('role', 'manager')
            ->get();


        Issue::query()
            ->each(function (Issue $issue) use ($managers) {


                IssueStatusLog::updateOrCreate(
    [
        'issue_id' => $issue->id,
    ],
    [
        'old_status' => null,
        'new_status' => $issue->status,
        'changed_by' => $managers->random()->id,
    ]
);

            });
    }
}