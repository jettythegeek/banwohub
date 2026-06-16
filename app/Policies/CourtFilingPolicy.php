<?php

namespace App\Policies;

use App\Models\CourtFiling;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class CourtFilingPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('filings.view');
    }

    public function view(User $user, CourtFiling $courtFiling): bool
    {
        return $user->can('filings.view')
            && $this->sameOrganization($user, $courtFiling);
    }

    public function create(User $user): bool
    {
        return $user->can('filings.create');
    }

    public function update(User $user, CourtFiling $courtFiling): bool
    {
        return $user->can('filings.update')
            && $this->sameOrganization($user, $courtFiling);
    }

    public function delete(User $user, CourtFiling $courtFiling): bool
    {
        return $user->can('filings.delete')
            && $this->sameOrganization($user, $courtFiling);
    }
}
