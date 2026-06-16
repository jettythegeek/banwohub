<?php

namespace App\Policies;

use App\Models\CourtFormTemplate;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class CourtFormTemplatePolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('court-forms.view');
    }

    public function view(User $user, CourtFormTemplate $courtFormTemplate): bool
    {
        if ($courtFormTemplate->organization_id === null) {
            return $user->can('court-forms.view');
        }

        return $user->can('court-forms.view')
            && $this->sameOrganization($user, $courtFormTemplate);
    }

    public function create(User $user): bool
    {
        return $user->can('court-forms.create');
    }

    public function update(User $user, CourtFormTemplate $courtFormTemplate): bool
    {
        if ($courtFormTemplate->organization_id === null) {
            return false;
        }

        return $user->can('court-forms.update')
            && $this->sameOrganization($user, $courtFormTemplate);
    }

    public function delete(User $user, CourtFormTemplate $courtFormTemplate): bool
    {
        if ($courtFormTemplate->organization_id === null) {
            return false;
        }

        return $user->can('court-forms.update')
            && $this->sameOrganization($user, $courtFormTemplate);
    }
}
