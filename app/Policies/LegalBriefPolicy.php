<?php

namespace App\Policies;

use App\Models\LegalBrief;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class LegalBriefPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('briefs.view');
    }

    public function view(User $user, LegalBrief $legalBrief): bool
    {
        return $user->can('briefs.view')
            && $this->sameOrganization($user, $legalBrief);
    }

    public function create(User $user): bool
    {
        return $user->can('briefs.create');
    }

    public function update(User $user, LegalBrief $legalBrief): bool
    {
        return $user->can('briefs.update')
            && $this->sameOrganization($user, $legalBrief);
    }

    public function delete(User $user, LegalBrief $legalBrief): bool
    {
        return $user->can('briefs.delete')
            && $this->sameOrganization($user, $legalBrief);
    }
}
