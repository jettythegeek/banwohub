<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait AuthorizesOrganizationResource
{
    protected function sameOrganization(User $user, object $resource): bool
    {
        return $user->organization_id === $resource->organization_id;
    }
}
