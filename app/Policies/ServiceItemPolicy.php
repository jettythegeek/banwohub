<?php

namespace App\Policies;

use App\Models\ServiceItem;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class ServiceItemPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('service-items.view');
    }

    public function view(User $user, ServiceItem $serviceItem): bool
    {
        return $user->can('service-items.view')
            && $this->sameOrganization($user, $serviceItem);
    }

    public function create(User $user): bool
    {
        return $user->can('service-items.create');
    }

    public function update(User $user, ServiceItem $serviceItem): bool
    {
        return $user->can('service-items.update')
            && $this->sameOrganization($user, $serviceItem);
    }

    public function delete(User $user, ServiceItem $serviceItem): bool
    {
        return $user->can('service-items.delete')
            && $this->sameOrganization($user, $serviceItem);
    }
}
