<?php

declare(strict_types=1);

namespace Modules\Issue\app\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Issue\app\Models\Issue;

interface IssueRepositoryInterface
{
    public function paginateByCommunity(
        int $communityId,
        int $perPage = 15
    ): LengthAwarePaginator;

    public function findOrFail(
        int $issueId
    ): Issue;

    public function create(
        array $data
    ): Issue;

    public function update(
        Issue $issue,
        array $data
    ): Issue;

    public function delete(
        Issue $issue
    ): bool;
}