<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\EdiscoveryReviewAssignmentResource;
use App\Models\EdiscoveryDocument;
use App\Models\EdiscoveryReviewAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EdiscoveryReviewAssignmentController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', EdiscoveryReviewAssignment::class);

        $organization = $this->organizationFor($request->user());

        $assignments = EdiscoveryReviewAssignment::query()
            ->with(['reviewer:id,name', 'assigner:id,name', 'document:id,title,review_status'])
            ->where('organization_id', $organization->id)
            ->when($request->filled('ediscovery_document_id'), fn ($q) => $q->where('ediscovery_document_id', $request->integer('ediscovery_document_id')))
            ->when($request->filled('reviewer_id'), fn ($q) => $q->where('reviewer_id', $request->integer('reviewer_id')))
            ->when($request->filled('review_status'), fn ($q) => $q->where('review_status', $request->string('review_status')))
            ->when($request->filled('legal_matter_id'), function ($q) use ($request): void {
                $q->whereHas('document', fn ($doc) => $doc->where('legal_matter_id', $request->integer('legal_matter_id')));
            })
            ->orderByDesc('assigned_at')
            ->paginate($request->integer('per_page', 50));

        return EdiscoveryReviewAssignmentResource::collection($assignments)
            ->additional(['meta' => [
                'statuses' => EdiscoveryReviewAssignment::STATUSES,
            ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', EdiscoveryReviewAssignment::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);
        $data = $request->validate([
            'ediscovery_document_id' => ['required', 'integer', 'exists:ediscovery_documents,id'],
            'reviewer_id' => ['required', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $document = EdiscoveryDocument::query()
            ->where('organization_id', $organization->id)
            ->findOrFail($data['ediscovery_document_id']);

        $this->userForOrganization((int) $data['reviewer_id'], $organization->id);

        if (EdiscoveryReviewAssignment::query()
            ->where('ediscovery_document_id', $document->id)
            ->where('reviewer_id', $data['reviewer_id'])
            ->exists()) {
            throw ValidationException::withMessages([
                'reviewer_id' => ['This reviewer is already assigned to the document.'],
            ]);
        }

        $assignment = EdiscoveryReviewAssignment::query()->create([
            'organization_id' => $organization->id,
            'ediscovery_document_id' => $document->id,
            'reviewer_id' => $data['reviewer_id'],
            'review_status' => 'assigned',
            'notes' => $data['notes'] ?? null,
            'assigned_by' => $user->id,
            'assigned_at' => now(),
        ]);

        if ($document->review_status === 'pending') {
            $document->update(['review_status' => 'in_progress']);
        }

        activity('ediscovery')
            ->performedOn($document)
            ->causedBy($user)
            ->withProperties(['reviewer_id' => $data['reviewer_id']])
            ->log('Reviewer assigned to discovery document');

        return (new EdiscoveryReviewAssignmentResource($assignment->load(['reviewer', 'assigner', 'document'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, EdiscoveryReviewAssignment $ediscoveryReviewAssignment): EdiscoveryReviewAssignmentResource
    {
        $this->authorize('update', $ediscoveryReviewAssignment);

        $data = $request->validate([
            'review_status' => ['sometimes', 'string', Rule::in(EdiscoveryReviewAssignment::STATUSES)],
            'notes' => ['nullable', 'string'],
        ]);

        if (isset($data['review_status'])) {
            if ($data['review_status'] === 'completed') {
                $data['completed_at'] = now();
            } elseif ($data['review_status'] === 'assigned') {
                $data['completed_at'] = null;
            }
        }

        $ediscoveryReviewAssignment->update($data);

        return new EdiscoveryReviewAssignmentResource(
            $ediscoveryReviewAssignment->fresh()->load(['reviewer', 'assigner', 'document'])
        );
    }

    public function destroy(EdiscoveryReviewAssignment $ediscoveryReviewAssignment): JsonResponse
    {
        $this->authorize('delete', $ediscoveryReviewAssignment);

        $ediscoveryReviewAssignment->delete();

        return response()->json(['message' => 'Review assignment removed successfully.']);
    }
}
