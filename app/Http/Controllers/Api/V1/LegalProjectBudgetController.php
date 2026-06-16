<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Resources\LegalProjectBudgetResource;
use App\Models\LegalMatter;
use App\Models\LegalProjectBudget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class LegalProjectBudgetController extends Controller
{
    use ResolvesOrganization;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', LegalProjectBudget::class);

        $organization = $this->organizationFor($request->user());

        $budgets = LegalProjectBudget::query()
            ->with(['legalMatter:id,title,matter_number'])
            ->where('organization_id', $organization->id)
            ->when($request->filled('legal_matter_id'), fn ($q) => $q->where('legal_matter_id', $request->integer('legal_matter_id')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->orderByDesc('updated_at')
            ->paginate($request->integer('per_page', 50));

        $totals = LegalProjectBudget::query()
            ->where('organization_id', $organization->id)
            ->when($request->filled('legal_matter_id'), fn ($q) => $q->where('legal_matter_id', $request->integer('legal_matter_id')))
            ->selectRaw('COALESCE(SUM(budgeted_amount), 0) as total_budgeted, COALESCE(SUM(actual_amount), 0) as total_actual')
            ->first();

        return LegalProjectBudgetResource::collection($budgets)
            ->additional(['meta' => [
                'categories' => LegalProjectBudget::CATEGORIES,
                'totals' => [
                    'budgeted' => round((float) ($totals->total_budgeted ?? 0), 2),
                    'actual' => round((float) ($totals->total_actual ?? 0), 2),
                ],
            ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', LegalProjectBudget::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);
        $data = $this->validatedData($request);

        LegalMatter::query()
            ->where('organization_id', $organization->id)
            ->findOrFail($data['legal_matter_id']);

        $budget = LegalProjectBudget::query()->create([
            ...$data,
            'organization_id' => $organization->id,
            'created_by' => $user->id,
        ]);

        return (new LegalProjectBudgetResource($budget->load('legalMatter')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(LegalProjectBudget $legalProjectBudget): LegalProjectBudgetResource
    {
        $this->authorize('view', $legalProjectBudget);

        return new LegalProjectBudgetResource($legalProjectBudget->load('legalMatter'));
    }

    public function update(Request $request, LegalProjectBudget $legalProjectBudget): LegalProjectBudgetResource
    {
        $this->authorize('update', $legalProjectBudget);

        $legalProjectBudget->update($this->validatedData($request, partial: true));

        return new LegalProjectBudgetResource($legalProjectBudget->fresh()->load('legalMatter'));
    }

    public function destroy(LegalProjectBudget $legalProjectBudget): JsonResponse
    {
        $this->authorize('delete', $legalProjectBudget);

        $legalProjectBudget->delete();

        return response()->json(['message' => 'Budget line deleted successfully.']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedData(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'legal_matter_id' => [$partial ? 'sometimes' : 'required', 'integer', 'exists:legal_matters,id'],
            'category' => ['nullable', 'string', Rule::in(LegalProjectBudget::CATEGORIES)],
            'description' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'budgeted_amount' => ['nullable', 'numeric', 'min:0'],
            'actual_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
