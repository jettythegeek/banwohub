<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class InvoicePolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('invoices.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.view') && $this->sameOrganization($user, $invoice);
    }

    public function create(User $user): bool
    {
        return $user->can('invoices.create');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.update')
            && $this->sameOrganization($user, $invoice)
            && $invoice->isEditable();
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.delete')
            && $this->sameOrganization($user, $invoice)
            && in_array($invoice->status, ['draft', 'cancelled'], true);
    }

    public function send(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.send')
            && $this->sameOrganization($user, $invoice)
            && in_array($invoice->status, ['draft', 'sent'], true);
    }

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.record-payment')
            && $this->sameOrganization($user, $invoice)
            && ! in_array($invoice->status, ['paid', 'cancelled'], true);
    }

    public function export(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.view') && $this->sameOrganization($user, $invoice);
    }
}
