<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApprovalRequestResource;
use App\Models\ApprovalRequest;
use App\Models\Invoice;
use App\Models\LegalDocument;
use App\Services\ApprovalWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ApprovalRequestController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ApprovalRequest::class);

        $organization = $this->organizationFor($request->user());

        $requests = ApprovalRequest::query()
            ->with(['submitter:id,name', 'reviewer:id,name'])
            ->where('organization_id', $organization->id)
            ->when($request->filled('subject_type'), fn ($q) => $q->where('subject_type', $request->string('subject_type')))
            ->when($request->filled('subject_id'), fn ($q) => $q->where('subject_id', $request->integer('subject_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return ApprovalRequestResource::collection($requests);
    }

    public function store(Request $request, ApprovalWorkflowService $workflow): JsonResponse
    {
        $this->authorize('create', ApprovalRequest::class);

        $organization = $this->organizationFor($request->user());
        $data = $request->validate([
            'subject_type' => ['required', 'string', Rule::in(ApprovalRequest::SUBJECT_TYPES)],
            'subject_id' => ['required', 'integer', 'min:1'],
            'reviewer_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'requires_approval' => ['nullable', 'boolean'],
        ]);

        $subject = $this->resolveSubject(
            $data['subject_type'],
            (int) $data['subject_id'],
            $organization->id,
        );

        $approvalRequest = $workflow->submit(
            subject: $subject,
            submitter: $request->user(),
            reviewerId: $data['reviewer_id'] ?? null,
            notes: $data['notes'] ?? null,
            requiresApproval: $data['requires_approval'] ?? true,
        );

        return (new ApprovalRequestResource($approvalRequest))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ApprovalRequest $approvalRequest): ApprovalRequestResource
    {
        $this->authorize('view', $approvalRequest);

        return new ApprovalRequestResource(
            $approvalRequest->load(['submitter', 'reviewer'])
        );
    }

    public function review(
        Request $request,
        ApprovalRequest $approvalRequest,
        ApprovalWorkflowService $workflow,
    ): ApprovalRequestResource {
        $this->authorize('review', $approvalRequest);

        $data = $request->validate([
            'action' => ['required', 'string', Rule::in(['approve', 'reject', 'request_changes'])],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $updated = $workflow->review(
            request: $approvalRequest,
            reviewer: $request->user(),
            action: $data['action'],
            comment: $data['comment'] ?? null,
        );

        return new ApprovalRequestResource($updated);
    }

    protected function resolveSubject(string $subjectType, int $subjectId, int $organizationId): Invoice|LegalDocument
    {
        return match ($subjectType) {
            'legal_document' => $this->legalDocumentForOrganization($subjectId, $organizationId),
            'invoice' => $this->invoiceForOrganization($subjectId, $organizationId),
            default => abort(422, 'Unsupported subject type.'),
        };
    }

    protected function legalDocumentForOrganization(int $documentId, int $organizationId): LegalDocument
    {
        $document = LegalDocument::query()
            ->where('organization_id', $organizationId)
            ->find($documentId);

        abort_unless($document, 404);

        return $document;
    }

    protected function invoiceForOrganization(int $invoiceId, int $organizationId): Invoice
    {
        $invoice = Invoice::query()
            ->where('organization_id', $organizationId)
            ->find($invoiceId);

        abort_unless($invoice, 404);

        return $invoice;
    }
}
