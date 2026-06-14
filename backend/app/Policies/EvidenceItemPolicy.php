<?php

namespace App\Policies;

use App\Models\EvidenceItem;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class EvidenceItemPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('evidence.view');
    }

    public function view(User $user, EvidenceItem $evidenceItem): bool
    {
        return $user->can('evidence.view')
            && $this->sameOrganization($user, $evidenceItem);
    }

    public function create(User $user): bool
    {
        return $user->can('evidence.create');
    }

    public function update(User $user, EvidenceItem $evidenceItem): bool
    {
        return $user->can('evidence.update')
            && $this->sameOrganization($user, $evidenceItem);
    }

    public function delete(User $user, EvidenceItem $evidenceItem): bool
    {
        return $user->can('evidence.delete')
            && $this->sameOrganization($user, $evidenceItem);
    }
}
