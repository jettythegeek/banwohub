<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentClauseResource;
use App\Models\DocumentClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class DocumentClauseController extends Controller
{
    use ResolvesOrganization;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', DocumentClause::class);

        $organization = $this->organizationFor($request->user());

        $clauses = DocumentClause::query()
            ->with('creator:id,name')
            ->where('organization_id', $organization->id)
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('tag'), function ($q) use ($request): void {
                $tag = $request->string('tag');
                $q->where('tags', 'like', '%"' . addcslashes($tag, '"\\') . '"%');
            })
            ->when($request->filled('keyword'), function ($q) use ($request): void {
                $keyword = '%' . $request->string('keyword') . '%';
                $q->where(function ($inner) use ($keyword): void {
                    $inner->where('title', 'like', $keyword)
                        ->orWhere('body_html', 'like', $keyword)
                        ->orWhere('tags', 'like', $keyword);
                });
            })
            ->orderBy('category')
            ->orderBy('title')
            ->paginate($request->integer('per_page', 50));

        return DocumentClauseResource::collection($clauses)
            ->additional(['meta' => ['categories' => DocumentClause::CATEGORIES]]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', DocumentClause::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);
        $data = $this->validatedData($request);

        $clause = DocumentClause::query()->create([
            ...$data,
            'organization_id' => $organization->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return (new DocumentClauseResource($clause->load('creator')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(DocumentClause $documentClause): DocumentClauseResource
    {
        $this->authorize('view', $documentClause);

        return new DocumentClauseResource($documentClause->load('creator'));
    }

    public function update(Request $request, DocumentClause $documentClause): DocumentClauseResource
    {
        $this->authorize('update', $documentClause);

        $data = $this->validatedData($request, partial: true);
        $data['updated_by'] = $request->user()->id;

        $documentClause->update($data);

        return new DocumentClauseResource($documentClause->fresh()->load('creator'));
    }

    public function destroy(DocumentClause $documentClause): JsonResponse
    {
        $this->authorize('delete', $documentClause);

        $documentClause->delete();

        return response()->json(['message' => 'Clause deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, bool $partial = false): array
    {
        $rules = [
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'string', 'max:100', Rule::in(DocumentClause::CATEGORIES)],
            'body_html' => [$partial ? 'sometimes' : 'required', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];

        return $request->validate($rules);
    }
}
