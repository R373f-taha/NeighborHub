<?php

declare(strict_types=1);

namespace Modules\Community\app\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Services\V1\CommunityService;
use Modules\Community\app\Http\Requests\StoreCommunityRequest;
use Modules\Community\app\Http\Requests\UpdateCommunityRequest;
use Modules\Community\App\Transformers\CommunityResource;

class CommunityController extends Controller
{
    public function __construct(
        private CommunityService $communityService,


    ) {}

  /**
     * GET /api/v1/communities
     * List all active communities (Public)
     */
    public function index(Request $request)
    {
        try {
            $filters = $request->only(['city', 'name']);
            $perPage = $request->input('per_page', 20);

            $communities = $this->communityService->getCommunities($filters, $perPage);

            if ($communities->total() === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No communities found.',
                    'data' => [],
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Communities retrieved successfully.',
                'data' => CommunityResource::collection($communities),
                'meta' => [
                    'total' => $communities->total(),
                    'per_page' => $communities->perPage(),
                    'current_page' => $communities->currentPage(),
                    'last_page' => $communities->lastPage(),
                ],
            ], 200);

        } catch (\Exception $e) {


            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }
    /**
     * POST /communities - Super Admin only
     */
    public function store(StoreCommunityRequest $request)
    {
        $community = $this->communityService->create($request->validated());
        return new CommunityResource($community);
    }

    /**
     * GET /communities/{id} - Auth
     */
    public function show($communityId)
    {
        $community=Community::findOrFail($communityId);
        return new CommunityResource($community->load(['units', 'managers']));
    }

    /**
     * PUT /communities/{id} - Super Admin / Manager
     */
    public function update(UpdateCommunityRequest $request,$communityId)
    {
        $community = Community::findOrFail($communityId);

        $updated = $this->communityService->update($community, $request->validated());
       return new CommunityResource($updated);
    }

    /**
     * DELETE /communities/{id} - Super Admin
     */
    public function destroy($communityId)
    {
        $community = Community::findOrFail($communityId);


        $this->communityService->delete($community);
        return response()->json(['message' => 'Community deleted successfully']);
    }




}
