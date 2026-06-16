<?php

namespace App\Policies;

use App\Models\AiGovernanceLog;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class AiGovernancePolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('ai.governance.view');
    }

    public function view(User $user, AiGovernanceLog $log): bool
    {
        return $user->can('ai.governance.view') && $this->sameOrganization($user, $log);
    }
}
