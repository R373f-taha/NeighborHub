<?php

declare(strict_types=1);
namespace Modules\Community\app\Http\Controllers\V1;
use Modules\Community\app\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Community\app\Actions\CreateAnnouncementAction;
use Modules\Community\app\Actions\DeleteAnnouncementAction;
use Modules\Community\app\Actions\ReactToAnnouncementAction;
use Modules\Community\app\Actions\UpdateAnnouncementAction;
use Modules\Community\app\Http\Requests\V1\ReactAnnouncementRequest;
use Modules\Community\app\Http\Requests\V1\StoreAnnouncementRequest;
use Modules\Community\app\Http\Requests\V1\UpdateAnnouncementRequest;
use Modules\Community\app\Http\Resources\Api\V1\AnnouncementCollection;
use Modules\Community\app\Http\Resources\Api\V1\AnnouncementResource;
use Modules\Community\app\Models\Announcement;
use Modules\Community\app\Repositories\Contracts\AnnouncementRepositoryInterface;
use Modules\Community\app\Models\Community;

class AnnouncementController extends Controller
{

    public function __construct(
        private AnnouncementRepositoryInterface $repository
    ) {}

    public function index(
        int $communityId
    ): AnnouncementCollection {


        return new AnnouncementCollection(

            $this->repository
                ->paginateByCommunity(
                    $communityId
                )
        );
    }
public function store(
    StoreAnnouncementRequest $request,
    CreateAnnouncementAction $action,
    int $communityId
): AnnouncementResource {

    $community = Community::findOrFail($communityId);

  $this->authorize('create', [Announcement::class, $community]);

    $data = $request->validated();

    $data['community_id'] = $communityId;

    $announcement = $action->execute(
        $request->user(),
        $data
    );

    return new AnnouncementResource($announcement);
}

public function show(
    int $communityId,
    $announcement
): AnnouncementResource {

    $announcement = Announcement::findOrFail($announcement);

    abort_if(
        (int) $announcement->community_id !== (int) $communityId,
        404
    );

    $this->authorize('view', $announcement);

    return new AnnouncementResource($announcement);
}



public function update(
    int $communityId,
    int $announcement,
    UpdateAnnouncementRequest $request,
    UpdateAnnouncementAction $action
): JsonResponse {

    $announcement = Announcement::findOrFail($announcement);

    $this->authorize('update', $announcement);

    $action->execute(
        $announcement,
        $request->validated()
    );

    return response()->json([
        'message' => 'Announcement updated successfully'
    ]);
}

public function destroy(
    int $communityId,
    int $announcement,
    DeleteAnnouncementAction $action
): JsonResponse {

    $announcement = Announcement::findOrFail($announcement);

    $this->authorize(
        'delete',
        $announcement
    );

    $action->execute($announcement);

    return response()->json([
        'message' => 'Announcement deleted successfully'
    ]);
}
   public function react(
       int $communityId,
       $announcement,
       ReactAnnouncementRequest $request,
       ReactToAnnouncementAction $action
   ): JsonResponse {

        $announcement = Announcement::findOrFail($announcement);

        abort_if(
            (int) $announcement->community_id !== (int) $communityId,
            404
        );

        $this->authorize(
            'react',
            $announcement
        );


        $action->execute(

            $announcement,

            $request->user(),

            $request->validated()['type']

        );


        return response()->json([

            'message' => 'Reaction added successfully'

        ]);

    }
}