<?php

namespace App\Policies;

use App\Models\LegalMotion;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class LegalMotionPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('motions.view');
    }

    public function view(User $user, LegalMotion $legalMotion): bool
    {
        return $user->can('motions.view')
            && $this->sameOrganization($user, $legalMotion);
    }

    public function create(User $user): bool
    {
        return $user->can('motions.create');
    }

    public function update(User $user, LegalMotion $legalMotion): bool
    {
        return $user->can('motions.update')
            && $this->sameOrganization($user, $legalMotion);
    }

    public function delete(User $user, LegalMotion $legalMotion): bool
    {
        return $user->can('motions.delete')
            && $this->sameOrganization($user, $legalMotion);
    }
}
