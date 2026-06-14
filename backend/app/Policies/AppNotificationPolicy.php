<?php

namespace App\Policies;

use App\Models\AppNotification;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class AppNotificationPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('notifications.view');
    }

    public function view(User $user, AppNotification $appNotification): bool
    {
        return $user->can('notifications.view')
            && $this->sameOrganization($user, $appNotification)
            && $appNotification->user_id === $user->id;
    }

    public function update(User $user, AppNotification $appNotification): bool
    {
        return $user->can('notifications.update')
            && $this->sameOrganization($user, $appNotification)
            && $appNotification->user_id === $user->id;
    }
}
