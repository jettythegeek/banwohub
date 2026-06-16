<?php

namespace App\Policies;

use App\Models\IntakeForm;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class IntakeFormPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('intake-forms.view');
    }

    public function view(User $user, IntakeForm $intakeForm): bool
    {
        return $user->can('intake-forms.view') && $this->sameOrganization($user, $intakeForm);
    }

    public function create(User $user): bool
    {
        return $user->can('intake-forms.create');
    }

    public function update(User $user, IntakeForm $intakeForm): bool
    {
        return $user->can('intake-forms.update') && $this->sameOrganization($user, $intakeForm);
    }

    public function delete(User $user, IntakeForm $intakeForm): bool
    {
        return $user->can('intake-forms.delete') && $this->sameOrganization($user, $intakeForm);
    }
}
