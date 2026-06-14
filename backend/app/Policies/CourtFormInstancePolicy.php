<?php

namespace App\Policies;

use App\Models\CourtFormInstance;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class CourtFormInstancePolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('court-forms.view');
    }

    public function view(User $user, CourtFormInstance $courtFormInstance): bool
    {
        return $user->can('court-forms.view')
            && $this->sameOrganization($user, $courtFormInstance);
    }

    public function create(User $user): bool
    {
        return $user->can('court-forms.create');
    }

    public function update(User $user, CourtFormInstance $courtFormInstance): bool
    {
        return $user->can('court-forms.update')
            && $this->sameOrganization($user, $courtFormInstance);
    }

    public function delete(User $user, CourtFormInstance $courtFormInstance): bool
    {
        return $user->can('court-forms.update')
            && $this->sameOrganization($user, $courtFormInstance);
    }
}
