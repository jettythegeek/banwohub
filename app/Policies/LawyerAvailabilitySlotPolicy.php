<?php

namespace App\Policies;

use App\Models\LawyerAvailabilitySlot;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class LawyerAvailabilitySlotPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('appointments.view') || $user->can('appointments.manage-availability');
    }

    public function manage(User $user, ?int $targetUserId = null): bool
    {
        if (! $user->can('appointments.manage-availability')) {
            return false;
        }

        if ($targetUserId === null || $targetUserId === $user->id) {
            return true;
        }

        return $user->can('users.manage');
    }
}
