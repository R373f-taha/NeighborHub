<?php

declare(strict_types=1);

namespace Modules\Auth\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Modules\Auth\app\Actions\ChangePasswordAction;
use Modules\Auth\app\Actions\ResetPasswordAction;
use Modules\Auth\app\Actions\SendPasswordResetLinkAction;
use Modules\Auth\app\Http\Requests\ChangePasswordRequest;
use Modules\Auth\app\Http\Requests\ForgotPasswordRequest;
use Modules\Auth\app\Http\Requests\ResetPasswordRequest;
use Modules\Auth\app\Support\AuthSecurityContext;

class PasswordController extends Controller
{
    public function __construct(
        private ChangePasswordAction $changePassword,
        private SendPasswordResetLinkAction $sendResetLink,
        private ResetPasswordAction $resetPassword,
    ) {}

    public function change(ChangePasswordRequest $request): Response
    {
        $user = $request->user();
        $data = $request->validated();

        $this->changePassword->execute(
            $user,
            $data['current_password'],
            $data['password'],
            $user->currentAccessToken(),
            new AuthSecurityContext(
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            ),
        );

        return response()->noContent();
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->sendResetLink->execute(
            $request->validated('email'),
            new AuthSecurityContext(
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            ),
        );

        return response()->json([
            'message' => 'If an account exists for this email, a password reset link has been sent.',
        ], 200);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->resetPassword->execute(
            $data['email'],
            $data['token'],
            $data['password'],
            new AuthSecurityContext(
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            ),
        );

        return response()->json(['message' => 'Password reset successfully.'], 200);
    }
}
