<?php

namespace App\Policies;

use App\Models\EdiscoveryTag;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class EdiscoveryTagPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('ediscovery.view');
    }

    public function view(User $user, EdiscoveryTag $ediscoveryTag): bool
    {
        return $user->can('ediscovery.view')
            && $this->sameOrganization($user, $ediscoveryTag);
    }

    public function create(User $user): bool
    {
        return $user->can('ediscovery.create');
    }

    public function update(User $user, EdiscoveryTag $ediscoveryTag): bool
    {
        return $user->can('ediscovery.update')
            && $this->sameOrganization($user, $ediscoveryTag);
    }

    public function delete(User $user, EdiscoveryTag $ediscoveryTag): bool
    {
        return $user->can('ediscovery.delete')
            && $this->sameOrganization($user, $ediscoveryTag);
    }
}
