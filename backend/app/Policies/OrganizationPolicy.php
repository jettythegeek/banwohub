<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        return $user->organization_id === $organization->id;
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->can('organization.manage')
            && $user->organization_id === $organization->id;
    }
}
