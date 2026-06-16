<?php

namespace App\Policies;

use App\Models\TimeEntry;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class TimeEntryPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('time-entries.view') || $user->can('time-entries.view-all');
    }

    public function view(User $user, TimeEntry $timeEntry): bool
    {
        if (! $this->sameOrganization($user, $timeEntry)) {
            return false;
        }

        if ($user->can('time-entries.view-all')) {
            return true;
        }

        return $user->can('time-entries.view') && $this->owns($user, $timeEntry);
    }

    public function create(User $user): bool
    {
        return $user->can('time-entries.create');
    }

    public function update(User $user, TimeEntry $timeEntry): bool
    {
        if (! $this->sameOrganization($user, $timeEntry)) {
            return false;
        }

        // Approved entries are locked unless the user can manage all entries.
        if ($timeEntry->status === 'approved' && ! $user->can('time-entries.update-all')) {
            return false;
        }

        if ($user->can('time-entries.update-all')) {
            return true;
        }

        return $user->can('time-entries.update') && $this->owns($user, $timeEntry);
    }

    public function delete(User $user, TimeEntry $timeEntry): bool
    {
        if (! $this->sameOrganization($user, $timeEntry)) {
            return false;
        }

        if ($user->can('time-entries.delete-all')) {
            return true;
        }

        return $user->can('time-entries.delete') && $this->owns($user, $timeEntry);
    }

    public function approve(User $user, TimeEntry $timeEntry): bool
    {
        return $user->can('time-entries.approve') && $this->sameOrganization($user, $timeEntry);
    }

    protected function owns(User $user, TimeEntry $timeEntry): bool
    {
        return $user->id === $timeEntry->user_id;
    }
}
