<?php

declare(strict_types=1);

namespace Modules\Issue\app\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

use Modules\Issue\app\DTOs\AssignIssueData;
use Modules\Issue\app\DTOs\IssueData;
use Modules\Issue\app\DTOs\IssueLogData;
use Modules\Issue\app\DTOs\IssueStatusData;
use Modules\Issue\app\Enums\IssueStatus;

use Modules\Issue\app\Events\IssueAssigned;
use Modules\Issue\app\Events\IssueCreated;
use Modules\Issue\app\Events\IssueDeleted;
use Modules\Issue\app\Events\IssueLogAdded;
use Modules\Issue\app\Events\IssueStatusUpdated;

use Modules\Issue\app\Models\Issue;

use Modules\Issue\app\Repositories\Contracts\IssueRepositoryInterface;

use Modules\Issue\app\Rules\IssueStatusTransitionRule;


class IssueService
{

    public function __construct(
        private readonly IssueRepositoryInterface $repository
    ) {}



    public function paginateByCommunity(
        int $communityId,
        int $perPage = 15
    ): LengthAwarePaginator {

        return $this->repository
            ->paginateByCommunity(
                $communityId,
                $perPage
            );
    }
    public function findOrFail(
        int $issueId
    ): Issue {

        return $this->repository
            ->findOrFail($issueId);
    }
    public function create(
        IssueData $data
    ): Issue {

        return DB::transaction(function () use ($data) {

            $issue = $this->repository->create(
                $data->toArray()
            );


            IssueCreated::dispatch(
                $issue
            );


            return $issue;

        });

    }

    public function update(
        Issue $issue,
        IssueData $data
    ): Issue {

        return DB::transaction(function () use (
            $issue,
            $data
        ) {


            if ($issue->assigned_to !== null) {

                abort(
                    422,
                    'Issue cannot be updated after assignment.'
                );

            }


            return $this->repository->update(
                $issue,
                $data->toArray()
            );


        });

    }

  public function assign(
    Issue $issue,
    AssignIssueData $data
): Issue {

    return DB::transaction(function () use (
        $issue,
        $data
    ) {

        $issue = Issue::query()
            ->lockForUpdate()
            ->findOrFail($issue->id);


        if ($issue->assigned_to !== null) {

            abort(
                422,
                'Issue is already assigned.'
            );

        }


        $issue->update([

            'assigned_to' => $data->providerId,

            'status' => IssueStatus::ASSIGNED,

        ]);

        $issue->refresh();
        IssueAssigned::dispatch(
            $issue
        );
        return $issue;

    });

}
    public function updateStatus(
        Issue $issue,
        IssueStatusData $data
    ): Issue {


        return DB::transaction(function () use (
            $issue,
            $data
        ) {


            $issue = Issue::query()
                ->lockForUpdate()
                ->findOrFail($issue->id);
            IssueStatusTransitionRule::validate(

                $issue->status,

                $data->status

            );

            $oldStatus = $issue->status;
            $issue->update(['status' => $data->status,]);



            $log = $issue->statusLogs()->create([

                'old_status' => $oldStatus->value,

                'new_status' => $data->status->value,

                'changed_by' => $data->changedBy,

                'note' => $data->note,

            ]);
            $issue->refresh();
            IssueStatusUpdated::dispatch(
                $issue,
                $oldStatus->value
            );

            IssueLogAdded::dispatch(
                $log
            );
            return $issue;

        });

    }

public function addLog(Issue $issue, IssueLogData $data): void
{
    $status = $issue->getRawOriginal('status');

    $issue->statusLogs()->create([
        'old_status' => $status,
        'new_status' => $status,
        'changed_by' => $data->changedBy,
        'note' => $data->note,
    ]);
}
   public function delete(
    Issue $issue
): bool {

    return DB::transaction(function () use ($issue) {


        if ($issue->assigned_to !== null) {

            abort(
                422,
                'Assigned issue cannot be deleted.'
            );

        }


        return $this->repository
            ->delete($issue);

    });

}

}