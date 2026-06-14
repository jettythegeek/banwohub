<?php

namespace App\Support;

use App\Models\AppNotification;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Collection;

class InAppNotifier
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function notifyUser(User $user, string $type, string $title, ?string $body = null, array $data = [], ?User $actor = null): AppNotification
    {
        return AppNotification::query()->create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'actor_id' => $actor?->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => NotificationDeepLink::enrich($type, $data),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function notifyPermission(Organization $organization, string $permission, string $type, string $title, ?string $body = null, array $data = [], ?User $actor = null): void
    {
        $this->usersWithPermission($organization, $permission)
            ->each(fn (User $user) => $this->notifyUser($user, $type, $title, $body, $data, $actor));
    }

    /**
     * @return Collection<int, User>
     */
    protected function usersWithPermission(Organization $organization, string $permission): Collection
    {
        return User::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user) => $user->can($permission))
            ->values();
    }
}
