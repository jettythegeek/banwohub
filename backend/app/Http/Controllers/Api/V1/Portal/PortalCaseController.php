<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ResolvesPortalClient;
use App\Http\Resources\PortalCaseResource;
use App\Models\LegalMatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PortalCaseController extends Controller
{
    use ResolvesPortalClient;

    public function index(Request $request): AnonymousResourceCollection
    {
        $client = $this->portalClientFor($request->user());

        $matters = $this->scopeToPortalClient(LegalMatter::query(), $client)
            ->with(['leadLawyer:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return PortalCaseResource::collection($matters);
    }

    public function show(Request $request, LegalMatter $legalMatter): PortalCaseResource
    {
        $client = $this->portalClientFor($request->user());

        abort_unless(
            $legalMatter->organization_id === $client->organization_id
            && $legalMatter->client_id === $client->id,
            404,
        );

        $legalMatter->load(['leadLawyer:id,name']);

        return new PortalCaseResource($legalMatter);
    }
}
