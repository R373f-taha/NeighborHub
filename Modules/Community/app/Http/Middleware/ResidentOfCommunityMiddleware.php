<?php

namespace Modules\Community\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Community\app\Models\Community;

class ResidentOfCommunityMiddleware
{
    public function handle(
        Request $request,
        Closure $next
    ) {

        $user = $request->user();


        // Check authentication
        if (!$user) {

            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);

        }


        // Get community id from route
        $communityId = $request->route('communityId');


        $community = Community::find($communityId);


        if (!$community) {

            return response()->json([
                'message' => 'Community not found.',
            ], 404);

        }


        // Check resident belongs to this community
        $isResident = $community->residents()
            ->where('user_id', $user->id)
            ->exists();


        if (!$isResident) {

            return response()->json([
                'message' => 'Unauthorized. You are not a resident of this community.',
            ], 403);

        }


        return $next($request);
    }
}