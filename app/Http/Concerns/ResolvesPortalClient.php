<?php

namespace App\Http\Concerns;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait ResolvesPortalClient
{
    protected function portalClientFor(User $user): Client
    {
        $client = $user->client;

        abort_unless($client, 403, 'Your account is not linked to a client record.');

        return $client;
    }

    /**
     * @param  Builder<\App\Models\LegalMatter>  $query
     */
    protected function scopeToPortalClient(Builder $query, Client $client): Builder
    {
        return $query
            ->where('organization_id', $client->organization_id)
            ->where('client_id', $client->id);
    }
}
