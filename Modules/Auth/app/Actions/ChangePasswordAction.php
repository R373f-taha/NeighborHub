<?php

declare(strict_types=1);

namespace Modules\Auth\app\Actions;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Auth\app\Models\User;
use Modules\Auth\app\Support\AuthSecurityContext;
use Modules\Auth\app\Support\AuthSecurityLogger;

class ChangePasswordAction
{
    public function __construct(private readonly AuthSecurityLogger $security) {}

    public function execute(User $user, string $currentPassword, string $newPassword, PersonalAccessToken $currentToken, AuthSecurityContext $securityContext): void
    {
        if (! Hash::check($currentPassword, $user->getAuthPassword())) {
            throw ValidationException::withMessages([
                'current_password' => __('The provided current password is incorrect.'),
            ]);
        }

        $user->password = $newPassword;
        $user->save();

        $revokedTokenCount = $user->tokens()->whereKeyNot($currentToken->getKey())->delete();

        $this->security->passwordChanged($user, $securityContext, (int) $revokedTokenCount);
    }
}
