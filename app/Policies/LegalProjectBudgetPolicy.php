<?php

namespace App\Policies;

use App\Models\LegalProjectBudget;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class LegalProjectBudgetPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('projects.view');
    }

    public function view(User $user, LegalProjectBudget $budget): bool
    {
        return $user->can('projects.view') && $this->sameOrganization($user, $budget);
    }

    public function create(User $user): bool
    {
        return $user->can('projects.create');
    }

    public function update(User $user, LegalProjectBudget $budget): bool
    {
        return $user->can('projects.update') && $this->sameOrganization($user, $budget);
    }

    public function delete(User $user, LegalProjectBudget $budget): bool
    {
        return $user->can('projects.delete') && $this->sameOrganization($user, $budget);
    }
}
