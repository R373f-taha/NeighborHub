<?php

namespace Modules\Community\app\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Services\V1\StatsService;
use Modules\Community\app\Http\Requests\StoreCommunityRequest;
use Modules\Community\app\Http\Requests\UpdateCommunityRequest;
use Modules\Community\app\Http\Requests\JoinCommunityRequest;
use Modules\Community\app\Services\V1\MembershipService;
use Modules\Community\App\Transformers\CommunityResource;
use Modules\Community\App\Transformers\ResidentResource;

class StatsController extends Controller
{
    public function __construct(
        private StatsService $statsService
    ) {}

    /**
     * GET /communities/{id}/stats - Manager
     * Get community statistics
     */
    public function stats($communityId)
    {
        Log::info('Stats request started', [
            'community_id' => $communityId,
            'user_id' => Auth::id() ?? 'guest',
            'ip' => request()->ip(),
        ]);

        try {
            $community = Community::findOrFail($communityId);

            Log::info('Community found for stats', [
                'community_id' => $community->id,
                'community_name' => $community->name,
                'total_units' => $community->total_units,
            ]);

            $stats = $this->statsService->getStats($community->id);

            Log::info('Stats calculated successfully', [
                'community_id' => $community->id,
                'total_residents' => $stats['total_residents'] ?? 0,
                'total_units' => $stats['total_units'] ?? 0,
                'active_residents' => $stats['active_residents'] ?? 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => $stats['total_residents'] === 0
                    ? 'Community exists but has no residents yet'
                    : 'Stats retrieved successfully',
                'data' => $stats,
                'is_empty' => $stats['total_residents'] === 0,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Community not found for stats', [
                'community_id' => $communityId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Community not found',
                'data' => null,
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error getting community stats', [
                'community_id' => $communityId,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching stats',
                'data' => null,
            ], 500);
        }
    }

    /**
     * GET /communities/{id}/residents - Manager
     * Get all residents of a community
     */
    public function residents($communityId)
    {
        Log::info('Residents list request started', [
            'community_id' => $communityId,
            'user_id' => Auth::id() ?? 'guest',
            'ip' => request()->ip(),
        ]);

        try {
            $community = Community::findOrFail($communityId);

            Log::info('Community found for residents list', [
                'community_id' => $community->id,
                'community_name' => $community->name,
            ]);

            $residents = $this->statsService->getResidents($community->id);

            Log::info('Residents retrieved successfully', [
                'community_id' => $community->id,
                'total_residents' => $residents->count(),
            ]);

            return ResidentResource::collection($residents);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Community not found for residents list', [
                'community_id' => $communityId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Community not found',
                'data' => [],
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error getting residents list', [
                'community_id' => $communityId,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching residents',
                'data' => [],
            ], 500);
        }
    }
}
