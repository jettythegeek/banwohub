<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\BriefCitationResource;
use App\Http\Resources\LegalBriefResource;
use App\Models\BriefCitation;
use App\Models\LegalBrief;
use App\Services\BriefExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LegalBriefController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', LegalBrief::class);

        $organization = $this->organizationFor($request->user());

        $briefs = LegalBrief::query()
            ->with(['legalMatter:id,title,matter_number', 'creator:id,name'])
            ->where('organization_id', $organization->id)
            ->when($request->filled('legal_matter_id'), fn ($q) => $q->where('legal_matter_id', $request->integer('legal_matter_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('updated_at')
            ->paginate($request->integer('per_page', 25));

        return LegalBriefResource::collection($briefs)
            ->additional(['meta' => [
                'statuses' => LegalBrief::STATUSES,
                'authority_types' => LegalBrief::AUTHORITY_TYPES,
                'brief_types' => LegalBrief::BRIEF_TYPES,
                'court_types' => LegalBrief::COURT_TYPES,
                'citation_styles' => LegalBrief::CITATION_STYLES,
            ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', LegalBrief::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);
        $data = $this->validatedData($request);

        $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);

        $brief = LegalBrief::query()->create([
            ...$data,
            'organization_id' => $organization->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'status' => $data['status'] ?? 'draft',
        ]);

        return (new LegalBriefResource($brief->load(['legalMatter', 'creator', 'citations'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(LegalBrief $legalBrief): LegalBriefResource
    {
        $this->authorize('view', $legalBrief);

        return new LegalBriefResource(
            $legalBrief->load(['legalMatter', 'creator', 'citations'])
        );
    }

    public function update(Request $request, LegalBrief $legalBrief): LegalBriefResource
    {
        $this->authorize('update', $legalBrief);

        if ($legalBrief->status === 'final') {
            throw ValidationException::withMessages([
                'status' => ['Finalized briefs cannot be edited. Revert to review first.'],
            ]);
        }

        if (array_key_exists('legal_matter_id', $request->all()) && $request->filled('legal_matter_id')) {
            $this->legalMatterForOrganization((int) $request->integer('legal_matter_id'), $legalBrief->organization_id);
        }

        $data = $this->validatedData($request, partial: true);
        $data['updated_by'] = $request->user()->id;

        $legalBrief->update($data);

        return new LegalBriefResource($legalBrief->fresh()->load(['legalMatter', 'creator', 'citations']));
    }

    public function destroy(LegalBrief $legalBrief): JsonResponse
    {
        $this->authorize('delete', $legalBrief);

        $legalBrief->delete();

        return response()->json(['message' => 'Brief deleted successfully.']);
    }

    public function updateStatus(Request $request, LegalBrief $legalBrief): LegalBriefResource
    {
        $this->authorize('update', $legalBrief);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(LegalBrief::STATUSES)],
        ]);

        $nextStatus = $data['status'];
        if (! $legalBrief->canTransitionTo($nextStatus)) {
            throw ValidationException::withMessages([
                'status' => ["Cannot transition from {$legalBrief->status} to {$nextStatus}."],
            ]);
        }

        $legalBrief->update([
            'status' => $nextStatus,
            'updated_by' => $request->user()->id,
        ]);

        return new LegalBriefResource($legalBrief->fresh()->load(['legalMatter', 'creator', 'citations']));
    }

    public function citations(LegalBrief $legalBrief): AnonymousResourceCollection
    {
        $this->authorize('view', $legalBrief);

        return BriefCitationResource::collection($legalBrief->citations);
    }

    public function storeCitation(Request $request, LegalBrief $legalBrief): JsonResponse
    {
        $this->authorize('update', $legalBrief);

        if ($legalBrief->status === 'final') {
            throw ValidationException::withMessages([
                'status' => ['Cannot add citations to a finalized brief.'],
            ]);
        }

        $data = $request->validate([
            'authority' => ['required', 'string', Rule::in(LegalBrief::AUTHORITY_TYPES)],
            'citation_text' => ['required', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'source_note' => ['nullable', 'string'],
        ]);

        $citation = BriefCitation::query()->create([
            ...$data,
            'organization_id' => $legalBrief->organization_id,
            'legal_brief_id' => $legalBrief->id,
            'sort_order' => $data['sort_order'] ?? $legalBrief->citations()->count(),
        ]);

        $legalBrief->update(['updated_by' => $request->user()->id]);

        return (new BriefCitationResource($citation))
            ->response()
            ->setStatusCode(201);
    }

    public function destroyCitation(LegalBrief $legalBrief, BriefCitation $citation): JsonResponse
    {
        $this->authorize('update', $legalBrief);

        abort_unless($citation->legal_brief_id === $legalBrief->id, 404);

        if ($legalBrief->status === 'final') {
            throw ValidationException::withMessages([
                'status' => ['Cannot remove citations from a finalized brief.'],
            ]);
        }

        $citation->delete();
        $legalBrief->update(['updated_by' => $request->user()->id]);

        return response()->json(['message' => 'Citation removed successfully.']);
    }

    public function export(Request $request, LegalBrief $legalBrief, BriefExportService $exportService): Response|JsonResponse
    {
        $this->authorize('view', $legalBrief);

        $data = $request->validate([
            'format' => ['nullable', 'string', Rule::in(['html', 'word', 'pdf', 'court_filing', 'google_docs'])],
        ]);

        return $exportService->export($legalBrief, $data['format'] ?? 'html');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedData(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'legal_matter_id' => [$partial ? 'sometimes' : 'required', 'integer', 'exists:legal_matters,id'],
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'brief_type' => ['nullable', 'string', Rule::in(LegalBrief::BRIEF_TYPES)],
            'jurisdiction' => ['nullable', 'string', 'max:120'],
            'court_type' => ['nullable', 'string', Rule::in(LegalBrief::COURT_TYPES)],
            'cause_of_action' => ['nullable', 'string', 'max:500'],
            'case_facts' => ['nullable', 'string'],
            'statutes' => ['nullable', 'string'],
            'desired_outcome' => ['nullable', 'string'],
            'citation_style' => ['nullable', 'string', Rule::in(LegalBrief::CITATION_STYLES)],
            'content_html' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(LegalBrief::STATUSES)],
            'last_ai_governance_log_id' => ['nullable', 'integer', 'exists:ai_governance_logs,id'],
        ]);
    }
}
