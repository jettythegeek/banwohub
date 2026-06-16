<?php

namespace App\Policies;

use App\Models\CommunicationLog;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class CommunicationLogPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('communication-logs.view');
    }

    public function view(User $user, CommunicationLog $communicationLog): bool
    {
        return $user->can('communication-logs.view')
            && $this->sameOrganization($user, $communicationLog);
    }

    public function create(User $user): bool
    {
        return $user->can('communication-logs.create');
    }

    public function update(User $user, CommunicationLog $communicationLog): bool
    {
        return $user->can('communication-logs.update')
            && $this->sameOrganization($user, $communicationLog);
    }

    public function delete(User $user, CommunicationLog $communicationLog): bool
    {
        return $user->can('communication-logs.delete')
            && $this->sameOrganization($user, $communicationLog);
    }
}
