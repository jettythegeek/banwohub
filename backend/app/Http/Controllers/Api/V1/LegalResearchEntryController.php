<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Resources\LegalResearchEntryResource;
use App\Models\LegalResearchEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class LegalResearchEntryController extends Controller
{
    use ResolvesOrganization;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', LegalResearchEntry::class);

        $organization = $this->organizationFor($request->user());

        $entries = LegalResearchEntry::query()
            ->with(['creator:id,name'])
            ->where(fn ($q) => $q->where('organization_id', $organization->id)->orWhereNull('organization_id'))
            ->when($request->filled('jurisdiction'), fn ($q) => $q->where('jurisdiction', $request->string('jurisdiction')))
            ->when($request->filled('document_type'), fn ($q) => $q->where('document_type', $request->string('document_type')))
            ->when($request->filled('keyword'), function ($q) use ($request): void {
                $keyword = '%' . $request->string('keyword') . '%';
                $q->where(function ($inner) use ($keyword): void {
                    $inner->where('title', 'like', $keyword)
                        ->orWhere('citation', 'like', $keyword)
                        ->orWhere('summary', 'like', $keyword)
                        ->orWhere('tags', 'like', $keyword);
                });
            })
            ->orderByDesc('updated_at')
            ->paginate($request->integer('per_page', 25));

        return LegalResearchEntryResource::collection($entries)
            ->additional(['meta' => [
                'document_types' => LegalResearchEntry::DOCUMENT_TYPES,
            ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', LegalResearchEntry::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);
        $data = $this->validatedData($request);

        $entry = LegalResearchEntry::query()->create([
            ...$data,
            'organization_id' => $organization->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return (new LegalResearchEntryResource($entry->load('creator')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(LegalResearchEntry $legalResearchEntry): LegalResearchEntryResource
    {
        $this->authorize('view', $legalResearchEntry);

        return new LegalResearchEntryResource($legalResearchEntry->load('creator'));
    }

    public function update(Request $request, LegalResearchEntry $legalResearchEntry): LegalResearchEntryResource
    {
        $this->authorize('update', $legalResearchEntry);

        $data = $this->validatedData($request, partial: true);
        $data['updated_by'] = $request->user()->id;
        $legalResearchEntry->update($data);

        return new LegalResearchEntryResource($legalResearchEntry->fresh()->load('creator'));
    }

    public function destroy(LegalResearchEntry $legalResearchEntry): JsonResponse
    {
        $this->authorize('delete', $legalResearchEntry);

        $legalResearchEntry->delete();

        return response()->json(['message' => 'Research entry deleted successfully.']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedData(Request $request, bool $partial = false): array
    {
        $data = $request->validate([
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'citation' => ['nullable', 'string', 'max:500'],
            'summary' => ['nullable', 'string'],
            'jurisdiction' => ['nullable', 'string', 'max:255'],
            'document_type' => ['nullable', 'string', Rule::in(LegalResearchEntry::DOCUMENT_TYPES)],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
        ]);

        if (array_key_exists('tags', $data)) {
            $data['tags'] = array_values(array_filter($data['tags'] ?? []));
        }

        return $data;
    }
}
