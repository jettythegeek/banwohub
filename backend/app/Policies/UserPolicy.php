<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('users.manage');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->can('users.manage') && $this->sameOrganization($actor, $target);
    }

    public function create(User $actor): bool
    {
        return $actor->can('users.manage');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->can('users.manage') && $this->sameOrganization($actor, $target);
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->can('users.manage') && $this->sameOrganization($actor, $target);
    }

    protected function sameOrganization(User $actor, User $target): bool
    {
        return $actor->organization_id === $target->organization_id;
    }
}
