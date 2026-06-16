<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ResolvesPortalClient;
use App\Http\Resources\SignatureRequestResource;
use App\Models\SignatureRequest;
use App\Services\SignatureRequestService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PortalSignatureRequestController extends Controller
{
    use ResolvesPortalClient;

    public function index(Request $request): AnonymousResourceCollection
    {
        $client = $this->portalClientFor($request->user());
        abort_unless($request->user()->can('portal.signatures.view'), 403);

        $requests = SignatureRequest::query()
            ->with(['document:id,name,content_html,version,category', 'legalMatter:id,title,matter_number', 'signedDocument:id,name'])
            ->where('client_id', $client->id)
            ->when($request->filled('legal_matter_id'), fn ($q) => $q->where('legal_matter_id', $request->integer('legal_matter_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return SignatureRequestResource::collection($requests);
    }

    public function show(Request $request, SignatureRequest $signatureRequest): SignatureRequestResource
    {
        $client = $this->portalClientFor($request->user());
        abort_unless($request->user()->can('portal.signatures.view'), 403);
        abort_unless($signatureRequest->client_id === $client->id, 404);

        return new SignatureRequestResource(
            $signatureRequest->load(['document', 'legalMatter', 'signedDocument'])
        );
    }

    public function sign(
        Request $request,
        SignatureRequest $signatureRequest,
        SignatureRequestService $service,
    ): SignatureRequestResource {
        $this->authorize('sign', $signatureRequest);

        $data = $request->validate([
            'field_values' => ['required', 'array'],
            'method' => ['nullable', 'string', 'in:canvas,typed'],
        ]);

        $updated = $service->sign(
            request: $signatureRequest,
            signer: $request->user(),
            fieldValues: $data['field_values'],
            ip: $request->ip(),
            method: $data['method'] ?? 'canvas',
        );

        return new SignatureRequestResource($updated);
    }

    public function decline(
        Request $request,
        SignatureRequest $signatureRequest,
        SignatureRequestService $service,
    ): SignatureRequestResource {
        $this->authorize('decline', $signatureRequest);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $updated = $service->decline(
            request: $signatureRequest,
            signer: $request->user(),
            ip: $request->ip(),
            reason: $data['reason'] ?? null,
        );

        return new SignatureRequestResource($updated);
    }
}
