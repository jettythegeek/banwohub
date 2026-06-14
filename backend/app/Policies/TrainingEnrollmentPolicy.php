<?php

namespace App\Policies;

use App\Models\TrainingEnrollment;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class TrainingEnrollmentPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('training.view') || $user->can('training.assign');
    }

    public function view(User $user, TrainingEnrollment $enrollment): bool
    {
        if ($user->can('training.assign')) {
            return $this->sameOrganization($user, $enrollment);
        }

        return $user->can('training.view')
            && $this->sameOrganization($user, $enrollment)
            && $enrollment->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('training.assign');
    }

    public function update(User $user, TrainingEnrollment $enrollment): bool
    {
        if ($user->can('training.assign') && $this->sameOrganization($user, $enrollment)) {
            return true;
        }

        return $user->can('training.view')
            && $this->sameOrganization($user, $enrollment)
            && $enrollment->user_id === $user->id;
    }
}
