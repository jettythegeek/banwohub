<?php

namespace App\Policies;

use App\Models\LegalDocument;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class LegalDocumentPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('documents.view');
    }

    public function view(User $user, LegalDocument $legalDocument): bool
    {
        return $user->can('documents.view') && $this->sameOrganization($user, $legalDocument);
    }

    public function create(User $user): bool
    {
        return $user->can('documents.create');
    }

    public function update(User $user, LegalDocument $legalDocument): bool
    {
        return $user->can('documents.update') && $this->sameOrganization($user, $legalDocument);
    }

    public function delete(User $user, LegalDocument $legalDocument): bool
    {
        return $user->can('documents.delete') && $this->sameOrganization($user, $legalDocument);
    }

    public function download(User $user, LegalDocument $legalDocument): bool
    {
        return $user->can('documents.download') && $this->sameOrganization($user, $legalDocument);
    }
}
