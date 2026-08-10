<?php

declare(strict_types=1);

namespace Modules\Poll\app\Policies;

use Modules\Auth\app\Models\User;
use Modules\Poll\app\Enums\PollStatus;
use Modules\Poll\app\Models\Poll;

class PollPolicy
{
    public function view(User $user, Poll $poll): bool
    {
        return true; // All authenticated users
    }

    public function create(User $user, $community): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isManager()) {
            return $community->managers()->where('manager_id', $user->id)->exists();
        }

        return false;
    }

    public function activate(User $user, Poll $poll): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isManager()) {
            return $poll->community->managers()->where('manager_id', $user->id)->exists();
        }

        return false;
    }

    public function close(User $user, Poll $poll): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isManager()) {
            return $poll->community->managers()->where('manager_id', $user->id)->exists();
        }

        return false;
    }

    public function vote(User $user, Poll $poll): bool
    {
        if (!$user->isResident()) {
            return false;
        }

        $resident = $user->currentResident;

        if (!$resident || $resident->community_id !== $poll->community_id) {
            return false;
        }

        if ($poll->status !== PollStatus::Active) {
            return false;
        }

        return true;
    }

    public function viewResults(User $user, Poll $poll): bool
    {
        // Manager can always view results
        if ($user->isManager() || $user->isSuperAdmin()) {
            return $poll->community->managers()->where('manager_id', $user->id)->exists();
        }

        // Resident can view results only after poll is closed
        if ($user->isResident()) {
            $resident = $user->currentResident;
            return $resident && $resident->community_id === $poll->community_id && $poll->status === PollStatus::Closed;
        }

        return false;
    }
}
