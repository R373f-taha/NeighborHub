<?php

declare(strict_types=1);

namespace Modules\Poll\App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
use Modules\Poll\app\Enums\PollStatus;
use Modules\Poll\app\Http\Requests\VotePollRequest;
use Modules\Poll\app\Models\Poll;
use Modules\Poll\App\Services\V1\VotesManagementService;

class VotesManagementController extends Controller
{
    public function __construct(
        private VotesManagementService $votesManagementService
    ) {}

    /**
     * POST /communities/{id}/polls/{pid}/vote
     */
    public function vote(VotePollRequest $request, $communityId, $pollId): JsonResponse
    {
        $community = Community::findOrFail($communityId);
        $poll = Poll::findOrFail($pollId);

    $resident = Resident::where('user_id', $request->user()->id)
            ->where('community_id', $community->id)
            ->where('current_marker', true)
            ->firstOrFail();

        $result = $this->votesManagementService->vote(
            $poll,
            $resident,
            (int) $request->input('option_id')
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'poll_id' => $poll->id,
                'option_id' => $result['data']->option_id,
                'submitted_at' => $result['data']->submitted_at,
            ],
        ]);
    }

    /**
     * GET /communities/{id}/polls/{pid}/results
     */
    public function results($communityId, $pollId): JsonResponse
    {
        $community = Community::findOrFail($communityId);
        $poll = Poll::findOrFail($pollId);

        if ($poll->community_id !== $community->id) {
            return response()->json([
                'success' => false,
                'message' => 'Poll does not belong to this community.',
            ], 404);
        }

        if ($poll->status !== 'closed') {
            return response()->json([
                'success' => false,
                'message' => 'Poll is not closed yet.',
            ], 403);
        }

        $results = $this->votesManagementService->getResults($poll);

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }
}
