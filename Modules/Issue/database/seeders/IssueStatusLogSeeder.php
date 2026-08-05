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

        $managers = User::role('manager')->get();


        if ($managers->isEmpty()) {
            return;
        }



        Issue::query()
            ->each(function (Issue $issue) use ($managers) {


                IssueStatusLog::create([

                    'issue_id' => $issue->id,

                    'old_status' => null,

                    'new_status' => $issue->status,

                    'changed_by' => $managers->random()->id,

                    'note' => 'Initial issue status',

                ]);


            });

    }
}