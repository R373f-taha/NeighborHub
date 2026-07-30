<?php

declare(strict_types=1);

namespace Modules\Community\app\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Services\V1\CommunityService;
use Modules\Community\app\Http\Requests\StoreCommunityRequest;
use Modules\Community\app\Http\Requests\UpdateCommunityRequest;
use Modules\Community\app\Http\Requests\JoinCommunityRequest;
use Modules\Community\app\Services\V1\MembershipService;
use Modules\Community\App\Transformers\CommunityResource;
use Modules\Community\App\Transformers\ResidentResource;

class CommunityController extends Controller
{
    public function __construct(
        private CommunityService $communityService,
        private MembershipService $membershipService

    ) {}

    /**
     * GET /communities - Public
     */
    public function index(Request $request)
    {
        $communities = Community::where('is_active', true)
            ->when($request->city, fn($q) => $q->where('city', $request->city))
            ->paginate(20);

        if ($communities->total() === 0) {
            return response()->json([
                'message' => 'No communities found',
                'data' => [],
            ], 404);
        }

        return CommunityResource::collection($communities);
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
    public function show(Community $community)
    {
        return new CommunityResource($community->load(['units', 'managers']));
    }

    /**
     * PUT /communities/{id} - Super Admin / Manager
     */
    public function update(UpdateCommunityRequest $request,$communityId)
    {
        $community = Community::findOrFail($communityId);

 //var_dump($request->validated());
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




    /**
     * GET /residents/me - Resident
     */
    public function myResidency(Request $request)
    {
        $resident = Resident::where('user_id', $request->user()->id)
            ->where('current_marker', true)
            ->firstOrFail();

        return new ResidentResource($resident);
    }
}
