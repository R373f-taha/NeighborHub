<?php

declare(strict_types=1);

namespace Modules\Issue\app\Policies;
use Modules\Auth\app\Models\User;
use Modules\Issue\app\Models\Issue;


class IssuePolicy
{


    public function view(
        User $user,
        Issue $issue
    ): bool {


        if ($user->hasAnyRole([
            'super_admin',
            'manager'
        ])) {
            return true;
        }

        if ($user->hasRole('resident')) {

            return $issue->community
                ->members()
                ->where(
                    'user_id',
                    $user->id
                )
                ->exists();

        }


        if ($user->hasRole('provider')) {

            return $issue->assigned_to === $user->id;

        }



        return false;

    }
    public function create(
        User $user
    ): bool {


        return $user->hasAnyRole([
            'super_admin',
            'manager',
            'resident'
        ]);

    }
    public function update(
        User $user,
        Issue $issue
    ): bool {


        if ($user->hasAnyRole([
            'super_admin',
            'manager'
        ])) {

            return true;

        }
        if ($user->hasRole('resident')) {


            return $issue->reported_by === $user->id
                &&
                $issue->assigned_to === null
                &&
                $issue->status->value === 'open';

        }

        return false;

    }

    public function delete(
        User $user,
        Issue $issue
    ): bool {


        return $user->hasAnyRole([
            'super_admin',
            'manager'
        ]);

    }

    public function assign(
        User $user,
        Issue $issue
    ): bool {


        return $user->hasAnyRole([
            'super_admin',
            'manager'
        ]);

    }
    public function updateStatus(
        User $user,
        Issue $issue
    ): bool {


        if ($user->hasAnyRole([
            'super_admin',
            'manager'
        ])) {

            return true;

        }
        if ($user->hasRole('provider')) {
            return $issue->assigned_to === $user->id;

        }

        return false;

    }
    public function addUpdate(
        User $user,
        Issue $issue
    ): bool {


        if ($user->hasAnyRole([
            'super_admin',
            'manager'
        ])) {

            return true;
        }

        if ($user->hasRole('provider')) {
            return $issue->assigned_to === $user->id;
        }
        return false;

    }
    public function addComment(
    User $user,
    Issue $issue
): bool
{
    if ($user->hasAnyRole([
        'super_admin',
        'manager',
    ])) {
        return true;
    }

    if ($user->hasRole('resident')) {
        return $issue->community
            ->members()
            ->where('user_id', $user->id)
            ->exists();
    }

    return false;
}
}