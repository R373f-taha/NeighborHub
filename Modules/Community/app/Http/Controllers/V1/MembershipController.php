<?php

namespace Modules\Community\app\Services\V1;
use App\Http\Controllers\Controller;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Services\V1\CommunityService;
use Modules\Community\app\Http\Requests\JoinCommunityRequest;
use Modules\Community\app\Services\V1\MembershipService;
use Modules\Community\App\Transformers\ResidentResource;


class MembershipController extends Controller{
     public function __construct(
        private CommunityService $communityService,
        private MembershipService $membershipService

    ) {}

  /**
     * POST /communities/{id}/join - Resident
     */
    public function join(JoinCommunityRequest $request, $communityId)
    {
        $community = Community::findOrFail($communityId);
        $resident = $this->membershipService->joinCommunity(
            $community,
            $request->user(),
            $request->validated()
        );
       if(is_array($resident))
        return response()->json($resident, 200);

       return
       ['message'=>'Welcome in our community, your request is pending approval by the manager.😊💛',
       'your data'=>new ResidentResource($resident)];
    }

     /**
 * POST /communities/{id}/residents/{uid}/approve - Manager
 */
public function approve($communityId, $residentId)
{
    try {
        $community = Community::findOrFail($communityId);
        $resident = Resident::findOrFail($residentId);

        $approved = $this->membershipService->approveResident($community, $resident);

        return response()->json([
            'message' => 'Resident approved successfully',
            'data' => new ResidentResource($approved),
        ]);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'message' => 'Resident not found with ID: ' . $residentId,
        ], 404);
    } catch (\RuntimeException $e) {
        return response()->json([
            'message' => $e->getMessage(),
        ], 422);
    }
}

/**
 * POST /communities/{id}/residents/{uid}/reject - Manager
 */
public function reject($communityId, $residentId)
{
    try {
      $community = Community::findOrFail($communityId);

      $resident = Resident::findOrFail($residentId);


        $rejected = $this->membershipService->rejectResident($community, $resident);

        return response()->json([
            'message' => 'Resident rejected successfully',
            'data' => new ResidentResource($rejected),
        ]);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'message' => 'Resident not found with ID: ' . $residentId,
        ], 404);
    } catch (\RuntimeException $e) {
        return response()->json([
            'message' => $e->getMessage(),
        ], 422);
    }
}

/**
 * POST /communities/{id}/residents/{uid}/suspend - Manager
 */
public function suspend(Community $community, $residentId)
{
    try {
        $resident = Resident::findOrFail($residentId);

        $suspended = $this->membershipService->suspendResident( $resident,$community);

        return response()->json([
            'message' => 'Resident suspended successfully',
            'data' => new ResidentResource($suspended),
        ]);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'message' => 'Resident not found with ID: ' . $residentId,
        ], 404);
    } catch (\RuntimeException $e) {
        return response()->json([
            'message' => $e->getMessage(),
        ], 422);
    }
}
}
