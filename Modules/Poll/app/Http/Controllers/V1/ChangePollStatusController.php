<?php

namespace Modules\Poll\app\Http\Controllers\V1;

use Modules\Poll\app\Services\V1\ChangePollStatusService;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Http\Request;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
use Modules\Poll\app\Enums\PollStatus;
use Modules\Poll\app\Http\Requests\StorePollRequest;
use Modules\Poll\app\Models\Poll;
use Modules\Poll\app\Services\V1\PollService;
use Modules\Poll\app\Transformers\PollResource;
use Modules\Poll\app\Transformers\PollResultResource;
use Modules\Poll\app\Http\Requests\VotePollRequest;
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
            abort(404);
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
