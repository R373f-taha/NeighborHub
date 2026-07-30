<?php

declare(strict_types=1);

namespace Modules\Community\app\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Services\V1\MembershipService;
use Modules\Community\app\Http\Requests\JoinCommunityRequest;
use Modules\Community\app\Transformers\ResidentResource;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class MembershipController extends Controller
{
    public function __construct(
        private MembershipService $membershipService
    ) {}

    /**
     * POST /api/v1/communities/{communityId}/join
     * Request to join a community (Resident only)
     */
    public function join(JoinCommunityRequest $request, $communityId): JsonResponse
    {
        try {
            $community = Community::findOrFail($communityId);

            $result = $this->membershipService->joinCommunity(
                $community,
                $request->user(),
                $request->validated()
            );

            if (is_array($result)) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'data' => $result['record'] ? new ResidentResource($result['record']) : null,
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => 'Join request submitted successfully. Waiting for manager approval.',
                'data' => new ResidentResource($result),
            ], 202);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Community not found.',
            ], 404);

        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/communities/{communityId}/residents/{residentId}/approve
     * Approve a pending resident (Manager only)
     */
    public function approve($communityId, $residentId): JsonResponse
    {
        try {
            $community = Community::findOrFail($communityId);
            $resident = Resident::findOrFail($residentId);

            $approved = $this->membershipService->approveResident($community, $resident);

            return response()->json([
                'success' => true,
                'message' => 'Resident approved successfully.',
                'data' => new ResidentResource($approved),
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Community or Resident not found.',
            ], 404);

        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/communities/{communityId}/residents/{residentId}/reject
     * Reject a pending resident (Manager only)
     */
    public function reject($communityId, $residentId): JsonResponse
    {
        try {
            $community = Community::findOrFail($communityId);
            $resident = Resident::findOrFail($residentId);

            $rejected = $this->membershipService->rejectResident($community, $resident);

            return response()->json([
                'success' => true,
                'message' => 'Resident rejected successfully.',
                'data' => new ResidentResource($rejected),
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Community or Resident not found.',
            ], 404);

        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/communities/{communityId}/residents/{residentId}/suspend
     * Suspend an active resident (Manager only)
     */
    public function suspend($communityId, $residentId): JsonResponse
    {
        try {
            $community = Community::findOrFail($communityId);
            $resident = Resident::findOrFail($residentId);

            $suspended = $this->membershipService->suspendResident($resident, $community);

            return response()->json([
                'success' => true,
                'message' => 'Resident suspended successfully.',
                'data' => new ResidentResource($suspended),
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Community or Resident not found.',
            ], 404);

        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/residents/me
     * Get current user's residency (Resident only)
     */
    public function myResidency(Request $request): JsonResponse
    {
        try {
            $resident = $this->membershipService->getMyResidency($request->user());

            if (!$resident) {
                return response()->json([
                    'success' => false,
                    'message' => 'No residency found for this user.',
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Residency retrieved successfully.',
                'data' => new ResidentResource($resident),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }
}
