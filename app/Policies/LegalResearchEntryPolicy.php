<?php

namespace App\Policies;

use App\Models\LegalResearchEntry;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class LegalResearchEntryPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('research.view');
    }

    public function view(User $user, LegalResearchEntry $entry): bool
    {
        return $user->can('research.view')
            && ($entry->organization_id === null || $this->sameOrganization($user, $entry));
    }

    public function create(User $user): bool
    {
        return $user->can('research.create');
    }

    public function update(User $user, LegalResearchEntry $entry): bool
    {
        return $user->can('research.update')
            && $entry->organization_id !== null
            && $this->sameOrganization($user, $entry);
    }

    public function delete(User $user, LegalResearchEntry $entry): bool
    {
        return $user->can('research.delete')
            && $entry->organization_id !== null
            && $this->sameOrganization($user, $entry);
    }
}
