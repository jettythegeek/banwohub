<?php

namespace App\Policies;

use App\Models\EdiscoveryCollection;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class EdiscoveryCollectionPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('ediscovery.view');
    }

    public function view(User $user, EdiscoveryCollection $ediscoveryCollection): bool
    {
        return $user->can('ediscovery.view')
            && $this->sameOrganization($user, $ediscoveryCollection);
    }

    public function create(User $user): bool
    {
        return $user->can('ediscovery.create');
    }

    public function update(User $user, EdiscoveryCollection $ediscoveryCollection): bool
    {
        return $user->can('ediscovery.update')
            && $this->sameOrganization($user, $ediscoveryCollection);
    }

    public function delete(User $user, EdiscoveryCollection $ediscoveryCollection): bool
    {
        return $user->can('ediscovery.delete')
            && $this->sameOrganization($user, $ediscoveryCollection);
    }
}
