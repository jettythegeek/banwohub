<?php

namespace App\Policies;

use App\Models\ApprovalRequest;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class ApprovalRequestPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('approvals.view');
    }

    public function view(User $user, ApprovalRequest $approvalRequest): bool
    {
        return $user->can('approvals.view') && $this->sameOrganization($user, $approvalRequest);
    }

    public function create(User $user): bool
    {
        return $user->can('approvals.submit');
    }

    public function review(User $user, ApprovalRequest $approvalRequest): bool
    {
        return $user->can('approvals.review') && $this->sameOrganization($user, $approvalRequest);
    }
}
