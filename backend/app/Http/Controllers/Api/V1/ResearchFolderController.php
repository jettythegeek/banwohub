<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\ResearchFolderResource;
use App\Http\Resources\ResearchSavedItemResource;
use App\Models\LegalResearchEntry;
use App\Models\ResearchFolder;
use App\Models\ResearchSavedItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class ResearchFolderController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ResearchFolder::class);

        $organization = $this->organizationFor($request->user());

        $folders = ResearchFolder::query()
            ->with(['legalMatter:id,title,matter_number', 'creator:id,name'])
            ->withCount('savedItems')
            ->where('organization_id', $organization->id)
            ->when($request->filled('legal_matter_id'), fn ($q) => $q->where('legal_matter_id', $request->integer('legal_matter_id')))
            ->when($request->filled('practice_area'), fn ($q) => $q->where('practice_area', $request->string('practice_area')))
            ->orderByDesc('updated_at')
            ->paginate($request->integer('per_page', 25));

        return ResearchFolderResource::collection($folders);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ResearchFolder::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);
        $data = $this->validatedData($request);

        if (! empty($data['legal_matter_id'])) {
            $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);
        }

        $folder = ResearchFolder::query()->create([
            ...$data,
            'organization_id' => $organization->id,
            'created_by' => $user->id,
        ]);

        return (new ResearchFolderResource($folder->load(['legalMatter', 'creator'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ResearchFolder $researchFolder): ResearchFolderResource
    {
        $this->authorize('view', $researchFolder);

        return new ResearchFolderResource(
            $researchFolder->load([
                'legalMatter:id,title,matter_number',
                'creator:id,name',
                'savedItems.entry',
                'savedItems.saver:id,name',
            ])
        );
    }

    public function update(Request $request, ResearchFolder $researchFolder): ResearchFolderResource
    {
        $this->authorize('update', $researchFolder);

        if (array_key_exists('legal_matter_id', $request->all()) && $request->filled('legal_matter_id')) {
            $this->legalMatterForOrganization((int) $request->integer('legal_matter_id'), $researchFolder->organization_id);
        }

        $data = $this->validatedData($request, partial: true);
        $researchFolder->update($data);

        return new ResearchFolderResource($researchFolder->fresh()->load(['legalMatter', 'creator']));
    }

    public function destroy(ResearchFolder $researchFolder): JsonResponse
    {
        $this->authorize('delete', $researchFolder);

        $researchFolder->delete();

        return response()->json(['message' => 'Research folder deleted successfully.']);
    }

    public function items(ResearchFolder $researchFolder): AnonymousResourceCollection
    {
        $this->authorize('view', $researchFolder);

        $items = $researchFolder->savedItems()
            ->with(['entry', 'saver:id,name', 'legalMatter:id,title,matter_number'])
            ->orderByDesc('created_at')
            ->get();

        return ResearchSavedItemResource::collection($items);
    }

    public function storeItem(Request $request, ResearchFolder $researchFolder): JsonResponse
    {
        $this->authorize('create', ResearchSavedItem::class);
        $this->authorize('view', $researchFolder);

        $user = $request->user();
        $data = $request->validate([
            'legal_research_entry_id' => ['required', 'integer', 'exists:legal_research_entries,id'],
            'legal_matter_id' => ['nullable', 'integer', 'exists:legal_matters,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $entry = LegalResearchEntry::query()->findOrFail($data['legal_research_entry_id']);
        abort_unless(
            $entry->organization_id === null || $entry->organization_id === $researchFolder->organization_id,
            404,
        );

        if (! empty($data['legal_matter_id'])) {
            $this->legalMatterForOrganization((int) $data['legal_matter_id'], $researchFolder->organization_id);
        }

        $existing = ResearchSavedItem::query()
            ->where('research_folder_id', $researchFolder->id)
            ->where('legal_research_entry_id', $data['legal_research_entry_id'])
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'legal_research_entry_id' => ['This entry is already saved in this folder.'],
            ]);
        }

        $item = ResearchSavedItem::query()->create([
            'organization_id' => $researchFolder->organization_id,
            'research_folder_id' => $researchFolder->id,
            'legal_research_entry_id' => $data['legal_research_entry_id'],
            'legal_matter_id' => $data['legal_matter_id'] ?? $researchFolder->legal_matter_id,
            'notes' => $data['notes'] ?? null,
            'saved_by' => $user->id,
        ]);

        return (new ResearchSavedItemResource($item->load(['entry', 'saver:id,name'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedData(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'legal_matter_id' => ['nullable', 'integer', 'exists:legal_matters,id'],
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'practice_area' => ['nullable', 'string', 'max:255'],
            'legal_issue' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
