<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ResolvesOrganization;
use App\Http\Resources\OrganizationResource;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    use ResolvesOrganization;

    public function show(Request $request): OrganizationResource
    {
        $organization = $this->organizationFor($request->user());
        $this->authorize('view', $organization);

        return new OrganizationResource($organization);
    }

    public function update(Request $request): OrganizationResource
    {
        $organization = $this->organizationFor($request->user());
        $this->authorize('update', $organization);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'practice_areas' => ['nullable', 'array'],
            'case_types' => ['nullable', 'array'],
            'jurisdictions' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
        ]);

        $organization->update($data);

        return new OrganizationResource($organization->fresh());
    }
}
