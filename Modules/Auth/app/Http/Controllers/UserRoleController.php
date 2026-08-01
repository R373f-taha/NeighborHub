<?php

declare(strict_types=1);

namespace Modules\Auth\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Modules\Auth\app\Actions\AssignUserRoleAction;
use Modules\Auth\app\Exceptions\LastSuperAdminException;
use Modules\Auth\app\Http\Requests\UpdateUserRoleRequest;
use Modules\Auth\app\Http\Resources\UserResource;
use Modules\Auth\app\Models\User;
use Modules\Auth\app\Support\AuthSecurityContext;

class UserRoleController extends Controller
{
    public function update(UpdateUserRoleRequest $request, AssignUserRoleAction $action, User $user): JsonResponse
    {
        Gate::authorize('assignRole', $user);

        try {
            $updated = $action->execute(
                $request->user(),
                $user,
                $request->validated('role'),
                new AuthSecurityContext(
                    ip: $request->ip(),
                    userAgent: $request->userAgent(),
                ),
            );
        } catch (LastSuperAdminException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 403);
        }

        return response()->json([
            'message' => 'User role updated successfully.',
            'data' => new UserResource($updated),
        ], 200);
    }
}
