<?php

declare(strict_types=1);

namespace Modules\Auth\app\Actions;

use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Auth\app\Enums\UserRole;
use Modules\Auth\app\Models\User;
use Modules\Auth\app\Support\AuthSecurityContext;
use Modules\Auth\app\Support\AuthSecurityLogger;

class RegisterUserAction
{
    public function __construct(private readonly AuthSecurityLogger $security) {}

    /**
     * @return array{user: User, access_token: string, expires_at: Carbon}
     */
    public function execute(string $name, string $email, ?string $phone, string $password, string $deviceName, AuthSecurityContext $securityContext): array
    {
        $expiresAt = now()->addMinutes((int) config('auth.token_expiration', 60 * 24 * 30));

        [$user, $plainTextToken] = DB::transaction(function () use ($name, $email, $phone, $password, $deviceName, $expiresAt): array {
            $user = new User;
            $user->name = $name;
            $user->email = $email;
            $user->phone = $phone;
            // The hashed cast hashes this on assignment, so it must never be pre-hashed.
            $user->password = $password;
            $user->role = UserRole::Resident;
            $user->is_active = true;
            $user->save();

            $plainTextToken = $user->createToken($deviceName, ['*'], $expiresAt)->plainTextToken;

            return [$user, $plainTextToken];
        });

        event(new Registered($user));

        $this->security->registrationSucceeded($user, $securityContext, $deviceName);

        return [
            'user' => $user,
            'access_token' => $plainTextToken,
            'expires_at' => $expiresAt,
        ];
    }
}
