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
        $managers = User::role('manager')->pluck('id');

        if ($managers->isEmpty()) {
            return;
        }

        Issue::query()
            ->each(function (Issue $issue) use ($managers): void {

                if ($issue->status->value === 'open') {
                    IssueStatusLog::updateOrCreate(
                        [
                            'issue_id' => $issue->id,
                            'new_status' => $issue->status,
                        ],
                        [
                            'old_status' => null,
                            'changed_by' => $managers->random(),
                            'note' => 'Initial issue status',
                        ]
                    );

                    return;
                }

                IssueStatusLog::updateOrCreate(
                    [
                        'issue_id' => $issue->id,
                        'new_status' => $issue->status,
                    ],
                    [
                        'old_status' => 'open',
                        'changed_by' => $managers->random(),
                        'note' => 'Issue status updated',
                    ]
                );
            });
    }
}