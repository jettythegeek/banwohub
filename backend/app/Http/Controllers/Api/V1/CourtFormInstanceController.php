<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\CourtFormInstanceResource;
use App\Models\CourtFormInstance;
use App\Models\CourtFormTemplate;
use App\Models\CourtFiling;
use App\Services\CourtFormPrefillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CourtFormInstanceController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function __construct(
        protected CourtFormPrefillService $prefillService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CourtFormInstance::class);

        $organization = $this->organizationFor($request->user());

        $instances = CourtFormInstance::query()
            ->with(['template', 'legalMatter:id,title,matter_number'])
            ->where('organization_id', $organization->id)
            ->when($request->filled('legal_matter_id'), fn ($q) => $q->where('legal_matter_id', $request->integer('legal_matter_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return CourtFormInstanceResource::collection($instances);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CourtFormInstance::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);
        $data = $this->validatedData($request);

        $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);
        $template = CourtFormTemplate::query()
            ->availableToOrganization($organization->id)
            ->findOrFail((int) $data['court_form_template_id']);

        $fieldValues = $data['field_values'] ?? null;
        if ($fieldValues === null) {
            $fieldValues = $this->prefillService->prefill($template, $matter)['field_values'];
        }

        $instance = CourtFormInstance::query()->create([
            'organization_id' => $organization->id,
            'legal_matter_id' => $matter->id,
            'court_form_template_id' => $template->id,
            'title' => $data['title'] ?? $template->name,
            'field_values' => $fieldValues,
            'status' => $data['status'] ?? 'draft',
            'created_by' => $user->id,
        ]);

        return (new CourtFormInstanceResource($instance->load(['template', 'legalMatter'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(CourtFormInstance $courtFormInstance): CourtFormInstanceResource
    {
        $this->authorize('view', $courtFormInstance);

        return new CourtFormInstanceResource(
            $courtFormInstance->load(['template', 'legalMatter', 'courtFiling'])
        );
    }

    public function update(Request $request, CourtFormInstance $courtFormInstance): CourtFormInstanceResource
    {
        $this->authorize('update', $courtFormInstance);

        $courtFormInstance->update($this->validatedData($request, partial: true));

        return new CourtFormInstanceResource(
            $courtFormInstance->fresh()->load(['template', 'legalMatter', 'courtFiling'])
        );
    }

    public function destroy(CourtFormInstance $courtFormInstance): JsonResponse
    {
        $this->authorize('delete', $courtFormInstance);

        $courtFormInstance->delete();

        return response()->json(['message' => 'Court form deleted successfully.']);
    }

    public function createFiling(Request $request, CourtFormInstance $courtFormInstance): JsonResponse
    {
        $this->authorize('update', $courtFormInstance);
        $this->authorize('create', CourtFiling::class);

        if ($courtFormInstance->court_filing_id !== null) {
            return response()->json(['message' => 'A filing already exists for this form.'], 422);
        }

        $user = $request->user();
        $matter = $courtFormInstance->legalMatter;
        $court = $request->string('court')->toString()
            ?: ($courtFormInstance->field_values['court_name'] ?? $matter->court_jurisdiction ?? 'Court');

        $filing = CourtFiling::query()->create([
            'organization_id' => $courtFormInstance->organization_id,
            'legal_matter_id' => $courtFormInstance->legal_matter_id,
            'court_form_instance_id' => $courtFormInstance->id,
            'title' => $courtFormInstance->title,
            'court' => $court,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $courtFormInstance->update([
            'court_filing_id' => $filing->id,
            'status' => 'ready_to_file',
        ]);

        return (new CourtFormInstanceResource(
            $courtFormInstance->fresh()->load(['template', 'legalMatter', 'courtFiling'])
        ))->additional(['filing' => new \App\Http\Resources\CourtFilingResource($filing)])
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
            'court_form_template_id' => [$partial ? 'sometimes' : 'required', 'integer', 'exists:court_form_templates,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'field_values' => ['nullable', 'array'],
            'status' => ['nullable', 'string', Rule::in(CourtFormInstance::STATUSES)],
        ]);
    }
}
