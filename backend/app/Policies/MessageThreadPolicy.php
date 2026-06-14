<?php

namespace App\Policies;

use App\Models\MessageThread;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class MessageThreadPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('messages.view');
    }

    public function view(User $user, MessageThread $messageThread): bool
    {
        return $user->can('messages.view') && $this->sameOrganization($user, $messageThread);
    }

    public function create(User $user): bool
    {
        return $user->can('messages.create');
    }

    public function sendMessage(User $user, MessageThread $messageThread): bool
    {
        return $user->can('messages.create')
            && $this->sameOrganization($user, $messageThread);
    }

    public function markRead(User $user, MessageThread $messageThread): bool
    {
        return $user->can('messages.view')
            && $this->sameOrganization($user, $messageThread);
    }
}
