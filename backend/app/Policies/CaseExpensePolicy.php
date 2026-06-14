<?php

namespace App\Policies;

use App\Models\CaseExpense;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class CaseExpensePolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('expenses.view') || $user->can('expenses.view-all');
    }

    public function view(User $user, CaseExpense $caseExpense): bool
    {
        if (! $this->sameOrganization($user, $caseExpense)) {
            return false;
        }

        if ($user->can('expenses.view-all')) {
            return true;
        }

        return $user->can('expenses.view') && $this->owns($user, $caseExpense);
    }

    public function create(User $user): bool
    {
        return $user->can('expenses.create');
    }

    public function update(User $user, CaseExpense $caseExpense): bool
    {
        if (! $this->sameOrganization($user, $caseExpense)) {
            return false;
        }

        if ($caseExpense->invoice_id !== null) {
            return false;
        }

        if ($user->can('expenses.update-all')) {
            return true;
        }

        return $user->can('expenses.update') && $this->owns($user, $caseExpense);
    }

    public function delete(User $user, CaseExpense $caseExpense): bool
    {
        if (! $this->sameOrganization($user, $caseExpense)) {
            return false;
        }

        if ($caseExpense->invoice_id !== null) {
            return false;
        }

        if ($user->can('expenses.delete-all')) {
            return true;
        }

        return $user->can('expenses.delete') && $this->owns($user, $caseExpense);
    }

    protected function owns(User $user, CaseExpense $caseExpense): bool
    {
        return $user->id === $caseExpense->user_id;
    }
}
