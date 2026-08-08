<?php

declare(strict_types=1);

namespace Modules\Auth\app\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Auth\app\Exceptions\LastSuperAdminException;
use Modules\Auth\app\Models\User;
use Modules\Auth\app\Support\AuthSecurityContext;

class AssignUserRoleAction
{
   
    public function execute(User $actor, User $target, string $newRole, AuthSecurityContext $context): User
    {
        return DB::transaction(function () use ($actor, $target, $newRole, $context): User {

            $superAdminIds = $this->lockedSuperAdminIds();

            $target = User::lockForUpdate()->findOrFail($target->id);


            $isCurrentSuperAdmin = $target->hasRole('super_admin');
            $oldRole = $target->getRoleNames()->first();

 
            if ($isCurrentSuperAdmin && $newRole !== 'super_admin' && $superAdminIds->count() <= 1) {
                $this->log($actor, $target, $oldRole, $newRole, $context, 'denied_last_super_admin');

                throw LastSuperAdminException::forDemotion();
            }

            $target->syncRoles([$newRole]);

            $target->role = $newRole;
            $target->save();

            $this->log($actor, $target, $oldRole, $newRole, $context, 'success');

            return $target->refresh();
        });
    }

  
    private function lockedSuperAdminIds(): Collection
    {
        return User::role('super_admin')
            ->select('users.id')
            ->orderBy('users.id')
            ->lockForUpdate()
            ->pluck('users.id');
    }

    private function log(User $actor, User $target, ?string $oldRole, string $newRole, AuthSecurityContext $context, string $result = 'success'): void
    {
        Log::channel('security')->info('user.role.updated', [
            'actor_user_id' => $actor->id,
            'target_user_id' => $target->id,
            'old_role' => $oldRole,
            'new_role' => $newRole,
            'result' => $result,
            'ip' => $context->ip ?? 'unknown',
            'user_agent' => $context->userAgent,
        ]);
    }
}
