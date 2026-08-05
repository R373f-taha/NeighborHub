<?php

declare(strict_types=1);

namespace Modules\Issue\app\Http\Controllers\V1;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Issue\app\Actions\CreateIssueAction;
use Modules\Issue\app\Actions\UpdateIssueAction;
use Modules\Issue\app\Actions\DeleteIssueAction;
use Modules\Issue\app\Actions\AssignIssueAction;
use Modules\Issue\app\Actions\UpdateIssueStatusAction;
use Modules\Issue\app\Actions\AddIssueLogNoteAction;
use Modules\Issue\app\DTOs\IssueData;
use Modules\Issue\app\DTOs\AssignIssueData;
use Modules\Issue\app\DTOs\IssueStatusData;
use Modules\Issue\app\DTOs\IssueLogData;
use Modules\Issue\app\Http\Requests\V1\StoreIssueRequest;
use Modules\Issue\app\Http\Requests\V1\UpdateIssueRequest;
use Modules\Issue\app\Http\Requests\V1\AssignIssueRequest;
use Modules\Issue\app\Http\Requests\V1\UpdateIssueStatusRequest;
use Modules\Issue\app\Http\Requests\V1\AddIssueLogNoteRequest;
use Modules\Issue\app\Http\Resources\V1\IssueResource;
use Modules\Issue\app\Http\Resources\V1\IssueCollection;
use Modules\Issue\app\Models\Issue;
use Modules\Issue\app\Services\IssueService;
use Modules\Interaction\app\Http\Requests\Api\V1\StoreCommentRequest;
use Modules\Interaction\app\Services\CommentService;

class IssueController extends Controller
{

    public function __construct(
        private readonly IssueService $service,

        private readonly CreateIssueAction $createAction,

        private readonly UpdateIssueAction $updateAction,

        private readonly DeleteIssueAction $deleteAction,

        private readonly AssignIssueAction $assignAction,

        private readonly UpdateIssueStatusAction $statusAction,

        private readonly AddIssueLogNoteAction $logAction,

        private readonly CommentService $commentService,


    ) {}

    public function index(
        int $communityId
    ): IssueCollection {


        return new IssueCollection(

            $this->service
                ->paginateByCommunity($communityId)

        );

    }

    public function store(
        StoreIssueRequest $request,
        int $communityId
    ): IssueResource {


        $issue = $this->createAction->execute(

            IssueData::fromStoreRequest(
                $request,
                $communityId
            )

        );


        return new IssueResource(

            $issue->load([
                'category',
                'reporter'
            ])

        );

    }

public function show(
    int $communityId,
    int $issue
): IssueResource {

    $issue = Issue::findOrFail($issue);

    $issue->load([
        'category',
        'reporter',
        'assignee',
        'statusLogs.changer',
    ]);

    return new IssueResource($issue);
}

    public function update(
        UpdateIssueRequest $request,
        Issue $issue
    ): IssueResource {


        $issue = $this->updateAction->execute(

            $issue,

            IssueData::fromUpdateRequest($request)

        );
        return new IssueResource($issue);

    }


  public function assign(
    AssignIssueRequest $request,
    int $communityId,
    int $issue
): IssueResource
{
    $issue = Issue::findOrFail($issue);

    $issue = $this->assignAction->execute(
        $issue,
        AssignIssueData::fromRequest($request)
    );

    return new IssueResource($issue);
}
public function updateStatus(
    UpdateIssueStatusRequest $request,
    int $communityId,
    int $issue
): IssueResource
{
    $issue = Issue::findOrFail($issue);

    $issue = $this->statusAction->execute(
        $issue,
        IssueStatusData::fromRequest($request)
    );

    return new IssueResource($issue);
}


public function addUpdate(
    AddIssueLogNoteRequest $request,
    int $communityId,
    int $issue
): JsonResponse {

    $issue = Issue::findOrFail($issue);

    $this->logAction->execute(
        $issue,
        IssueLogData::fromRequest($request)
    );

    return response()->json([
        'message' => 'Issue update added successfully'
    ]);
}
  public function updates(
    int $communityId,
    int $issue
): JsonResponse
{
    $issue = Issue::findOrFail($issue);

    return response()->json(
        $issue
            ->statusLogs()
            ->with('changer')
            ->latest()
            ->get()
    );
}
public function destroy(
    int $communityId,
    int $issue
): JsonResponse {

    $issue = Issue::findOrFail($issue);

    $this->deleteAction->execute($issue);

    return response()->json([
        'message' => 'Issue deleted successfully'
    ]);
}

public function addComment(
    StoreCommentRequest $request,
    int $communityId,
    int $issue
): JsonResponse {

    $issue = Issue::findOrFail($issue);

    $comment = $this->commentService->store(
        $issue,
        $request->user(),
        $request->validated()
    );

    return response()->json([
        'message' => 'Comment added successfully',
        'data' => $comment
    ]);
}
}