<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentFolderResource;
use App\Models\DocumentFolder;
use App\Models\LegalMatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DocumentFolderController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', DocumentFolder::class);

        $organization = $this->organizationFor($request->user());
        $data = $request->validate([
            'legal_matter_id' => ['required', 'integer', 'exists:legal_matters,id'],
        ]);

        $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);

        $folders = DocumentFolder::query()
            ->withCount('documents')
            ->with(['children' => fn ($q) => $q->withCount('documents')->orderBy('name')])
            ->where('organization_id', $organization->id)
            ->where('legal_matter_id', $data['legal_matter_id'])
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return DocumentFolderResource::collection($folders);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', DocumentFolder::class);

        $organization = $this->organizationFor($request->user());
        $data = $request->validate([
            'legal_matter_id' => ['required', 'integer', 'exists:legal_matters,id'],
            'parent_id' => ['nullable', 'integer', 'exists:document_folders,id'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);

        if (! empty($data['parent_id'])) {
            $parent = DocumentFolder::query()
                ->where('organization_id', $organization->id)
                ->where('legal_matter_id', $matter->id)
                ->findOrFail($data['parent_id']);
            abort_unless($parent->legal_matter_id === $matter->id, 422, 'Parent folder must belong to the same case.');
        }

        $folder = DocumentFolder::query()->create([
            'organization_id' => $organization->id,
            'legal_matter_id' => $matter->id,
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
        ]);

        return (new DocumentFolderResource($folder->loadCount('documents')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(DocumentFolder $documentFolder): DocumentFolderResource
    {
        $this->authorize('view', $documentFolder);

        return new DocumentFolderResource(
            $documentFolder->loadCount('documents')->load(['children' => fn ($q) => $q->withCount('documents')->orderBy('name')]),
        );
    }

    public function update(Request $request, DocumentFolder $documentFolder): DocumentFolderResource
    {
        $this->authorize('update', $documentFolder);

        $organization = $this->organizationFor($request->user());
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:document_folders,id'],
        ]);

        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null) {
            abort_if((int) $data['parent_id'] === $documentFolder->id, 422, 'A folder cannot be its own parent.');
            $parent = DocumentFolder::query()
                ->where('organization_id', $organization->id)
                ->where('legal_matter_id', $documentFolder->legal_matter_id)
                ->findOrFail($data['parent_id']);
            abort_unless($parent->legal_matter_id === $documentFolder->legal_matter_id, 422, 'Parent folder must belong to the same case.');
        }

        $documentFolder->update($data);

        return new DocumentFolderResource($documentFolder->fresh()->loadCount('documents'));
    }

    public function destroy(DocumentFolder $documentFolder): JsonResponse
    {
        $this->authorize('delete', $documentFolder);

        $documentFolder->documents()->update(['document_folder_id' => null]);
        $documentFolder->children()->update(['parent_id' => null]);
        $documentFolder->delete();

        return response()->json(null, 204);
    }
}
