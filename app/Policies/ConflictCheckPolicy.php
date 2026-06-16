<?php

namespace App\Policies;

use App\Models\ConflictCheck;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class ConflictCheckPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('conflict-checks.view');
    }

    public function view(User $user, ConflictCheck $conflictCheck): bool
    {
        return $user->can('conflict-checks.view') && $this->sameOrganization($user, $conflictCheck);
    }

    public function create(User $user): bool
    {
        return $user->can('conflict-checks.create');
    }

    public function update(User $user, ConflictCheck $conflictCheck): bool
    {
        return $user->can('conflict-checks.update') && $this->sameOrganization($user, $conflictCheck);
    }

    public function delete(User $user, ConflictCheck $conflictCheck): bool
    {
        return $user->can('conflict-checks.delete') && $this->sameOrganization($user, $conflictCheck);
    }
}
