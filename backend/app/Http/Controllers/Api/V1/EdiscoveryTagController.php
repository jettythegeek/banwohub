<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\EdiscoveryTagResource;
use App\Models\EdiscoveryTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class EdiscoveryTagController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', EdiscoveryTag::class);

        $organization = $this->organizationFor($request->user());

        $tags = EdiscoveryTag::query()
            ->with(['creator:id,name'])
            ->where('organization_id', $organization->id)
            ->when($request->filled('legal_matter_id'), fn ($q) => $q->where(fn ($inner) => $inner
                ->where('legal_matter_id', $request->integer('legal_matter_id'))
                ->orWhereNull('legal_matter_id')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 100));

        return EdiscoveryTagResource::collection($tags)
            ->additional(['meta' => [
                'categories' => EdiscoveryTag::CATEGORIES,
            ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', EdiscoveryTag::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);
        $data = $this->validatedData($request);

        if (! empty($data['legal_matter_id'])) {
            $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);
        }

        $tag = EdiscoveryTag::query()->create([
            ...$data,
            'organization_id' => $organization->id,
            'created_by' => $user->id,
            'category' => $data['category'] ?? 'custom',
        ]);

        return (new EdiscoveryTagResource($tag->load('creator')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(EdiscoveryTag $ediscoveryTag): EdiscoveryTagResource
    {
        $this->authorize('view', $ediscoveryTag);

        return new EdiscoveryTagResource($ediscoveryTag->load('creator'));
    }

    public function update(Request $request, EdiscoveryTag $ediscoveryTag): EdiscoveryTagResource
    {
        $this->authorize('update', $ediscoveryTag);

        if ($request->filled('legal_matter_id') && $request->integer('legal_matter_id')) {
            $this->legalMatterForOrganization((int) $request->integer('legal_matter_id'), $ediscoveryTag->organization_id);
        }

        $ediscoveryTag->update($this->validatedData($request, partial: true));

        return new EdiscoveryTagResource($ediscoveryTag->fresh()->load('creator'));
    }

    public function destroy(EdiscoveryTag $ediscoveryTag): JsonResponse
    {
        $this->authorize('delete', $ediscoveryTag);

        $ediscoveryTag->delete();

        return response()->json(['message' => 'Discovery tag deleted successfully.']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedData(Request $request, bool $partial = false): array
    {
        $rules = [
            'legal_matter_id' => ['nullable', 'integer', 'exists:legal_matters,id'],
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:20'],
            'category' => ['nullable', 'string', Rule::in(EdiscoveryTag::CATEGORIES)],
        ];

        return $request->validate($rules);
    }
}
