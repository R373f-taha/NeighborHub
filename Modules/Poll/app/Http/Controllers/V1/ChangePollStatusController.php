<?php

namespace Modules\Poll\App\Http\Controllers\V1;

use Modules\Poll\App\Services\V1\ChangePollStatusService;
use Modules\Community\app\Models\Community;
use Modules\Poll\app\Models\Poll;
use Modules\Poll\App\Transformers\PollResource;

class ChangePollStatusController{
  public function __construct(
        private ChangePollStatusService $changePollStatusService
    ) {}

 /**
     * POST /communities/{id}/polls/{pid}/activate - Activate poll (Manager)
     */
    public function activate($communityId,$pollId)
    {
        $poll=Poll::findOrFail($pollId);
        $community = Community::findOrFail($communityId);

        if ($poll->community_id !== $community->id) {
     return response()->json(['message'=>'This poll doesn`t follow to this community'],404);;
        }

      //  $this->authorize('activate', $poll);

        $poll = $this->changePollStatusService->activatePoll($poll);

        return new PollResource($poll->load(['options', 'creator']));
    }

    /**
     * POST /communities/{id}/polls/{pid}/close - Close poll (Manager)
     */
    public function close($communityId, $pollId)
    {
        $community = Community::findOrFail($communityId);
        $poll = Poll::findOrFail($pollId);

        if ($poll->community_id !== $community->id) {
            abort(404);
        }

       // $this->authorize('close', $poll);

        $poll = $this->changePollStatusService->closePoll($poll, request()->user());

        return new PollResource($poll->load(['options', 'creator']));
    }


}
