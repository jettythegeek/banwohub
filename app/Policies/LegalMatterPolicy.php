<?php

namespace App\Policies;

use App\Models\LegalMatter;
use App\Models\User;

class LegalMatterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cases.view');
    }

    public function view(User $user, LegalMatter $legalMatter): bool
    {
        return $user->can('cases.view') && $this->sameOrganization($user, $legalMatter);
    }

    public function create(User $user): bool
    {
        return $user->can('cases.create');
    }

    public function update(User $user, LegalMatter $legalMatter): bool
    {
        return $user->can('cases.update') && $this->sameOrganization($user, $legalMatter);
    }

    public function delete(User $user, LegalMatter $legalMatter): bool
    {
        return $user->can('cases.delete') && $this->sameOrganization($user, $legalMatter);
    }

    protected function sameOrganization(User $user, LegalMatter $legalMatter): bool
    {
        return $user->organization_id === $legalMatter->organization_id;
    }
}
