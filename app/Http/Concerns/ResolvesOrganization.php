<?php

namespace App\Http\Concerns;

use App\Models\Organization;
use App\Models\User;

trait ResolvesOrganization
{
    protected function organizationFor(User $user): Organization
    {
        $organization = $user->organization;

        abort_unless($organization, 403, 'User is not linked to an organization.');

        return $organization;
    }
}
