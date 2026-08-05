<?php

declare(strict_types=1);

namespace Modules\Issue\app\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Issue\app\Models\Issue;
use Modules\Issue\app\Repositories\Contracts\IssueRepositoryInterface;

class IssueRepository implements IssueRepositoryInterface
{
    public function paginateByCommunity(
        int $communityId,
        int $perPage = 15
    ): LengthAwarePaginator {
        return Issue::query()
            ->with([
                'category',
                'reporter',
                'assignee',
            ])
            ->where('community_id', $communityId)
            ->latest()
            ->paginate($perPage);
    }

    public function findOrFail(
        int $issueId
    ): Issue {
        return Issue::query()
            ->with([
                'category',
                'reporter',
                'assignee',
                'statusLogs',
            ])
            ->findOrFail($issueId);
    }

    public function create(
        array $data
    ): Issue {
        return Issue::create($data);
    }

    public function update(
        Issue $issue,
        array $data
    ): Issue {
        $issue->update($data);

        return $issue->refresh();
    }

    public function delete(
    Issue $issue
): bool {
    return $issue->delete();
}
}