<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\LegalBriefResource;
use App\Http\Resources\ResearchChatMessageResource;
use App\Http\Resources\ResearchProjectResource;
use App\Models\LegalBrief;
use App\Models\ResearchChatMessage;
use App\Models\ResearchProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ResearchProjectController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ResearchProject::class);

        $organization = $this->organizationFor($request->user());

        $projects = ResearchProject::query()
            ->with(['legalMatter:id,title,matter_number', 'creator:id,name'])
            ->withCount('chatMessages')
            ->where('organization_id', $organization->id)
            ->when($request->filled('legal_matter_id'), fn ($q) => $q->where('legal_matter_id', $request->integer('legal_matter_id')))
            ->orderByDesc('updated_at')
            ->paginate($request->integer('per_page', 25));

        return ResearchProjectResource::collection($projects);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ResearchProject::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);
        $data = $this->validatedData($request);

        if (! empty($data['legal_matter_id'])) {
            $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);
        }

        $project = ResearchProject::query()->create([
            ...$data,
            'organization_id' => $organization->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return (new ResearchProjectResource($project->load(['legalMatter', 'creator'])->loadCount('chatMessages')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ResearchProject $researchProject): ResearchProjectResource
    {
        $this->authorize('view', $researchProject);

        return new ResearchProjectResource(
            $researchProject->load(['legalMatter', 'creator'])->loadCount('chatMessages')
        );
    }

    public function update(Request $request, ResearchProject $researchProject): ResearchProjectResource
    {
        $this->authorize('update', $researchProject);

        if ($request->filled('legal_matter_id')) {
            $this->legalMatterForOrganization((int) $request->integer('legal_matter_id'), $researchProject->organization_id);
        }

        $data = $this->validatedData($request, partial: true);
        $data['updated_by'] = $request->user()->id;

        $researchProject->update($data);

        return new ResearchProjectResource(
            $researchProject->fresh()->load(['legalMatter', 'creator'])->loadCount('chatMessages')
        );
    }

    public function destroy(ResearchProject $researchProject): JsonResponse
    {
        $this->authorize('delete', $researchProject);

        $researchProject->delete();

        return response()->json(['message' => 'Research project deleted successfully.']);
    }

    public function messages(ResearchProject $researchProject): AnonymousResourceCollection
    {
        $this->authorize('view', $researchProject);

        return ResearchChatMessageResource::collection(
            $researchProject->chatMessages()->orderBy('created_at')->get()
        );
    }

    public function transferToBrief(Request $request, ResearchProject $researchProject): JsonResponse
    {
        $this->authorize('view', $researchProject);
        abort_unless($request->user()?->can('briefs.create'), 403);

        $data = $request->validate([
            'legal_matter_id' => ['required', 'integer', 'exists:legal_matters,id'],
            'legal_brief_id' => ['nullable', 'integer', 'exists:legal_briefs,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'content_html' => ['nullable', 'string'],
            'append' => ['nullable', 'boolean'],
        ]);

        $organization = $this->organizationFor($request->user());
        $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);

        $researchSummary = $researchProject->chatMessages()
            ->orderBy('created_at')
            ->get()
            ->map(fn (ResearchChatMessage $message) => '<p><strong>'.e(strtoupper($message->role)).':</strong> '
                .nl2br(e($message->content)).'</p>')
            ->join('');

        $transferBlock = '<section class="research-transfer">'
            .'<h2>Research Transfer — '.e($researchProject->name).'</h2>'
            .($researchProject->description ? '<p><em>'.e($researchProject->description).'</em></p>' : '')
            .($researchProject->case_theory ? '<h3>Case Theory</h3><p>'.nl2br(e($researchProject->case_theory)).'</p>' : '')
            .($researchProject->jurisdiction ? '<p><strong>Jurisdiction:</strong> '.e($researchProject->jurisdiction).'</p>' : '')
            .($researchSummary !== '' ? '<h3>Research Conversation</h3>'.$researchSummary : '')
            .(! empty($data['content_html']) ? '<h3>Additional Content</h3>'.(string) $data['content_html'] : '')
            .'</section>';

        if (! empty($data['legal_brief_id'])) {
            $brief = LegalBrief::query()->findOrFail($data['legal_brief_id']);
            abort_unless($brief->organization_id === $organization->id, 404);
            abort_unless($brief->legal_matter_id === $matter->id, 422);

            if ($brief->status === 'final') {
                return response()->json(['message' => 'Cannot transfer research into a finalized brief.'], 422);
            }

            $content = ($data['append'] ?? true)
                ? ($brief->content_html ?? '').$transferBlock
                : $transferBlock;

            $brief->update([
                'content_html' => $content,
                'updated_by' => $request->user()->id,
            ]);

            return (new LegalBriefResource($brief->fresh()->load(['legalMatter', 'citations'])))
                ->response();
        }

        $brief = LegalBrief::query()->create([
            'organization_id' => $organization->id,
            'legal_matter_id' => $matter->id,
            'title' => $data['title'] ?? "Research brief — {$researchProject->name}",
            'brief_type' => 'memorandum_of_law',
            'jurisdiction' => $researchProject->jurisdiction,
            'content_html' => $transferBlock,
            'status' => 'draft',
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return (new LegalBriefResource($brief->load(['legalMatter', 'citations'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedData(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'legal_matter_id' => [$partial ? 'sometimes' : 'nullable', 'integer', 'exists:legal_matters,id'],
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'case_theory' => ['nullable', 'string'],
            'jurisdiction' => ['nullable', 'string', 'max:120'],
            'practice_area' => ['nullable', 'string', 'max:120'],
        ]);
    }
}
