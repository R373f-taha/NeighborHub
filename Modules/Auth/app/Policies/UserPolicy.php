<?php

declare(strict_types=1);

namespace Modules\Auth\app\Policies;

use Modules\Auth\app\Models\User;

class UserPolicy
{
    /**
     * Only a user holding the assign_role permission may change another user's
     * role. A user may never change their own role.
     */
    public function assignRole(User $actor, User $target): bool
    {
        return $actor->can('assign_role')
            && $actor->id !== $target->id;
    }
}
