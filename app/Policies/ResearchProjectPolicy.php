<?php

namespace App\Policies;

use App\Models\ResearchProject;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class ResearchProjectPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('research.view');
    }

    public function view(User $user, ResearchProject $project): bool
    {
        return $user->can('research.view')
            && $this->sameOrganization($user, $project);
    }

    public function create(User $user): bool
    {
        return $user->can('research.create');
    }

    public function update(User $user, ResearchProject $project): bool
    {
        return $user->can('research.update')
            && $this->sameOrganization($user, $project);
    }

    public function delete(User $user, ResearchProject $project): bool
    {
        return $user->can('research.delete')
            && $this->sameOrganization($user, $project);
    }
}
