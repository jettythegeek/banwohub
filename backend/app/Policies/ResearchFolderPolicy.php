<?php

namespace App\Policies;

use App\Models\ResearchFolder;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class ResearchFolderPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('research.view');
    }

    public function view(User $user, ResearchFolder $folder): bool
    {
        return $user->can('research.view')
            && $this->sameOrganization($user, $folder);
    }

    public function create(User $user): bool
    {
        return $user->can('research.create');
    }

    public function update(User $user, ResearchFolder $folder): bool
    {
        return $user->can('research.update')
            && $this->sameOrganization($user, $folder);
    }

    public function delete(User $user, ResearchFolder $folder): bool
    {
        return $user->can('research.delete')
            && $this->sameOrganization($user, $folder);
    }
}
