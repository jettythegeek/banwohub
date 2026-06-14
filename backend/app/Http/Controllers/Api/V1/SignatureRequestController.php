<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\SignatureRequestResource;
use App\Models\LegalDocument;
use App\Models\SignatureRequest;
use App\Services\SignatureRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class SignatureRequestController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SignatureRequest::class);

        $organization = $this->organizationFor($request->user());

        $requests = SignatureRequest::query()
            ->with(['document:id,name,version', 'legalMatter:id,title,matter_number', 'client:id,name,email', 'sender:id,name', 'signedDocument:id,name'])
            ->where('organization_id', $organization->id)
            ->when($request->filled('legal_matter_id'), fn ($q) => $q->where('legal_matter_id', $request->integer('legal_matter_id')))
            ->when($request->filled('document_id'), fn ($q) => $q->where('document_id', $request->integer('document_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return SignatureRequestResource::collection($requests);
    }

    public function store(Request $request, SignatureRequestService $service): JsonResponse
    {
        $this->authorize('create', SignatureRequest::class);

        $organization = $this->organizationFor($request->user());
        $data = $request->validate([
            'document_id' => ['required', 'integer', 'min:1'],
            'message' => ['nullable', 'string', 'max:2000'],
            'fields' => ['nullable', 'array'],
            'fields.*.id' => ['required_with:fields', 'string', 'max:64'],
            'fields.*.type' => ['required_with:fields', 'string', Rule::in(['signature', 'date', 'text', 'initials'])],
            'fields.*.label' => ['required_with:fields', 'string', 'max:255'],
            'fields.*.required' => ['nullable', 'boolean'],
        ]);

        $document = LegalDocument::query()
            ->where('organization_id', $organization->id)
            ->where('document_type', 'case_document')
            ->findOrFail((int) $data['document_id']);

        $signatureRequest = $service->send(
            document: $document,
            sender: $request->user(),
            message: $data['message'] ?? null,
            fields: $data['fields'] ?? null,
        );

        return (new SignatureRequestResource($signatureRequest))
            ->response()
            ->setStatusCode(201);
    }

    public function show(SignatureRequest $signatureRequest): SignatureRequestResource
    {
        $this->authorize('view', $signatureRequest);

        return new SignatureRequestResource(
            $signatureRequest->load(['document', 'legalMatter', 'client', 'sender', 'signedDocument'])
        );
    }
}
