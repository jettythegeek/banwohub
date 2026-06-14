<?php

namespace App\Policies;

use App\Models\CalendarEvent;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class CalendarEventPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('calendar.view');
    }

    public function view(User $user, CalendarEvent $calendarEvent): bool
    {
        return $user->can('calendar.view') && $this->sameOrganization($user, $calendarEvent);
    }

    public function create(User $user): bool
    {
        return $user->can('calendar.create');
    }

    public function update(User $user, CalendarEvent $calendarEvent): bool
    {
        return $user->can('calendar.update') && $this->sameOrganization($user, $calendarEvent);
    }

    public function delete(User $user, CalendarEvent $calendarEvent): bool
    {
        return $user->can('calendar.delete') && $this->sameOrganization($user, $calendarEvent);
    }
}
