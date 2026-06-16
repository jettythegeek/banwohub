<?php

namespace App\Policies;

use App\Models\IntakeSubmission;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class IntakeSubmissionPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('intake-submissions.view');
    }

    public function view(User $user, IntakeSubmission $intakeSubmission): bool
    {
        return $user->can('intake-submissions.view') && $this->sameOrganization($user, $intakeSubmission);
    }

    public function create(User $user): bool
    {
        return $user->can('intake-submissions.create');
    }

    public function update(User $user, IntakeSubmission $intakeSubmission): bool
    {
        return $user->can('intake-submissions.update') && $this->sameOrganization($user, $intakeSubmission);
    }

    public function delete(User $user, IntakeSubmission $intakeSubmission): bool
    {
        return $user->can('intake-submissions.delete') && $this->sameOrganization($user, $intakeSubmission);
    }

    public function convert(User $user, IntakeSubmission $intakeSubmission): bool
    {
        return $user->can('intake-submissions.convert') && $this->sameOrganization($user, $intakeSubmission);
    }
}
