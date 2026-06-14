<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Resources\KnowledgeArticleResource;
use App\Models\KnowledgeArticle;
use App\Models\LegalMatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class KnowledgeArticleController extends Controller
{
    use ResolvesOrganization;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', KnowledgeArticle::class);

        $organization = $this->organizationFor($request->user());

        $articles = KnowledgeArticle::query()
            ->with(['creator:id,name', 'legalMatter:id,title,matter_number'])
            ->where(fn ($q) => $q->where('organization_id', $organization->id)->orWhereNull('organization_id'))
            ->when($request->boolean('published_only', true), fn ($q) => $q->where('is_published', true))
            ->when($request->filled('legal_matter_id'), function ($q) use ($request): void {
                $matterId = $request->integer('legal_matter_id');
                $q->where(function ($inner) use ($matterId): void {
                    $inner->whereNull('legal_matter_id')->orWhere('legal_matter_id', $matterId);
                });
            })
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('content_type'), fn ($q) => $q->where('content_type', $request->string('content_type')))
            ->when($request->filled('practice_area'), fn ($q) => $q->where('practice_area', $request->string('practice_area')))
            ->when($request->filled('tag'), function ($q) use ($request): void {
                $tag = $request->string('tag');
                $q->where('tags', 'like', '%"' . addcslashes($tag, '"\\') . '"%');
            })
            ->when($request->filled('keyword'), function ($q) use ($request): void {
                $keyword = '%' . $request->string('keyword') . '%';
                $q->where(function ($inner) use ($keyword): void {
                    $inner->where('title', 'like', $keyword)
                        ->orWhere('excerpt', 'like', $keyword)
                        ->orWhere('content', 'like', $keyword)
                        ->orWhere('tags', 'like', $keyword);
                });
            })
            ->orderByDesc('updated_at')
            ->paginate($request->integer('per_page', 25));

        return KnowledgeArticleResource::collection($articles)
            ->additional(['meta' => [
                'content_types' => KnowledgeArticle::CONTENT_TYPES,
                'categories' => KnowledgeArticle::CATEGORIES,
            ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', KnowledgeArticle::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);
        $data = $this->validatedData($request);

        if (! empty($data['legal_matter_id'])) {
            LegalMatter::query()
                ->where('organization_id', $organization->id)
                ->findOrFail($data['legal_matter_id']);
        }

        $article = KnowledgeArticle::query()->create([
            ...$data,
            'organization_id' => $organization->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return (new KnowledgeArticleResource($article->load(['creator', 'legalMatter'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(KnowledgeArticle $knowledgeArticle): KnowledgeArticleResource
    {
        $this->authorize('view', $knowledgeArticle);

        return new KnowledgeArticleResource($knowledgeArticle->load(['creator', 'legalMatter']));
    }

    public function update(Request $request, KnowledgeArticle $knowledgeArticle): KnowledgeArticleResource
    {
        $this->authorize('update', $knowledgeArticle);

        $organization = $this->organizationFor($request->user());
        $data = $this->validatedData($request, partial: true);

        if (array_key_exists('legal_matter_id', $data) && ! empty($data['legal_matter_id'])) {
            LegalMatter::query()
                ->where('organization_id', $organization->id)
                ->findOrFail($data['legal_matter_id']);
        }

        $data['updated_by'] = $request->user()->id;
        $knowledgeArticle->update($data);

        return new KnowledgeArticleResource($knowledgeArticle->fresh()->load(['creator', 'legalMatter']));
    }

    public function destroy(KnowledgeArticle $knowledgeArticle): JsonResponse
    {
        $this->authorize('delete', $knowledgeArticle);

        $knowledgeArticle->delete();

        return response()->json(['message' => 'Knowledge article deleted successfully.']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedData(Request $request, bool $partial = false): array
    {
        $data = $request->validate([
            'legal_matter_id' => ['nullable', 'integer', 'exists:legal_matters,id'],
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content_type' => ['nullable', 'string', Rule::in(KnowledgeArticle::CONTENT_TYPES)],
            'category' => ['nullable', 'string', Rule::in(KnowledgeArticle::CATEGORIES)],
            'practice_area' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('tags', $data)) {
            $data['tags'] = array_values(array_filter($data['tags'] ?? []));
        }

        return $data;
    }
}
