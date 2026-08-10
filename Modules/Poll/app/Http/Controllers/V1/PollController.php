<?php

declare(strict_types=1);

namespace Modules\Poll\app\Http\Controllers\V1;

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

class PollController extends Controller
{
    use Authorizable;
    public function __construct(
        private PollService $pollService
    ) {}

    /**
     * GET /communities/{id}/polls - List polls (Auth)
     */
    public function index(Request $request,$communityId)
    {
        $community=Community::findOrFail($communityId);
        $polls = $this->pollService->getCommunityPolls(
            $community->id,
            $request->only(['status', 'type'])
        );

        return PollResource::collection($polls);
    }

    /**
     * POST /communities/{id}/polls - Create poll (Manager)
     */
    public function store(StorePollRequest $request,$communityId)
    {
        $community=Community::findOrFail($communityId);
        $poll = $this->pollService->createPoll(
            $community,
            $request->user(),
            $request->validated()
        );

        return new PollResource($poll->load(['options', 'creator']));
    }

    /**
     * GET /communities/{id}/polls/{pid} - Get poll details (Resident)
     */
    public function show($communityId, $pollId)
    {
        $community = Community::findOrFail($communityId);
        $poll = Poll::findOrFail($pollId);

        if ($poll->community_id !== $community->id) {
           return response()->json(['message'=>'This poll doesn`t follow to this community'],404);;
        }

        return new PollResource($poll->load(['options', 'creator']));
    }


}
