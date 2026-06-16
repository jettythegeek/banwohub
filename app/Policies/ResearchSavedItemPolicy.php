<?php

namespace App\Policies;

use App\Models\ResearchSavedItem;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class ResearchSavedItemPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('research.view');
    }

    public function view(User $user, ResearchSavedItem $item): bool
    {
        return $user->can('research.view')
            && $this->sameOrganization($user, $item);
    }

    public function create(User $user): bool
    {
        return $user->can('research.create');
    }

    public function delete(User $user, ResearchSavedItem $item): bool
    {
        return $user->can('research.delete')
            && $this->sameOrganization($user, $item);
    }
}
