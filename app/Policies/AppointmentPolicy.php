<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class AppointmentPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('appointments.view');
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $user->can('appointments.view') && $this->sameOrganization($user, $appointment);
    }

    public function create(User $user): bool
    {
        return $user->can('appointments.create');
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $user->can('appointments.update') && $this->sameOrganization($user, $appointment);
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $user->can('appointments.delete') && $this->sameOrganization($user, $appointment);
    }
}
