<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\EdiscoveryDocumentResource;
use App\Models\EdiscoveryCollection;
use App\Models\EdiscoveryDocument;
use App\Services\EdiscoveryReviewProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EdiscoveryDocumentController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', EdiscoveryDocument::class);

        $organization = $this->organizationFor($request->user());

        $documents = EdiscoveryDocument::query()
            ->with([
                'legalMatter:id,title,matter_number',
                'collection:id,name',
                'uploader:id,name',
                'reviewAssignments.reviewer:id,name',
            ])
            ->where('organization_id', $organization->id)
            ->when($request->filled('legal_matter_id'), fn ($q) => $q->where('legal_matter_id', $request->integer('legal_matter_id')))
            ->when($request->filled('ediscovery_collection_id'), fn ($q) => $q->where('ediscovery_collection_id', $request->integer('ediscovery_collection_id')))
            ->when($request->filled('privilege'), fn ($q) => $q->where('privilege', $request->string('privilege')))
            ->when($request->filled('relevance'), fn ($q) => $q->where('relevance', $request->string('relevance')))
            ->when($request->filled('review_status'), fn ($q) => $q->where('review_status', $request->string('review_status')))
            ->when($request->filled('file_type'), fn ($q) => $q->where('file_type', $request->string('file_type')))
            ->when($request->filled('sender'), fn ($q) => $q->where('sender', 'like', '%'.$request->string('sender').'%'))
            ->when($request->filled('recipient'), fn ($q) => $q->where('recipient', 'like', '%'.$request->string('recipient').'%'))
            ->when($request->filled('tag'), function ($q) use ($request): void {
                $tag = $request->string('tag');
                $q->where('custom_tags', 'like', '%"'.$tag.'"%');
            })
            ->when($request->filled('reviewer_id'), function ($q) use ($request): void {
                $q->whereHas('reviewAssignments', fn ($inner) => $inner->where('reviewer_id', $request->integer('reviewer_id')));
            })
            ->when($request->filled('document_date_from'), fn ($q) => $q->whereDate('document_date', '>=', $request->string('document_date_from')))
            ->when($request->filled('document_date_to'), fn ($q) => $q->whereDate('document_date', '<=', $request->string('document_date_to')))
            ->when($request->filled('keyword'), function ($q) use ($request): void {
                $keyword = '%'.$request->string('keyword').'%';
                $q->where(function ($inner) use ($keyword): void {
                    $inner->where('title', 'like', $keyword)
                        ->orWhere('notes', 'like', $keyword)
                        ->orWhere('content_preview', 'like', $keyword)
                        ->orWhere('sender', 'like', $keyword)
                        ->orWhere('recipient', 'like', $keyword)
                        ->orWhere('original_filename', 'like', $keyword);
                });
            })
            ->orderByDesc('updated_at')
            ->paginate($request->integer('per_page', 50));

        return EdiscoveryDocumentResource::collection($documents)
            ->additional(['meta' => [
                'privileges' => EdiscoveryDocument::PRIVILEGES,
                'relevances' => EdiscoveryDocument::RELEVANCES,
                'review_statuses' => EdiscoveryDocument::REVIEW_STATUSES,
                'file_types' => EdiscoveryDocument::FILE_TYPES,
            ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', EdiscoveryDocument::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);
        $data = $this->validatedData($request);

        $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);
        $this->collectionForOrganization((int) $data['ediscovery_collection_id'], $organization->id);

        $path = null;
        $originalFilename = null;
        $mimeType = null;
        $size = 0;
        $fileType = $data['file_type'] ?? 'other';

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $pathPrefix = "organizations/{$organization->id}/cases/{$data['legal_matter_id']}/ediscovery/{$data['ediscovery_collection_id']}";
            $path = $file->store($pathPrefix, 'local');
            $originalFilename = $file->getClientOriginalName();
            $mimeType = $file->getClientMimeType();
            $size = $file->getSize() ?: 0;
            $fileType = $this->inferFileType($originalFilename, $mimeType);
        }

        $document = EdiscoveryDocument::query()->create([
            ...$data,
            'organization_id' => $organization->id,
            'file_type' => $fileType,
            'uploaded_by' => $user->id,
            'created_by' => $user->id,
            'path' => $path,
            'original_filename' => $originalFilename,
            'mime_type' => $mimeType,
            'size' => $size,
            'disk' => 'local',
            'content_preview' => $data['content_preview'] ?? $data['notes'] ?? null,
        ]);

        activity('ediscovery')
            ->performedOn($document)
            ->causedBy($user)
            ->withProperties(['legal_matter_id' => $document->legal_matter_id])
            ->log('Discovery document uploaded');

        return (new EdiscoveryDocumentResource($document->load(['legalMatter', 'collection', 'uploader'])))
            ->response()
            ->setStatusCode(201);
    }

    public function bulkUpload(Request $request): JsonResponse
    {
        $this->authorize('create', EdiscoveryDocument::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);
        $data = $request->validate([
            'legal_matter_id' => ['required', 'integer', 'exists:legal_matters,id'],
            'ediscovery_collection_id' => ['required', 'integer', 'exists:ediscovery_collections,id'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:20480'],
            'default_privilege' => ['nullable', 'string', Rule::in(EdiscoveryDocument::PRIVILEGES)],
            'default_relevance' => ['nullable', 'string', Rule::in(EdiscoveryDocument::RELEVANCES)],
            'custom_tags' => ['nullable', 'array'],
            'custom_tags.*' => ['string', 'max:100'],
        ]);

        $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);
        $this->collectionForOrganization((int) $data['ediscovery_collection_id'], $organization->id);

        $created = [];
        foreach ($request->file('files', []) as $file) {
            $originalFilename = $file->getClientOriginalName();
            $pathPrefix = "organizations/{$organization->id}/cases/{$data['legal_matter_id']}/ediscovery/{$data['ediscovery_collection_id']}";
            $path = $file->store($pathPrefix, 'local');

            $document = EdiscoveryDocument::query()->create([
                'organization_id' => $organization->id,
                'legal_matter_id' => $data['legal_matter_id'],
                'ediscovery_collection_id' => $data['ediscovery_collection_id'],
                'title' => pathinfo($originalFilename, PATHINFO_FILENAME) ?: $originalFilename,
                'privilege' => $data['default_privilege'] ?? 'none',
                'relevance' => $data['default_relevance'] ?? 'needs_review',
                'custom_tags' => $data['custom_tags'] ?? [],
                'review_status' => 'pending',
                'file_type' => $this->inferFileType($originalFilename, $file->getClientMimeType()),
                'original_filename' => $originalFilename,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize() ?: 0,
                'disk' => 'local',
                'path' => $path,
                'uploaded_by' => $user->id,
                'created_by' => $user->id,
                'content_preview' => $originalFilename,
            ]);

            $created[] = $document;
        }

        activity('ediscovery')
            ->causedBy($user)
            ->withProperties([
                'legal_matter_id' => $data['legal_matter_id'],
                'collection_id' => $data['ediscovery_collection_id'],
                'count' => count($created),
            ])
            ->log('Bulk discovery upload');

        $documentIds = array_map(fn (EdiscoveryDocument $document) => $document->id, $created);
        $loaded = EdiscoveryDocument::query()
            ->whereIn('id', $documentIds)
            ->with(['legalMatter', 'collection', 'uploader'])
            ->get();

        return response()->json([
            'count' => count($created),
            'data' => EdiscoveryDocumentResource::collection($loaded),
        ], 201);
    }

    public function show(EdiscoveryDocument $ediscoveryDocument): EdiscoveryDocumentResource
    {
        $this->authorize('view', $ediscoveryDocument);

        return new EdiscoveryDocumentResource(
            $ediscoveryDocument->load([
                'legalMatter',
                'collection',
                'uploader',
                'reviewAssignments.reviewer',
                'reviewAssignments.assigner',
            ])
        );
    }

    public function update(Request $request, EdiscoveryDocument $ediscoveryDocument): EdiscoveryDocumentResource
    {
        $this->authorize('update', $ediscoveryDocument);

        if ($request->filled('legal_matter_id')) {
            $this->legalMatterForOrganization((int) $request->integer('legal_matter_id'), $ediscoveryDocument->organization_id);
        }

        $ediscoveryDocument->update($this->validatedData($request, partial: true));

        return new EdiscoveryDocumentResource($ediscoveryDocument->fresh()->load(['legalMatter', 'collection', 'uploader']));
    }

    public function destroy(EdiscoveryDocument $ediscoveryDocument): JsonResponse
    {
        $this->authorize('delete', $ediscoveryDocument);

        if ($ediscoveryDocument->path && Storage::disk($ediscoveryDocument->disk)->exists($ediscoveryDocument->path)) {
            Storage::disk($ediscoveryDocument->disk)->delete($ediscoveryDocument->path);
        }

        $ediscoveryDocument->delete();

        return response()->json(['message' => 'Discovery document deleted successfully.']);
    }

    public function updateTags(Request $request, EdiscoveryDocument $ediscoveryDocument): EdiscoveryDocumentResource
    {
        $this->authorize('update', $ediscoveryDocument);

        $data = $request->validate([
            'privilege' => ['nullable', 'string', Rule::in(EdiscoveryDocument::PRIVILEGES)],
            'relevance' => ['nullable', 'string', Rule::in(EdiscoveryDocument::RELEVANCES)],
            'custom_tags' => ['nullable', 'array'],
            'custom_tags.*' => ['string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $ediscoveryDocument->update(array_filter($data, fn ($value) => $value !== null));

        activity('ediscovery')
            ->performedOn($ediscoveryDocument)
            ->causedBy($request->user())
            ->withProperties([
                'privilege' => $ediscoveryDocument->privilege,
                'relevance' => $ediscoveryDocument->relevance,
            ])
            ->log('Discovery document tagged');

        return new EdiscoveryDocumentResource($ediscoveryDocument->fresh()->load(['legalMatter', 'collection', 'uploader']));
    }

    public function updateReviewStatus(Request $request, EdiscoveryDocument $ediscoveryDocument): EdiscoveryDocumentResource
    {
        $this->authorize('update', $ediscoveryDocument);

        $data = $request->validate([
            'review_status' => ['required', 'string', Rule::in(EdiscoveryDocument::REVIEW_STATUSES)],
        ]);

        $nextStatus = $data['review_status'];
        if (! $ediscoveryDocument->canTransitionReviewStatusTo($nextStatus)) {
            throw ValidationException::withMessages([
                'review_status' => ["Cannot transition from {$ediscoveryDocument->review_status} to {$nextStatus}."],
            ]);
        }

        $ediscoveryDocument->update(['review_status' => $nextStatus]);

        return new EdiscoveryDocumentResource($ediscoveryDocument->fresh()->load(['legalMatter', 'collection', 'uploader']));
    }

    public function reviewProgress(Request $request, EdiscoveryReviewProgressService $progressService): JsonResponse
    {
        $this->authorize('viewAny', EdiscoveryDocument::class);

        $organization = $this->organizationFor($request->user());
        $data = $request->validate([
            'legal_matter_id' => ['required', 'integer', 'exists:legal_matters,id'],
        ]);

        $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);

        return response()->json($progressService->progress((int) $data['legal_matter_id'], $organization->id));
    }

    protected function collectionForOrganization(int $id, int $organizationId): EdiscoveryCollection
    {
        return EdiscoveryCollection::query()
            ->where('organization_id', $organizationId)
            ->findOrFail($id);
    }

    protected function inferFileType(?string $filename, ?string $mimeType): string
    {
        $extension = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));

        return match (true) {
            $extension === 'pdf' || str_contains((string) $mimeType, 'pdf') => 'pdf',
            in_array($extension, ['eml', 'msg'], true) || str_contains((string) $mimeType, 'message') => 'email',
            in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) || str_starts_with((string) $mimeType, 'image/') => 'image',
            in_array($extension, ['xls', 'xlsx', 'csv'], true) || str_contains((string) $mimeType, 'spreadsheet') => 'spreadsheet',
            in_array($extension, ['doc', 'docx', 'txt', 'rtf'], true) || str_contains((string) $mimeType, 'document') => 'document',
            in_array($extension, ['ppt', 'pptx'], true) || str_contains((string) $mimeType, 'presentation') => 'presentation',
            in_array($extension, ['zip', 'rar', '7z'], true) || str_contains((string) $mimeType, 'zip') => 'archive',
            default => 'other',
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedData(Request $request, bool $partial = false): array
    {
        $rules = [
            'legal_matter_id' => [$partial ? 'sometimes' : 'required', 'integer', 'exists:legal_matters,id'],
            'ediscovery_collection_id' => [$partial ? 'sometimes' : 'required', 'integer', 'exists:ediscovery_collections,id'],
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'document_date' => ['nullable', 'date'],
            'sender' => ['nullable', 'string', 'max:255'],
            'recipient' => ['nullable', 'string', 'max:255'],
            'file_type' => ['nullable', 'string', Rule::in(EdiscoveryDocument::FILE_TYPES)],
            'privilege' => ['nullable', 'string', Rule::in(EdiscoveryDocument::PRIVILEGES)],
            'relevance' => ['nullable', 'string', Rule::in(EdiscoveryDocument::RELEVANCES)],
            'custom_tags' => ['nullable', 'array'],
            'custom_tags.*' => ['string', 'max:100'],
            'review_status' => ['nullable', 'string', Rule::in(EdiscoveryDocument::REVIEW_STATUSES)],
            'content_preview' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:20480'],
        ];

        return $request->validate($rules);
    }
}
