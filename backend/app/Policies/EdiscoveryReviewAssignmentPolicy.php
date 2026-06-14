<?php

namespace App\Policies;

use App\Models\EdiscoveryReviewAssignment;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class EdiscoveryReviewAssignmentPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('ediscovery.view');
    }

    public function view(User $user, EdiscoveryReviewAssignment $ediscoveryReviewAssignment): bool
    {
        return $user->can('ediscovery.view')
            && $this->sameOrganization($user, $ediscoveryReviewAssignment);
    }

    public function create(User $user): bool
    {
        return $user->can('ediscovery.update');
    }

    public function update(User $user, EdiscoveryReviewAssignment $ediscoveryReviewAssignment): bool
    {
        return $user->can('ediscovery.update')
            && $this->sameOrganization($user, $ediscoveryReviewAssignment);
    }

    public function delete(User $user, EdiscoveryReviewAssignment $ediscoveryReviewAssignment): bool
    {
        return $user->can('ediscovery.delete')
            && $this->sameOrganization($user, $ediscoveryReviewAssignment);
    }
}
