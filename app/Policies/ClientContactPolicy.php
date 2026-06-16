<?php

namespace App\Policies;

use App\Models\ClientContact;
use App\Models\User;

class ClientContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('client-contacts.view');
    }

    public function view(User $user, ClientContact $clientContact): bool
    {
        return $user->can('client-contacts.view')
            && $this->belongsToUserOrganization($user, $clientContact);
    }

    public function create(User $user): bool
    {
        return $user->can('client-contacts.create');
    }

    public function update(User $user, ClientContact $clientContact): bool
    {
        return $user->can('client-contacts.update')
            && $this->belongsToUserOrganization($user, $clientContact);
    }

    public function delete(User $user, ClientContact $clientContact): bool
    {
        return $user->can('client-contacts.delete')
            && $this->belongsToUserOrganization($user, $clientContact);
    }

    private function belongsToUserOrganization(User $user, ClientContact $clientContact): bool
    {
        $clientContact->loadMissing('client');

        return $clientContact->client !== null
            && $user->organization_id === $clientContact->client->organization_id;
    }
}
