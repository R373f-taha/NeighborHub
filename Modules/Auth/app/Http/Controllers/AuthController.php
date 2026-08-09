<?php

declare(strict_types=1);

namespace Modules\Auth\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Auth\app\Actions\LoginUserAction;
use Modules\Auth\app\Actions\RegisterUserAction;
use Modules\Auth\app\Http\Requests\LoginRequest;
use Modules\Auth\app\Http\Requests\RegisterRequest;
use Modules\Auth\app\Http\Resources\UserResource;
use Modules\Auth\app\Support\AuthSecurityContext;
use Modules\Auth\app\Support\AuthSecurityLogger;

class AuthController extends Controller
{
    public function __construct(
        private RegisterUserAction $registerUser,
        private LoginUserAction $loginUser,
        private readonly AuthSecurityLogger $security,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $result = $this->registerUser->execute(
            $data['name'],
            $data['email'],
            $data['phone'] ?? null,
            $data['password'],
            $data['device_name'],
            new AuthSecurityContext(
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            ),
        );


        return response()->json([
            'message' => 'Account registered successfully.',
            'data' => [
                'user' => new UserResource($result['user']),
                'access_token' => $result['access_token'],
                'token_type' => 'Bearer',
                'expires_at' => $result['expires_at']->toIso8601String(),
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $result = $this->loginUser->execute(
            $data['email'],
            $data['password'],
            $data['device_name'],
            new AuthSecurityContext(
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            ),
        );

        if ($result === null) {
            return response()->json(['message' => 'The provided credentials are invalid.'], 401);
        }

        return response()->json([
            'message' => 'Login successful.',
            'data' => [
                'user' => new UserResource($result['user']),
                'access_token' => $result['access_token'],
                'token_type' => 'Bearer',
                'expires_at' => $result['expires_at']->toIso8601String(),
            ],
        ], 200);
    }

    public function logout(Request $request): Response
    {
        $user = $request->user();
        $token = $user->currentAccessToken();
        $tokenName = $token?->name;

        $token->delete();

        $this->security->logoutSucceeded(
            $user,
            new AuthSecurityContext(
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            ),
            $tokenName,
        );

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
