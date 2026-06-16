<?php

namespace App\Policies;

use App\Models\DocumentFolder;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class DocumentFolderPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('documents.view');
    }

    public function view(User $user, DocumentFolder $documentFolder): bool
    {
        return $user->can('documents.view') && $this->sameOrganization($user, $documentFolder);
    }

    public function create(User $user): bool
    {
        return $user->can('documents.create');
    }

    public function update(User $user, DocumentFolder $documentFolder): bool
    {
        return $user->can('documents.update') && $this->sameOrganization($user, $documentFolder);
    }

    public function delete(User $user, DocumentFolder $documentFolder): bool
    {
        return $user->can('documents.delete') && $this->sameOrganization($user, $documentFolder);
    }
}
