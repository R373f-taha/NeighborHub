<?php

namespace Modules\Community\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Community\app\Models\Community;

class ManagerOrSuperAdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthenticated. Please provide a valid token.',
            ], 401);
        }

        if ($request->user()->isSuperAdmin()) {
            return $request;
        }


        if (!$request->user()->isManager()) {
            return response()->json([
                'message' => 'Unauthorized. This action requires Manager or super_admin  role.',
            ], 403);
        }

        $communityId = $request->route('communityId'); 
        $community=Community::find($communityId);
        if (!$community && $request->has('community_id')) {
            $community = Community::find($request->community_id);
        }

        if ($community) {
            $isManagerOfCommunity = $community->managers()
                ->where('manager_id', $request->user()->id)
                ->exists();

            if (!$isManagerOfCommunity) {
                return response()->json([
                    'message' => 'Unauthorized. You are not a manager of this community and you are not a super_admin.',
                ], 403);
            }
        } else {
            return response()->json([
                'message' => 'Community not found.',
            ], 404);
        }

        return $next($request);
    }
}
