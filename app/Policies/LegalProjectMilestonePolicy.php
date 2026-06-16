<?php

namespace App\Policies;

use App\Models\LegalProjectMilestone;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class LegalProjectMilestonePolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('projects.view');
    }

    public function view(User $user, LegalProjectMilestone $milestone): bool
    {
        return $user->can('projects.view') && $this->sameOrganization($user, $milestone);
    }

    public function create(User $user): bool
    {
        return $user->can('projects.create');
    }

    public function update(User $user, LegalProjectMilestone $milestone): bool
    {
        return $user->can('projects.update') && $this->sameOrganization($user, $milestone);
    }

    public function delete(User $user, LegalProjectMilestone $milestone): bool
    {
        return $user->can('projects.delete') && $this->sameOrganization($user, $milestone);
    }
}
