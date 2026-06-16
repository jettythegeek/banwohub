<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConflictCheckResource;
use App\Models\CaseNote;
use App\Models\Client;
use App\Models\ConflictCheck;
use App\Models\LegalMatter;
use App\Models\Party;
use App\Support\InAppNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ConflictCheckController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ConflictCheck::class);

        $organization = $this->organizationFor($request->user());

        $checks = ConflictCheck::query()
            ->with(['legalMatter:id,title,matter_number', 'intakeSubmission:id,submitter_name,status', 'requester:id,name', 'reviewer:id,name'])
            ->where('organization_id', $organization->id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('legal_matter_id'), fn ($q) => $q->where('legal_matter_id', $request->integer('legal_matter_id')))
            ->when($request->filled('intake_submission_id'), fn ($q) => $q->where('intake_submission_id', $request->integer('intake_submission_id')))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ConflictCheckResource::collection($checks);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ConflictCheck::class);

        $organization = $this->organizationFor($request->user());
        $data = $this->validatedData($request);
        if (isset($data['legal_matter_id'])) {
            $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);
        }
        if (isset($data['intake_submission_id'])) {
            $this->intakeSubmissionForOrganization((int) $data['intake_submission_id'], $organization->id);
        }

        $matches = $this->searchConflicts($organization->id, $data['search_terms']);
        $status = count($matches['clients']) + count($matches['parties']) + count($matches['cases']) + count($matches['notes']) > 0
            ? 'potential_conflict_found'
            : ($data['status'] ?? 'in_review');

        $check = ConflictCheck::query()->create([
            ...$data,
            'organization_id' => $organization->id,
            'requested_by' => $request->user()->id,
            'status' => $status,
            'matches' => $matches,
            'report' => $this->buildReport($data['search_terms'], $matches),
        ]);

        return (new ConflictCheckResource($check->load(['legalMatter', 'intakeSubmission', 'requester', 'reviewer'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ConflictCheck $conflictCheck): ConflictCheckResource
    {
        $this->authorize('view', $conflictCheck);

        return new ConflictCheckResource($conflictCheck->load(['legalMatter', 'intakeSubmission', 'requester', 'reviewer']));
    }

    public function update(Request $request, ConflictCheck $conflictCheck, InAppNotifier $notifier): ConflictCheckResource
    {
        $this->authorize('update', $conflictCheck);

        $data = $this->validatedData($request, partial: true);
        if (isset($data['legal_matter_id'])) {
            $this->legalMatterForOrganization((int) $data['legal_matter_id'], $conflictCheck->organization_id);
        }
        if (isset($data['intake_submission_id'])) {
            $this->intakeSubmissionForOrganization((int) $data['intake_submission_id'], $conflictCheck->organization_id);
        }
        if (isset($data['search_terms'])) {
            $data['matches'] = $this->searchConflicts($conflictCheck->organization_id, $data['search_terms']);
            $data['report'] = $this->buildReport($data['search_terms'], $data['matches']);
        }
        if (isset($data['status']) && in_array($data['status'], ['cleared', 'rejected', 'potential_conflict_found'], true)) {
            $data['reviewer_id'] = $request->user()->id;
            $data['reviewed_at'] = now();
            $data['decision'] = $data['decision'] ?? $data['status'];
        }

        $conflictCheck->update($data);

        if (isset($data['reviewed_at'])) {
            $notifier->notifyPermission(
                $conflictCheck->organization,
                'conflict-checks.view',
                'conflict_decision',
                'Conflict check decision',
                $conflictCheck->fresh()->status,
                ['conflict_check_id' => $conflictCheck->id],
                $request->user()
            );
        }

        return new ConflictCheckResource($conflictCheck->fresh()->load(['legalMatter', 'intakeSubmission', 'requester', 'reviewer']));
    }

    public function export(ConflictCheck $conflictCheck, Request $request)
    {
        $this->authorize('view', $conflictCheck);

        $format = $request->string('format', 'csv');
        $check = $conflictCheck->load(['legalMatter', 'requester', 'reviewer']);
        $report = [
            'search_terms' => $check->search_terms,
            'matches' => $check->matches,
            'status' => $check->status,
            'decision' => $check->decision,
            'notes' => $check->notes,
            'reviewer' => $check->reviewer?->name,
            'reviewed_at' => $check->reviewed_at?->toIso8601String(),
            'case' => $check->legalMatter?->only(['id', 'title', 'matter_number']),
            'generated_at' => $check->report['generated_at'] ?? now()->toIso8601String(),
        ];

        if ($format === 'html') {
            $html = view('exports.conflict-report', ['check' => $check, 'report' => $report])->render();

            return response($html, 200, [
                'Content-Type' => 'text/html',
                'Content-Disposition' => 'attachment; filename="conflict-check-'.$check->id.'.html"',
            ]);
        }

        $rows = [
            ['Field', 'Value'],
            ['Search terms', implode('; ', $check->search_terms ?? [])],
            ['Status', $check->status],
            ['Decision', (string) ($check->decision ?? '')],
            ['Reviewer', (string) ($check->reviewer?->name ?? '')],
            ['Reviewed at', (string) ($check->reviewed_at ?? '')],
            ['Notes', (string) ($check->notes ?? '')],
        ];
        foreach ($check->matches ?? [] as $bucket => $items) {
            foreach ($items as $item) {
                $rows[] = [ucfirst($bucket).' match', json_encode($item)];
            }
        }

        $csv = collect($rows)->map(fn (array $row) => '"'.implode('","', array_map(fn ($v) => str_replace('"', '""', (string) $v), $row)).'"')->implode("\n");

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="conflict-check-'.$check->id.'.csv"',
        ]);
    }

    public function destroy(ConflictCheck $conflictCheck): JsonResponse
    {
        $this->authorize('delete', $conflictCheck);

        $conflictCheck->delete();

        return response()->json(['message' => 'Conflict check deleted successfully.']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedData(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'legal_matter_id' => ['nullable', 'integer', 'exists:legal_matters,id'],
            'intake_submission_id' => ['nullable', 'integer', 'exists:intake_submissions,id'],
            'status' => ['nullable', 'string', Rule::in(ConflictCheck::STATUSES)],
            'search_terms' => [$partial ? 'sometimes' : 'required', 'array', 'min:1'],
            'search_terms.*' => ['string', 'max:255'],
            'decision' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    /**
     * @param  array<int, string>  $terms
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function searchConflicts(int $organizationId, array $terms): array
    {
        $terms = collect($terms)
            ->filter()
            ->map(fn (string $term) => trim($term))
            ->filter()
            ->unique()
            ->values();

        return [
            'clients' => Client::query()
                ->where('organization_id', $organizationId)
                ->where(function ($query) use ($terms) {
                    $terms->each(fn (string $term) => $query->orWhere('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('company_name', 'like', "%{$term}%")
                        ->orWhere('notes', 'like', "%{$term}%"));
                })
                ->limit(10)
                ->get(['id', 'name', 'email', 'company_name', 'status'])
                ->toArray(),
            'parties' => Party::query()
                ->where('organization_id', $organizationId)
                ->where(function ($query) use ($terms) {
                    $terms->each(fn (string $term) => $query->orWhere('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('notes', 'like', "%{$term}%"));
                })
                ->limit(10)
                ->get(['id', 'legal_matter_id', 'name', 'party_type'])
                ->toArray(),
            'cases' => LegalMatter::query()
                ->where('organization_id', $organizationId)
                ->where(function ($query) use ($terms) {
                    $terms->each(fn (string $term) => $query->orWhere('title', 'like', "%{$term}%")
                        ->orWhere('matter_number', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%"));
                })
                ->limit(10)
                ->get(['id', 'client_id', 'title', 'matter_number', 'status'])
                ->toArray(),
            'notes' => CaseNote::query()
                ->where('organization_id', $organizationId)
                ->where(function ($query) use ($terms) {
                    $terms->each(fn (string $term) => $query->orWhere('title', 'like', "%{$term}%")
                        ->orWhere('body', 'like', "%{$term}%"));
                })
                ->limit(10)
                ->get(['id', 'legal_matter_id', 'note_type', 'visibility', 'title'])
                ->toArray(),
        ];
    }

    /**
     * @param  array<int, string>  $terms
     * @param  array<string, array<int, array<string, mixed>>>  $matches
     * @return array<string, mixed>
     */
    protected function buildReport(array $terms, array $matches): array
    {
        return [
            'search_terms' => array_values($terms),
            'match_counts' => collect($matches)->map(fn (array $items) => count($items))->all(),
            'matches' => $matches,
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
