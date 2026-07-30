<?php

declare(strict_types=1);

namespace Modules\Auth\app\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\app\Models\User;
use Modules\Auth\app\Support\AuthSecurityContext;
use Modules\Auth\app\Support\AuthSecurityLogger;

class LoginUserAction
{
    public function __construct(private readonly AuthSecurityLogger $security) {}

    /**
     * @return array{user: User, access_token: string, expires_at: Carbon}|null
     */
    public function execute(string $email, string $password, string $deviceName, AuthSecurityContext $securityContext): ?array
    {
        $user = User::where('email', $email)->first();

        if (! $user instanceof User || ! Hash::check($password, $user->getAuthPassword())) {
            $this->security->loginFailed($email, $securityContext);

            return null;
        }

        if (! $user->is_active) {
            $this->security->loginBlockedInactive($user, $securityContext);

            return null;
        }

        if (Hash::needsRehash($user->getAuthPassword())) {
            $user->password = $password;
            $user->save();
        }

        $expiresAt = now()->addMinutes((int) config('auth.token_expiration', 60 * 24 * 30));

        $plainTextToken = $user->createToken($deviceName, ['*'], $expiresAt)->plainTextToken;

        $this->security->loginSucceeded($user, $securityContext, $deviceName);

        return [
            'user' => $user,
            'access_token' => $plainTextToken,
            'expires_at' => $expiresAt,
        ];
    }
}
