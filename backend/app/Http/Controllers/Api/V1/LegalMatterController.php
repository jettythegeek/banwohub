<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ResolvesOrganization;
use App\Http\Resources\LegalMatterResource;
use App\Models\CalendarEvent;
use App\Models\CaseExpense;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\LegalMatter;
use App\Models\Party;
use App\Models\TimeEntry;
use App\Models\TrustLedgerEntry;
use App\Services\TrustLedgerService;
use App\Services\AutoTaskService;
use App\Services\NumberSequenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class LegalMatterController extends Controller
{
    use ResolvesOrganization;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', LegalMatter::class);

        $organization = $this->organizationFor($request->user());

        $matters = LegalMatter::query()
            ->with(['client:id,name', 'leadLawyer:id,name'])
            ->where('organization_id', $organization->id)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->string('search').'%';
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', $search)
                        ->orWhere('matter_number', 'like', $search);
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return LegalMatterResource::collection($matters);
    }

    public function store(Request $request, NumberSequenceService $numbers): JsonResponse
    {
        $this->authorize('create', LegalMatter::class);

        $organization = $this->organizationFor($request->user());
        $data = $this->validatedMatterData($request, $organization->id);

        if (empty($data['matter_number'])) {
            $data['matter_number'] = $numbers->nextCaseNumber($organization->id);
        }
        $data['stage'] = $data['stage'] ?? 'lead';
        $data['matter_stage'] = $data['matter_stage'] ?? 'intake';
        $data['status'] = $data['status'] ?? 'new';
        $data['priority'] = $data['priority'] ?? 'normal';

        $matter = LegalMatter::query()->create([
            ...$data,
            'organization_id' => $organization->id,
            'created_by' => $request->user()->id,
        ]);

        $this->syncParties($request, $matter, $organization->id);
        $this->syncAssignments($request, $matter);

        activity('case')
            ->performedOn($matter)
            ->causedBy($request->user())
            ->withProperties(['event' => 'created'])
            ->log('Case created');

        return (new LegalMatterResource($matter->load(['client', 'leadLawyer', 'parties', 'assignedStaff'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(LegalMatter $legalMatter): LegalMatterResource
    {
        $this->authorize('view', $legalMatter);

        $legalMatter->load(['client', 'leadLawyer', 'parties', 'assignedStaff']);

        return new LegalMatterResource($legalMatter);
    }

    public function overviewMetrics(LegalMatter $legalMatter): JsonResponse
    {
        $this->authorize('view', $legalMatter);

        $unbilledEntries = TimeEntry::query()
            ->where('legal_matter_id', $legalMatter->id)
            ->where('billable', true)
            ->where('status', 'approved')
            ->whereNull('invoice_id')
            ->where('is_running', false)
            ->get(['duration_minutes', 'rate']);

        $unbilledRevenue = round($unbilledEntries->reduce(function (float $carry, TimeEntry $entry): float {
            if ($entry->rate === null) {
                return $carry;
            }

            return $carry + ((float) $entry->rate) * ($entry->duration_minutes / 60);
        }, 0.0), 2);

        $expenseTotal = round((float) CaseExpense::query()
            ->where('legal_matter_id', $legalMatter->id)
            ->sum('amount'), 2);

        $fixedFee = $legalMatter->fixed_fee_amount !== null ? (float) $legalMatter->fixed_fee_amount : null;
        $caseValue = $fixedFee ?? round($unbilledRevenue + $expenseTotal, 2);

        $retainerMinimum = $legalMatter->retainer_minimum_amount !== null
            ? (float) $legalMatter->retainer_minimum_amount
            : null;

        $trustLedgerService = app(TrustLedgerService::class);
        $trustBalance = $trustLedgerService->balanceForMatter($legalMatter->id);
        $hasTrustEntries = TrustLedgerEntry::query()
            ->where('legal_matter_id', $legalMatter->id)
            ->exists();

        $now = now();
        $deadlineWindow = $now->copy()->addDays(90);

        $deadlinesCount = CalendarEvent::query()
            ->where('legal_matter_id', $legalMatter->id)
            ->whereIn('event_type', CalendarEvent::DEADLINE_EVENT_TYPES)
            ->where('starts_at', '>=', $now)
            ->where('starts_at', '<=', $deadlineWindow)
            ->count();

        $nextDeadline = CalendarEvent::query()
            ->where('legal_matter_id', $legalMatter->id)
            ->whereIn('event_type', CalendarEvent::DEADLINE_EVENT_TYPES)
            ->where('starts_at', '>=', $now)
            ->orderBy('starts_at')
            ->first(['id', 'title', 'starts_at', 'event_type']);

        $trendStart = Carbon::now()->subMonths(5)->startOfMonth();
        $invoices = Invoice::query()
            ->where('legal_matter_id', $legalMatter->id)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->where('issue_date', '>=', $trendStart)
            ->get(['issue_date', 'total_amount']);

        $billingTrend = collect(range(0, 5))->map(function (int $offset) use ($trendStart, $invoices) {
            $month = $trendStart->copy()->addMonths($offset);
            $key = $month->format('Y-m');
            $amount = $invoices
                ->filter(fn (Invoice $invoice) => $invoice->issue_date?->format('Y-m') === $key)
                ->sum(fn (Invoice $invoice) => (float) $invoice->total_amount);

            return [
                'month' => $key,
                'label' => $month->format('M Y'),
                'amount' => round((float) $amount, 2),
            ];
        })->values()->all();

        $trustLedger = TrustLedgerEntry::query()
            ->where('legal_matter_id', $legalMatter->id)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'entry_type', 'amount', 'description', 'occurred_at'])
            ->map(fn (TrustLedgerEntry $entry) => [
                'id' => $entry->id,
                'entry_type' => $entry->entry_type,
                'amount' => (float) $entry->amount,
                'description' => $entry->description,
                'occurred_at' => $entry->occurred_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return response()->json([
            'unbilled_revenue' => $unbilledRevenue,
            'case_value' => $caseValue,
            'case_value_source' => $fixedFee !== null ? 'fixed_fee' : 'estimated',
            'trust_balance' => $hasTrustEntries || $trustBalance !== 0.0 ? $trustBalance : null,
            'retainer_minimum' => $retainerMinimum,
            'trust_status' => $legalMatter->billing_type !== 'retainer'
                ? 'not_applicable'
                : ($hasTrustEntries ? 'active' : 'empty'),
            'trust_ledger' => $trustLedger,
            'deadlines_count' => $deadlinesCount,
            'next_deadline' => $nextDeadline ? [
                'id' => $nextDeadline->id,
                'title' => $nextDeadline->title,
                'starts_at' => $nextDeadline->starts_at?->toIso8601String(),
                'event_type' => $nextDeadline->event_type,
            ] : null,
            'billing_trend' => $billingTrend,
        ]);
    }

    public function update(Request $request, LegalMatter $legalMatter, AutoTaskService $autoTasks): LegalMatterResource
    {
        $this->authorize('update', $legalMatter);

        $previousStatus = $legalMatter->status;
        $data = $this->validatedMatterData($request, $legalMatter->organization_id, partial: true);

        $legalMatter->update($data);
        $this->syncParties($request, $legalMatter, $legalMatter->organization_id);
        $this->syncAssignments($request, $legalMatter);

        if (isset($data['status']) && $data['status'] !== $previousStatus) {
            activity('case')
                ->performedOn($legalMatter)
                ->causedBy($request->user())
                ->withProperties(['from' => $previousStatus, 'to' => $data['status'], 'legal_matter_id' => $legalMatter->id])
                ->log('Case status changed');
            $autoTasks->onCaseStatusChanged($legalMatter, $previousStatus, $data['status'], $request->user());
        }

        return new LegalMatterResource($legalMatter->fresh()->load(['client', 'leadLawyer', 'parties', 'assignedStaff']));
    }

    public function destroy(LegalMatter $legalMatter): JsonResponse
    {
        $this->authorize('delete', $legalMatter);

        $legalMatter->delete();

        return response()->json(['message' => 'Case deleted successfully.']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedMatterData(Request $request, int $organizationId, bool $partial = false): array
    {
        $rules = [
            'client_id' => [$partial ? 'sometimes' : 'required', 'exists:clients,id'],
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'matter_number' => ['nullable', 'string', 'max:100'],
            'practice_area' => ['nullable', 'string', 'max:100'],
            'case_type' => ['nullable', 'string', 'max:100'],
            'court_jurisdiction' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'stage' => ['nullable', 'string', Rule::in(LegalMatter::STAGES)],
            'matter_stage' => ['nullable', 'string', Rule::in(LegalMatter::MATTER_STAGES)],
            'priority' => ['nullable', 'string', Rule::in(LegalMatter::PRIORITIES)],
            'opened_at' => ['nullable', 'date'],
            'expected_close_at' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'billing_type' => ['nullable', 'string', Rule::in(LegalMatter::BILLING_TYPES)],
            'billing_rate' => ['nullable', 'numeric', 'min:0'],
            'fixed_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'retainer_minimum_amount' => ['nullable', 'numeric', 'min:0'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'lead_lawyer_id' => ['nullable', 'exists:users,id'],
            'opposing_party' => ['nullable', 'string', 'max:255'],
            'assigned_staff_ids' => ['nullable', 'array'],
            'assigned_staff_ids.*' => ['integer', 'exists:users,id'],
        ];

        $data = $request->validate($rules);

        if (isset($data['client_id'])) {
            $client = Client::query()->findOrFail($data['client_id']);
            abort_unless($client->organization_id === $organizationId, 422, 'Client must belong to Banwolaw.');
        }

        unset($data['opposing_party'], $data['assigned_staff_ids']);

        return $data;
    }

    protected function syncParties(Request $request, LegalMatter $matter, int $organizationId): void
    {
        if (! $request->filled('opposing_party')) {
            return;
        }

        Party::query()->updateOrCreate(
            [
                'legal_matter_id' => $matter->id,
                'party_type' => 'opposing',
            ],
            [
                'organization_id' => $organizationId,
                'name' => $request->string('opposing_party'),
            ]
        );
    }

    protected function syncAssignments(Request $request, LegalMatter $matter): void
    {
        if (! $request->has('assigned_staff_ids')) {
            return;
        }

        $sync = collect($request->input('assigned_staff_ids', []))
            ->mapWithKeys(fn ($id) => [(int) $id => ['role' => 'support']])
            ->all();

        $matter->assignedStaff()->sync($sync);
    }
}
