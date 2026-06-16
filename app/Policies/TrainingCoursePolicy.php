<?php

namespace App\Policies;

use App\Models\TrainingCourse;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class TrainingCoursePolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('training.view');
    }

    public function view(User $user, TrainingCourse $course): bool
    {
        return $user->can('training.view')
            && ($course->organization_id === null || $this->sameOrganization($user, $course));
    }

    public function create(User $user): bool
    {
        return $user->can('training.create');
    }

    public function update(User $user, TrainingCourse $course): bool
    {
        return $user->can('training.update')
            && $course->organization_id !== null
            && $this->sameOrganization($user, $course);
    }

    public function delete(User $user, TrainingCourse $course): bool
    {
        return $user->can('training.delete')
            && $course->organization_id !== null
            && $this->sameOrganization($user, $course);
    }
}
