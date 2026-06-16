<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\CaseExpenseResource;
use App\Models\CaseExpense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CaseExpenseController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CaseExpense::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);

        $query = CaseExpense::query()
            ->with(['legalMatter:id,title,matter_number', 'user:id,name'])
            ->where('organization_id', $organization->id)
            ->when(! $user->can('expenses.view-all'), fn ($q) => $q->where('user_id', $user->id))
            ->when($request->filled('legal_matter_id'), fn ($q) => $q->where('legal_matter_id', $request->integer('legal_matter_id')))
            ->when($request->has('billable'), fn ($q) => $q->where('billable', $request->boolean('billable')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')));

        $expenses = (clone $query)
            ->orderByDesc('expense_date')
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return CaseExpenseResource::collection($expenses)
            ->additional(['meta' => ['summary' => $this->summarize(clone $query)]]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CaseExpense::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);
        $data = $this->validatedData($request);

        $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);

        $expense = CaseExpense::query()->create([
            ...$data,
            'organization_id' => $organization->id,
            'user_id' => $data['user_id'] ?? $user->id,
            'created_by' => $user->id,
            'status' => $data['status'] ?? 'approved',
        ]);

        return (new CaseExpenseResource($expense->load(['legalMatter', 'user'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(CaseExpense $caseExpense): CaseExpenseResource
    {
        $this->authorize('view', $caseExpense);

        return new CaseExpenseResource($caseExpense->load(['legalMatter', 'user']));
    }

    public function update(Request $request, CaseExpense $caseExpense): CaseExpenseResource
    {
        $this->authorize('update', $caseExpense);

        if (array_key_exists('legal_matter_id', $request->all()) && $request->filled('legal_matter_id')) {
            $this->legalMatterForOrganization((int) $request->integer('legal_matter_id'), $caseExpense->organization_id);
        }

        $caseExpense->update($this->validatedData($request, partial: true));

        return new CaseExpenseResource($caseExpense->fresh()->load(['legalMatter', 'user']));
    }

    public function destroy(CaseExpense $caseExpense): JsonResponse
    {
        $this->authorize('delete', $caseExpense);

        $caseExpense->delete();

        return response()->json(['message' => 'Expense deleted successfully.']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedData(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'legal_matter_id' => [$partial ? 'sometimes' : 'required', 'integer', 'exists:legal_matters,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => [$partial ? 'sometimes' : 'required', 'string', 'max:2000'],
            'amount' => [$partial ? 'sometimes' : 'required', 'numeric', 'min:0.01'],
            'expense_date' => [$partial ? 'sometimes' : 'required', 'date'],
            'billable' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', Rule::in(CaseExpense::STATUSES)],
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<CaseExpense>  $query
     * @return array<string, mixed>
     */
    protected function summarize($query): array
    {
        $rows = $query->get(['amount', 'billable']);

        return [
            'expense_count' => $rows->count(),
            'total_amount' => round((float) $rows->sum('amount'), 2),
            'billable_amount' => round((float) $rows->where('billable', true)->sum('amount'), 2),
        ];
    }
}
