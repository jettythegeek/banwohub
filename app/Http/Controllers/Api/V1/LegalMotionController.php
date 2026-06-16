<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\CourtFilingResource;
use App\Http\Resources\LegalMotionResource;
use App\Models\CourtFiling;
use App\Models\LegalMotion;
use App\Models\MotionTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LegalMotionController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', LegalMotion::class);

        $organization = $this->organizationFor($request->user());

        $motions = LegalMotion::query()
            ->with(['legalMatter:id,title,matter_number', 'creator:id,name', 'template:id,name,slug'])
            ->where('organization_id', $organization->id)
            ->when($request->filled('legal_matter_id'), fn ($q) => $q->where('legal_matter_id', $request->integer('legal_matter_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('updated_at')
            ->paginate($request->integer('per_page', 25));

        return LegalMotionResource::collection($motions)
            ->additional(['meta' => [
                'statuses' => LegalMotion::STATUSES,
            ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', LegalMotion::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);
        $data = $this->validatedData($request);

        $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);
        $this->resolveTemplate($data['motion_template_id'] ?? null, $organization->id);

        $contentHtml = $data['content_html'] ?? null;
        $motionType = $data['motion_type'] ?? null;

        if (! empty($data['motion_template_id']) && $contentHtml === null) {
            $template = MotionTemplate::query()->find($data['motion_template_id']);
            $contentHtml = $template?->structure_html;
            $motionType = $motionType ?? $template?->slug;
        }

        $motion = LegalMotion::query()->create([
            ...$data,
            'content_html' => $contentHtml,
            'motion_type' => $motionType,
            'organization_id' => $organization->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'status' => $data['status'] ?? 'draft',
        ]);

        return (new LegalMotionResource($motion->load(['legalMatter', 'creator', 'template'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(LegalMotion $legalMotion): LegalMotionResource
    {
        $this->authorize('view', $legalMotion);

        return new LegalMotionResource(
            $legalMotion->load(['legalMatter', 'creator', 'template', 'courtFiling'])
        );
    }

    public function update(Request $request, LegalMotion $legalMotion): LegalMotionResource
    {
        $this->authorize('update', $legalMotion);

        if ($legalMotion->isReadOnly()) {
            throw ValidationException::withMessages([
                'status' => ['Filing-ready motions cannot be edited.'],
            ]);
        }

        if (array_key_exists('legal_matter_id', $request->all()) && $request->filled('legal_matter_id')) {
            $this->legalMatterForOrganization((int) $request->integer('legal_matter_id'), $legalMotion->organization_id);
        }

        $data = $this->validatedData($request, partial: true);
        if (array_key_exists('motion_template_id', $data)) {
            $this->resolveTemplate($data['motion_template_id'], $legalMotion->organization_id);
        }

        $data['updated_by'] = $request->user()->id;
        $legalMotion->update($data);

        return new LegalMotionResource($legalMotion->fresh()->load(['legalMatter', 'creator', 'template', 'courtFiling']));
    }

    public function destroy(LegalMotion $legalMotion): JsonResponse
    {
        $this->authorize('delete', $legalMotion);

        $legalMotion->delete();

        return response()->json(['message' => 'Motion deleted successfully.']);
    }

    public function updateStatus(Request $request, LegalMotion $legalMotion): LegalMotionResource
    {
        $this->authorize('update', $legalMotion);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(LegalMotion::STATUSES)],
        ]);

        $nextStatus = $data['status'];
        if (! $legalMotion->canTransitionTo($nextStatus)) {
            throw ValidationException::withMessages([
                'status' => ["Cannot transition from {$legalMotion->status} to {$nextStatus}."],
            ]);
        }

        $legalMotion->update([
            'status' => $nextStatus,
            'updated_by' => $request->user()->id,
        ]);

        return new LegalMotionResource($legalMotion->fresh()->load(['legalMatter', 'creator', 'template', 'courtFiling']));
    }

    public function createFiling(Request $request, LegalMotion $legalMotion): JsonResponse
    {
        $this->authorize('update', $legalMotion);
        $this->authorize('create', CourtFiling::class);

        if ($legalMotion->court_filing_id !== null) {
            return response()->json(['message' => 'A filing already exists for this motion.'], 422);
        }

        if (! in_array($legalMotion->status, ['approved', 'filing_ready'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only approved motions can be linked to a court filing.'],
            ]);
        }

        $user = $request->user();
        $matter = $legalMotion->legalMatter;
        $court = $request->string('court')->toString() ?: ($matter->court_jurisdiction ?? 'Court');

        $filing = CourtFiling::query()->create([
            'organization_id' => $legalMotion->organization_id,
            'legal_matter_id' => $legalMotion->legal_matter_id,
            'legal_motion_id' => $legalMotion->id,
            'title' => $legalMotion->title,
            'court' => $court,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $updates = ['court_filing_id' => $filing->id];
        if ($legalMotion->status === 'approved') {
            $updates['status'] = 'filing_ready';
            $updates['updated_by'] = $user->id;
        }

        $legalMotion->update($updates);

        return (new LegalMotionResource($legalMotion->fresh()->load(['legalMatter', 'creator', 'template', 'courtFiling'])))
            ->additional(['filing' => new CourtFilingResource($filing)])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedData(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'legal_matter_id' => [$partial ? 'sometimes' : 'required', 'integer', 'exists:legal_matters,id'],
            'motion_template_id' => ['nullable', 'integer', 'exists:motion_templates,id'],
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'motion_type' => ['nullable', 'string', 'max:100'],
            'content_html' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(LegalMotion::STATUSES)],
            'last_ai_governance_log_id' => ['nullable', 'integer', 'exists:ai_governance_logs,id'],
        ]);
    }

    private function resolveTemplate(?int $templateId, int $organizationId): void
    {
        if ($templateId === null) {
            return;
        }

        $template = MotionTemplate::query()->findOrFail($templateId);
        abort_unless(
            $template->organization_id === null || $template->organization_id === $organizationId,
            404,
        );
    }
}
