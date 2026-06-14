<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\EdiscoveryCollectionResource;
use App\Models\EdiscoveryCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class EdiscoveryCollectionController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', EdiscoveryCollection::class);

        $organization = $this->organizationFor($request->user());

        $collections = EdiscoveryCollection::query()
            ->with(['legalMatter:id,title,matter_number'])
            ->withCount('documents')
            ->where('organization_id', $organization->id)
            ->when($request->filled('legal_matter_id'), fn ($q) => $q->where('legal_matter_id', $request->integer('legal_matter_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('updated_at')
            ->paginate($request->integer('per_page', 25));

        return EdiscoveryCollectionResource::collection($collections)
            ->additional(['meta' => [
                'statuses' => EdiscoveryCollection::STATUSES,
            ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', EdiscoveryCollection::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);
        $data = $this->validatedData($request);

        $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);

        $collection = EdiscoveryCollection::query()->create([
            ...$data,
            'organization_id' => $organization->id,
            'created_by' => $user->id,
            'status' => $data['status'] ?? 'open',
        ]);

        activity('ediscovery')
            ->performedOn($collection)
            ->causedBy($user)
            ->withProperties(['legal_matter_id' => $collection->legal_matter_id])
            ->log('Discovery collection created');

        return (new EdiscoveryCollectionResource($collection->load('legalMatter')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(EdiscoveryCollection $ediscoveryCollection): EdiscoveryCollectionResource
    {
        $this->authorize('view', $ediscoveryCollection);

        return new EdiscoveryCollectionResource(
            $ediscoveryCollection->load(['legalMatter', 'creator'])->loadCount('documents')
        );
    }

    public function update(Request $request, EdiscoveryCollection $ediscoveryCollection): EdiscoveryCollectionResource
    {
        $this->authorize('update', $ediscoveryCollection);

        if ($request->filled('legal_matter_id')) {
            $this->legalMatterForOrganization((int) $request->integer('legal_matter_id'), $ediscoveryCollection->organization_id);
        }

        $ediscoveryCollection->update($this->validatedData($request, partial: true));

        return new EdiscoveryCollectionResource($ediscoveryCollection->fresh()->load('legalMatter')->loadCount('documents'));
    }

    public function destroy(EdiscoveryCollection $ediscoveryCollection): JsonResponse
    {
        $this->authorize('delete', $ediscoveryCollection);

        $ediscoveryCollection->delete();

        return response()->json(['message' => 'Discovery collection deleted successfully.']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedData(Request $request, bool $partial = false): array
    {
        $rules = [
            'legal_matter_id' => [$partial ? 'sometimes' : 'required', 'integer', 'exists:legal_matters,id'],
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(EdiscoveryCollection::STATUSES)],
        ];

        return $request->validate($rules);
    }
}
