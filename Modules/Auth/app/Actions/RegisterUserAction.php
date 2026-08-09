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


        $this->assignRolePermissions($user,'resident');

        $this->security->registrationSucceeded($user, $securityContext, $deviceName);

        return [
            'user' => $user,
            'access_token' => $plainTextToken,
            'expires_at' => $expiresAt,
        ];
    }

private function assignRolePermissions(User $user, string $role): void
{
    $user->assignRole($role);

    $permissions = match($role) {
        'super_admin' => [
            'view_communities',
            'create_community',
            'update_community',
            'delete_community',
            'view_community_stats',
            'view_residents',
            'approve_resident',
            'reject_resident',
            'suspend_resident',
            'view_posts',
            'create_post',
            'update_post',
            'delete_post',
            'pin_post',
            'view_issues',
            'create_issue',
            'update_issue',
            'delete_issue',
            'assign_issue',
            'update_issue_status',
            'resolve_issue',
            'add_issue_update',
            'comment_issue',
            'view_polls',
            'create_poll',
            'vote_poll',
            'close_poll',
            'view_poll_result',
            'view_announcements',
            'create_announcement',
            'update_announcement',
            'delete_announcement',
            'react_announcement',
            'assign_role',
            'join_community',
        ],
        'manager' => [
            'view_communities',
            'update_community',
            'view_community_stats',
            'view_residents',
            'approve_resident',
            'reject_resident',
            'suspend_resident',
            'view_posts',
            'pin_post',
            'delete_post',
            'view_issues',
            'assign_issue',
            'update_issue',
            'update_issue_status',
            'add_issue_update',
            'resolve_issue',
            'delete_issue',
            'comment_issue',
            'view_polls',
            'create_poll',
            'close_poll',
            'view_poll_result',
            'view_announcements',
            'create_announcement',
            'update_announcement',
            'delete_announcement',
        ],

        'resident' => [
            'view_communities',
            'view_posts',
            'create_post',
            'update_post',
            'view_issues',
            'create_issue',
            'update_issue',
            'comment_issue',
            'view_polls',
            'vote_poll',
            'view_poll_result',
            'view_announcements',
            'react_announcement',
            'join_community',
        ],

        'provider' => [
            'view_issues',
            'update_issue_status',
            'resolve_issue',
            'add_issue_update',
            'view_polls',
            'view_announcements',
        ],

        default => [
            'view_communities',
        ],
    };

    $user->syncPermissions($permissions);
}
}
