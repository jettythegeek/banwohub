<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\CourtFormTemplateResource;
use App\Models\CourtFormTemplate;
use App\Services\CourtFormPrefillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CourtFormTemplateController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function __construct(
        protected CourtFormPrefillService $prefillService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CourtFormTemplate::class);

        $organization = $this->organizationFor($request->user());

        $templates = CourtFormTemplate::query()
            ->availableToOrganization($organization->id)
            ->when($request->filled('jurisdiction'), fn ($q) => $q->where('jurisdiction', $request->string('jurisdiction')))
            ->when($request->filled('court'), fn ($q) => $q->where('court', $request->string('court')))
            ->when($request->filled('case_type'), fn ($q) => $q->where('case_type', $request->string('case_type')))
            ->when($request->filled('filing_type'), fn ($q) => $q->where('filing_type', $request->string('filing_type')))
            ->orderBy('jurisdiction')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 50));

        return CourtFormTemplateResource::collection($templates);
    }

    public function show(CourtFormTemplate $courtFormTemplate): CourtFormTemplateResource
    {
        $this->authorize('view', $courtFormTemplate);

        return new CourtFormTemplateResource($courtFormTemplate);
    }

    public function prefill(Request $request, CourtFormTemplate $courtFormTemplate): JsonResponse
    {
        $this->authorize('view', $courtFormTemplate);

        $data = $request->validate([
            'legal_matter_id' => ['required', 'integer', 'exists:legal_matters,id'],
        ]);

        $organization = $this->organizationFor($request->user());
        $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);

        return response()->json([
            'template' => new CourtFormTemplateResource($courtFormTemplate),
            ...$this->prefillService->prefill($courtFormTemplate, $matter),
        ]);
    }
}
