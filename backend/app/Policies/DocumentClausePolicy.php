<?php

namespace App\Policies;

use App\Models\DocumentClause;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class DocumentClausePolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('documents.view');
    }

    public function view(User $user, DocumentClause $clause): bool
    {
        return $user->can('documents.view') && $this->sameOrganization($user, $clause);
    }

    public function create(User $user): bool
    {
        return $user->can('documents.create');
    }

    public function update(User $user, DocumentClause $clause): bool
    {
        return $user->can('documents.update') && $this->sameOrganization($user, $clause);
    }

    public function delete(User $user, DocumentClause $clause): bool
    {
        return $user->can('documents.delete') && $this->sameOrganization($user, $clause);
    }
}
