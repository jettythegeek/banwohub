<?php

namespace App\Policies;

use App\Models\LegalTask;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class LegalTaskPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('tasks.view');
    }

    public function view(User $user, LegalTask $legalTask): bool
    {
        return $user->can('tasks.view') && $this->sameOrganization($user, $legalTask);
    }

    public function create(User $user): bool
    {
        return $user->can('tasks.create');
    }

    public function update(User $user, LegalTask $legalTask): bool
    {
        return $user->can('tasks.update') && $this->sameOrganization($user, $legalTask);
    }

    public function delete(User $user, LegalTask $legalTask): bool
    {
        return $user->can('tasks.delete') && $this->sameOrganization($user, $legalTask);
    }
}
