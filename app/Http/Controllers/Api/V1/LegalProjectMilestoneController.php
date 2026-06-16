<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Resources\LegalProjectMilestoneResource;
use App\Models\LegalMatter;
use App\Models\LegalProjectMilestone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class LegalProjectMilestoneController extends Controller
{
    use ResolvesOrganization;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', LegalProjectMilestone::class);

        $organization = $this->organizationFor($request->user());

        $milestones = LegalProjectMilestone::query()
            ->with(['assignee:id,name', 'legalMatter:id,title,matter_number'])
            ->where('organization_id', $organization->id)
            ->when($request->filled('legal_matter_id'), fn ($q) => $q->where('legal_matter_id', $request->integer('legal_matter_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('assigned_to'), fn ($q) => $q->where('assigned_to', $request->integer('assigned_to')))
            ->orderBy('sort_order')
            ->orderBy('due_at')
            ->paginate($request->integer('per_page', 50));

        return LegalProjectMilestoneResource::collection($milestones)
            ->additional(['meta' => [
                'milestone_types' => LegalProjectMilestone::MILESTONE_TYPES,
                'statuses' => LegalProjectMilestone::STATUSES,
            ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', LegalProjectMilestone::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);
        $data = $this->validatedData($request);

        LegalMatter::query()
            ->where('organization_id', $organization->id)
            ->findOrFail($data['legal_matter_id']);

        $milestone = LegalProjectMilestone::query()->create([
            ...$data,
            'organization_id' => $organization->id,
            'created_by' => $user->id,
        ]);

        return (new LegalProjectMilestoneResource($milestone->load(['assignee', 'legalMatter'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(LegalProjectMilestone $legalProjectMilestone): LegalProjectMilestoneResource
    {
        $this->authorize('view', $legalProjectMilestone);

        return new LegalProjectMilestoneResource($legalProjectMilestone->load(['assignee', 'legalMatter']));
    }

    public function update(Request $request, LegalProjectMilestone $legalProjectMilestone): LegalProjectMilestoneResource
    {
        $this->authorize('update', $legalProjectMilestone);

        $data = $this->validatedData($request, partial: true);

        if (isset($data['status']) && $data['status'] === 'completed' && ! isset($data['completed_at'])) {
            $data['completed_at'] = now();
        }

        $legalProjectMilestone->update($data);

        return new LegalProjectMilestoneResource($legalProjectMilestone->fresh()->load(['assignee', 'legalMatter']));
    }

    public function destroy(LegalProjectMilestone $legalProjectMilestone): JsonResponse
    {
        $this->authorize('delete', $legalProjectMilestone);

        $legalProjectMilestone->delete();

        return response()->json(['message' => 'Milestone deleted successfully.']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedData(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'legal_matter_id' => [$partial ? 'sometimes' : 'required', 'integer', 'exists:legal_matters,id'],
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'milestone_type' => ['nullable', 'string', Rule::in(LegalProjectMilestone::MILESTONE_TYPES)],
            'status' => ['nullable', 'string', Rule::in(LegalProjectMilestone::STATUSES)],
            'due_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
