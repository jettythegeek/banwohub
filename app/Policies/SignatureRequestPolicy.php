<?php

namespace App\Policies;

use App\Models\SignatureRequest;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationResource;

class SignatureRequestPolicy
{
    use AuthorizesOrganizationResource;

    public function viewAny(User $user): bool
    {
        return $user->can('signatures.view') || $user->can('signatures.send');
    }

    public function view(User $user, SignatureRequest $signatureRequest): bool
    {
        if ($user->client_id && $user->can('portal.signatures.view')) {
            return $user->client_id === $signatureRequest->client_id;
        }

        return ($user->can('signatures.view') || $user->can('signatures.send'))
            && $this->sameOrganization($user, $signatureRequest);
    }

    public function create(User $user): bool
    {
        return $user->can('signatures.send');
    }

    public function sign(User $user, SignatureRequest $signatureRequest): bool
    {
        return $user->can('portal.signatures.sign')
            && $user->client_id === $signatureRequest->client_id
            && $signatureRequest->isPending();
    }

    public function decline(User $user, SignatureRequest $signatureRequest): bool
    {
        return $this->sign($user, $signatureRequest);
    }
}
