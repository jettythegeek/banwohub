<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\CourtFilingResource;
use App\Models\CourtFiling;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CourtFilingController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CourtFiling::class);

        $organization = $this->organizationFor($request->user());

        $filings = CourtFiling::query()
            ->with(['legalMatter:id,title,matter_number', 'filedByUser:id,name'])
            ->where('organization_id', $organization->id)
            ->when($request->filled('legal_matter_id'), fn ($q) => $q->where('legal_matter_id', $request->integer('legal_matter_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('court'), fn ($q) => $q->where('court', 'like', '%'.$request->string('court').'%'))
            ->orderByDesc('updated_at')
            ->paginate($request->integer('per_page', 25));

        return CourtFilingResource::collection($filings)
            ->additional(['meta' => [
                'statuses' => CourtFiling::STATUSES,
                'filing_methods' => CourtFiling::FILING_METHODS,
            ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CourtFiling::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);
        $data = $this->validatedData($request);

        $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);

        $filing = CourtFiling::query()->create([
            ...$data,
            'organization_id' => $organization->id,
            'created_by' => $user->id,
            'status' => $data['status'] ?? 'draft',
            'filing_method' => $data['filing_method'] ?? 'manual',
        ]);

        return (new CourtFilingResource($filing->load(['legalMatter', 'filedByUser'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(CourtFiling $courtFiling): CourtFilingResource
    {
        $this->authorize('view', $courtFiling);

        return new CourtFilingResource(
            $courtFiling->load(['legalMatter', 'filedByUser', 'courtFormInstance.template'])
        );
    }

    public function update(Request $request, CourtFiling $courtFiling): CourtFilingResource
    {
        $this->authorize('update', $courtFiling);

        if (array_key_exists('legal_matter_id', $request->all()) && $request->filled('legal_matter_id')) {
            $this->legalMatterForOrganization((int) $request->integer('legal_matter_id'), $courtFiling->organization_id);
        }

        $courtFiling->update($this->validatedData($request, partial: true));

        return new CourtFilingResource($courtFiling->fresh()->load(['legalMatter', 'filedByUser']));
    }

    public function destroy(CourtFiling $courtFiling): JsonResponse
    {
        $this->authorize('delete', $courtFiling);

        $courtFiling->delete();

        return response()->json(['message' => 'Filing deleted successfully.']);
    }

    public function updateStatus(Request $request, CourtFiling $courtFiling): CourtFilingResource
    {
        $this->authorize('update', $courtFiling);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(CourtFiling::STATUSES)],
            'court_response' => ['nullable', 'string'],
            'correction_deadline' => ['nullable', 'date'],
            'court_reference_number' => ['nullable', 'string', 'max:100'],
            'filed_by' => ['nullable', 'integer', 'exists:users,id'],
            'filing_date' => ['nullable', 'date'],
        ]);

        $nextStatus = $data['status'];
        if (! $courtFiling->canTransitionTo($nextStatus)) {
            throw ValidationException::withMessages([
                'status' => ["Cannot transition from {$courtFiling->status} to {$nextStatus}."],
            ]);
        }

        $updates = ['status' => $nextStatus];

        foreach (['court_response', 'correction_deadline', 'court_reference_number', 'filed_by', 'filing_date'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $data[$field];
            }
        }

        if ($nextStatus === 'filed' && ! array_key_exists('filing_date', $updates)) {
            $updates['filing_date'] = $courtFiling->filing_date ?? now()->toDateString();
        }

        if ($nextStatus === 'filed' && ! array_key_exists('filed_by', $updates)) {
            $updates['filed_by'] = $request->user()->id;
        }

        $courtFiling->update($updates);

        if ($courtFiling->courtFormInstance) {
            $courtFiling->courtFormInstance->update(['status' => $nextStatus === 'filed' ? 'filed' : $courtFiling->courtFormInstance->status]);
        }

        return new CourtFilingResource($courtFiling->fresh()->load(['legalMatter', 'filedByUser', 'courtFormInstance']));
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedData(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'legal_matter_id' => [$partial ? 'sometimes' : 'required', 'integer', 'exists:legal_matters,id'],
            'court_form_instance_id' => ['nullable', 'integer', 'exists:court_form_instances,id'],
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'court' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'filing_date' => ['nullable', 'date'],
            'filed_by' => ['nullable', 'integer', 'exists:users,id'],
            'filing_method' => ['nullable', 'string', Rule::in(CourtFiling::FILING_METHODS)],
            'court_reference_number' => ['nullable', 'string', 'max:100'],
            'document_ids' => ['nullable', 'array'],
            'document_ids.*' => ['integer', 'exists:legal_documents,id'],
            'status' => ['nullable', 'string', Rule::in(CourtFiling::STATUSES)],
            'court_response' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'correction_deadline' => ['nullable', 'date'],
        ]);
    }
}
