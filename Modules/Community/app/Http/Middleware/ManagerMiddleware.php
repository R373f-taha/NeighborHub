<?php

namespace Modules\Community\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Community\app\Models\Community;

class ManagerMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthenticated. Please provide a valid token.',
            ], 401);
        }

        if (!$request->user()->isManager()) {
            return response()->json([
                'message' => 'Unauthorized. This action requires Manager role.',
            ], 403);
        }

        $communityId = $request->route('communityId'); // Route Model Binding
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
                    'message' => 'Unauthorized. You are not a manager of this community.',
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
