<?php

namespace App\Policies;

use App\Models\EdiscoveryDocument;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class EdiscoveryDocumentPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('ediscovery.view');
    }

    public function view(User $user, EdiscoveryDocument $ediscoveryDocument): bool
    {
        return $user->can('ediscovery.view')
            && $this->sameOrganization($user, $ediscoveryDocument);
    }

    public function create(User $user): bool
    {
        return $user->can('ediscovery.create');
    }

    public function update(User $user, EdiscoveryDocument $ediscoveryDocument): bool
    {
        return $user->can('ediscovery.update')
            && $this->sameOrganization($user, $ediscoveryDocument);
    }

    public function delete(User $user, EdiscoveryDocument $ediscoveryDocument): bool
    {
        return $user->can('ediscovery.delete')
            && $this->sameOrganization($user, $ediscoveryDocument);
    }
}
