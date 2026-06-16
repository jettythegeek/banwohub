<?php

namespace App\Policies;

use App\Models\TrustLedgerEntry;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class TrustLedgerEntryPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('trust.view');
    }

    public function view(User $user, TrustLedgerEntry $trustLedgerEntry): bool
    {
        return $user->can('trust.view') && $this->sameOrganization($user, $trustLedgerEntry);
    }

    public function create(User $user): bool
    {
        return $user->can('trust.create');
    }

    public function delete(User $user, TrustLedgerEntry $trustLedgerEntry): bool
    {
        return $user->can('trust.delete') && $this->sameOrganization($user, $trustLedgerEntry);
    }
}
