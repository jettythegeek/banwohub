<?php

namespace App\Policies;

use App\Models\CaseNote;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class CaseNotePolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('case-notes.view');
    }

    public function view(User $user, CaseNote $caseNote): bool
    {
        return $user->can('case-notes.view') && $this->sameOrganization($user, $caseNote);
    }

    public function create(User $user): bool
    {
        return $user->can('case-notes.create');
    }

    public function update(User $user, CaseNote $caseNote): bool
    {
        return $user->can('case-notes.update') && $this->sameOrganization($user, $caseNote);
    }

    public function delete(User $user, CaseNote $caseNote): bool
    {
        return $user->can('case-notes.delete') && $this->sameOrganization($user, $caseNote);
    }
}
