<?php

declare(strict_types=1);

namespace Modules\Auth\app\Actions;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Validation\ValidationException;
use Modules\Auth\app\Models\User;
use Modules\Auth\app\Support\AuthSecurityContext;
use Modules\Auth\app\Support\AuthSecurityLogger;

class ResetPasswordAction
{
    public function __construct(private readonly AuthSecurityLogger $security) {}

    public function execute(string $email, string $token, string $password, AuthSecurityContext $securityContext): void
    {
        $status = PasswordBroker::reset(
            [
                'email' => $email,
                'token' => $token,
                'password' => $password,
                'password_confirmation' => $password,
            ],
            function (User $user, string $password) use ($securityContext): void {
                $user->password = $password;
                $user->save();

                $revokedTokenCount = $user->tokens()->delete();

                event(new PasswordReset($user));

                $this->security->passwordResetCompleted($user, $securityContext, (int) $revokedTokenCount);
            }
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => __('Unable to reset the password with the provided data.'),
            ]);
        }
    }
}
