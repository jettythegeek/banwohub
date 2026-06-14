<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Api\V1\Concerns\ValidatesOrganizationAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\CaseExpense;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Payment;
use App\Models\TimeEntry;
use App\Services\ApprovalWorkflowService;
use App\Services\NumberSequenceService;
use App\Support\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    use ResolvesOrganization;
    use ValidatesOrganizationAccess;

    public function agingSummary(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $organization = $this->organizationFor($request->user());
        $today = Carbon::today();

        $rows = Invoice::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', ['sent', 'partial', 'overdue'])
            ->where('balance_due', '>', 0)
            ->get(['balance_due', 'due_date', 'issue_date']);

        $buckets = [
            'current' => ['label' => '0–30 days', 'min_days' => 0, 'max_days' => 30, 'amount' => 0.0, 'count' => 0],
            '31_60' => ['label' => '31–60 days', 'min_days' => 31, 'max_days' => 60, 'amount' => 0.0, 'count' => 0],
            '61_90' => ['label' => '61–90 days', 'min_days' => 61, 'max_days' => 90, 'amount' => 0.0, 'count' => 0],
            'over_90' => ['label' => '90+ days', 'min_days' => 91, 'max_days' => null, 'amount' => 0.0, 'count' => 0],
        ];

        foreach ($rows as $invoice) {
            $reference = $invoice->due_date ?? $invoice->issue_date ?? $today;
            $days = max(0, (int) $reference->copy()->startOfDay()->diffInDays($today, false));
            $balance = (float) $invoice->balance_due;

            if ($days <= 30) {
                $key = 'current';
            } elseif ($days <= 60) {
                $key = '31_60';
            } elseif ($days <= 90) {
                $key = '61_90';
            } else {
                $key = 'over_90';
            }

            $buckets[$key]['amount'] += $balance;
            $buckets[$key]['count']++;
        }

        foreach ($buckets as $key => $bucket) {
            $buckets[$key]['amount'] = round($bucket['amount'], 2);
        }

        $totalOutstanding = round(array_sum(array_column($buckets, 'amount')), 2);

        return response()->json([
            'total_outstanding' => $totalOutstanding,
            'invoice_count' => $rows->count(),
            'buckets' => array_values($buckets),
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Invoice::class);

        $organization = $this->organizationFor($request->user());

        $query = Invoice::query()
            ->with(['client:id,name,email', 'legalMatter:id,title,matter_number'])
            ->where('organization_id', $organization->id)
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('legal_matter_id'), fn ($q) => $q->where('legal_matter_id', $request->integer('legal_matter_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%'.$request->string('search').'%';
                $q->where(function ($inner) use ($search) {
                    $inner->where('invoice_number', 'like', $search)
                        ->orWhereHas('client', fn ($client) => $client->where('name', 'like', $search));
                });
            });

        $invoices = (clone $query)
            ->latest('issue_date')
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return InvoiceResource::collection($invoices)
            ->additional(['meta' => ['summary' => $this->summarize(clone $query)]]);
    }

    public function store(Request $request, NumberSequenceService $numbers): JsonResponse
    {
        $this->authorize('create', Invoice::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);
        $data = $this->validatedInvoiceData($request);

        $client = $this->clientForOrganization((int) $data['client_id'], $organization->id);
        $matter = null;
        if (! empty($data['legal_matter_id'])) {
            $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);
            abort_unless($matter->client_id === $client->id, 422, 'The case does not belong to this client.');
        }

        $invoice = DB::transaction(function () use ($request, $organization, $user, $data, $client, $matter) {
            $invoice = Invoice::query()->create([
                'organization_id' => $organization->id,
                'client_id' => $client->id,
                'legal_matter_id' => $matter?->id,
                'created_by' => $user->id,
                'invoice_number' => $this->nextInvoiceNumber($organization->id),
                'status' => 'draft',
                'issue_date' => $data['issue_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? now()->addDays(30)->toDateString(),
                'tax_rate' => $data['tax_rate'] ?? 0,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'currency' => $data['currency'] ?? Currency::code(),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncLineItems($invoice, $data['line_items'], $organization->id);
            $this->recalculateTotals($invoice);

            return $invoice;
        });

        return (new InvoiceResource($invoice->load(['client', 'legalMatter', 'creator', 'lineItems'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        $this->authorize('view', $invoice);

        return new InvoiceResource(
            $invoice->load(['client', 'legalMatter', 'creator', 'lineItems.timeEntry', 'lineItems.serviceItem'])
        );
    }

    public function update(Request $request, Invoice $invoice): InvoiceResource
    {
        $this->authorize('update', $invoice);

        $organization = $this->organizationFor($request->user());
        $data = $this->validatedInvoiceData($request, partial: true);

        if (array_key_exists('client_id', $data)) {
            $this->clientForOrganization((int) $data['client_id'], $organization->id);
        }
        if (array_key_exists('legal_matter_id', $data) && $data['legal_matter_id'] !== null) {
            $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);
            $clientId = $data['client_id'] ?? $invoice->client_id;
            abort_unless($matter->client_id === (int) $clientId, 422, 'The case does not belong to this client.');
        }

        DB::transaction(function () use ($invoice, $data, $organization) {
            $invoice->update(collect($data)->except('line_items')->all());

            if (isset($data['line_items'])) {
                $invoice->lineItems()->delete();
                $this->syncLineItems($invoice, $data['line_items'], $organization->id);
            }

            $this->recalculateTotals($invoice->fresh());
        });

        return new InvoiceResource(
            $invoice->fresh()->load(['client', 'legalMatter', 'creator', 'lineItems.timeEntry'])
        );
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $this->authorize('delete', $invoice);

        DB::transaction(function () use ($invoice) {
            TimeEntry::query()
                ->where('invoice_id', $invoice->id)
                ->update(['invoice_id' => null]);

            CaseExpense::query()
                ->where('invoice_id', $invoice->id)
                ->update(['invoice_id' => null]);

            $invoice->lineItems()->delete();
            $invoice->delete();
        });

        return response()->json(['message' => 'Invoice deleted successfully.']);
    }

    public function generateFromTimeEntries(Request $request): JsonResponse
    {
        $this->authorize('create', Invoice::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);

        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'legal_matter_id' => ['nullable', 'integer', 'exists:legal_matters,id'],
            'time_entry_ids' => ['nullable', 'array', 'min:1'],
            'time_entry_ids.*' => ['integer', 'exists:time_entries,id'],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $client = $this->clientForOrganization((int) $data['client_id'], $organization->id);
        $matter = null;
        if (! empty($data['legal_matter_id'])) {
            $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);
            abort_unless($matter->client_id === $client->id, 422, 'The case does not belong to this client.');
        }

        $entriesQuery = TimeEntry::query()
            ->with(['user:id,name', 'legalTask:id,title'])
            ->where('organization_id', $organization->id)
            ->whereNull('invoice_id')
            ->where('billable', true)
            ->where('status', 'approved')
            ->where('is_running', false);

        if (! empty($data['time_entry_ids'])) {
            $entriesQuery->whereIn('id', $data['time_entry_ids']);
        } else {
            $entriesQuery->whereHas('legalMatter', fn ($q) => $q->where('client_id', $client->id));
            if ($matter) {
                $entriesQuery->where('legal_matter_id', $matter->id);
            }
        }

        $entries = $entriesQuery->get();
        abort_if($entries->isEmpty(), 422, 'No billable approved time entries are available to invoice.');

        foreach ($entries as $entry) {
            abort_if($entry->rate === null, 422, "Time entry #{$entry->id} has no hourly rate.");
        }

        $invoice = DB::transaction(function () use ($request, $organization, $user, $data, $client, $matter, $entries) {
            $resolvedMatterId = $matter?->id ?? $entries->first()?->legal_matter_id;

            $invoice = Invoice::query()->create([
                'organization_id' => $organization->id,
                'client_id' => $client->id,
                'legal_matter_id' => $resolvedMatterId,
                'created_by' => $user->id,
                'invoice_number' => $this->nextInvoiceNumber($organization->id),
                'status' => 'draft',
                'issue_date' => $data['issue_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? now()->addDays(30)->toDateString(),
                'tax_rate' => $data['tax_rate'] ?? 0,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'currency' => Currency::code(),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($entries->values() as $index => $entry) {
                $hours = round($entry->duration_minutes / 60, 2);
                $rate = (float) $entry->rate;
                $amount = round($rate * $hours, 2);
                $taskLabel = $entry->legalTask?->title;
                $userLabel = $entry->user?->name;
                $description = $entry->description ?: 'Billable time';
                if ($userLabel) {
                    $description .= " ({$userLabel})";
                }
                if ($taskLabel) {
                    $description .= " — {$taskLabel}";
                }

                InvoiceLineItem::query()->create([
                    'invoice_id' => $invoice->id,
                    'organization_id' => $organization->id,
                    'time_entry_id' => $entry->id,
                    'line_type' => 'time_entry',
                    'description' => $description,
                    'quantity' => $hours,
                    'unit_price' => $rate,
                    'amount' => $amount,
                    'sort_order' => $index,
                ]);

                $entry->update(['invoice_id' => $invoice->id]);
            }

            $this->recalculateTotals($invoice);

            return $invoice;
        });

        return (new InvoiceResource($invoice->load(['client', 'legalMatter', 'creator', 'lineItems.timeEntry'])))
            ->response()
            ->setStatusCode(201);
    }

    public function generateFromExpenses(Request $request): JsonResponse
    {
        $this->authorize('create', Invoice::class);

        $user = $request->user();
        $organization = $this->organizationFor($user);

        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'legal_matter_id' => ['nullable', 'integer', 'exists:legal_matters,id'],
            'expense_ids' => ['nullable', 'array', 'min:1'],
            'expense_ids.*' => ['integer', 'exists:case_expenses,id'],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $client = $this->clientForOrganization((int) $data['client_id'], $organization->id);
        $matter = null;
        if (! empty($data['legal_matter_id'])) {
            $matter = $this->legalMatterForOrganization((int) $data['legal_matter_id'], $organization->id);
            abort_unless($matter->client_id === $client->id, 422, 'The case does not belong to this client.');
        }

        $expensesQuery = CaseExpense::query()
            ->with(['user:id,name'])
            ->where('organization_id', $organization->id)
            ->whereNull('invoice_id')
            ->where('billable', true)
            ->where('status', 'approved');

        if (! empty($data['expense_ids'])) {
            $expensesQuery->whereIn('id', $data['expense_ids']);
        } else {
            $expensesQuery->whereHas('legalMatter', fn ($q) => $q->where('client_id', $client->id));
            if ($matter) {
                $expensesQuery->where('legal_matter_id', $matter->id);
            }
        }

        $expenses = $expensesQuery->get();
        abort_if($expenses->isEmpty(), 422, 'No billable approved expenses are available to invoice.');

        $invoice = DB::transaction(function () use ($organization, $user, $data, $client, $matter, $expenses) {
            $resolvedMatterId = $matter?->id ?? $expenses->first()?->legal_matter_id;

            $invoice = Invoice::query()->create([
                'organization_id' => $organization->id,
                'client_id' => $client->id,
                'legal_matter_id' => $resolvedMatterId,
                'created_by' => $user->id,
                'invoice_number' => $this->nextInvoiceNumber($organization->id),
                'status' => 'draft',
                'issue_date' => $data['issue_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? now()->addDays(30)->toDateString(),
                'tax_rate' => $data['tax_rate'] ?? 0,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'currency' => Currency::code(),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($expenses->values() as $index => $expense) {
                $amount = round((float) $expense->amount, 2);
                $description = $expense->description;
                if ($expense->category) {
                    $description = "[{$expense->category}] {$description}";
                }

                InvoiceLineItem::query()->create([
                    'invoice_id' => $invoice->id,
                    'organization_id' => $organization->id,
                    'case_expense_id' => $expense->id,
                    'line_type' => 'expense',
                    'description' => $description,
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'amount' => $amount,
                    'sort_order' => $index,
                ]);

                $expense->update(['invoice_id' => $invoice->id]);
            }

            $this->recalculateTotals($invoice);

            return $invoice;
        });

        return (new InvoiceResource($invoice->load(['client', 'legalMatter', 'creator', 'lineItems.caseExpense'])))
            ->response()
            ->setStatusCode(201);
    }

    public function markSent(Invoice $invoice, ApprovalWorkflowService $workflow): InvoiceResource
    {
        $this->authorize('send', $invoice);

        $workflow->assertSendAllowed($invoice);

        $invoice->update([
            'status' => 'sent',
            'sent_at' => $invoice->sent_at ?? now(),
        ]);

        $workflow->markFinalized($invoice);

        return new InvoiceResource(
            $invoice->fresh()->load(['client', 'legalMatter', 'creator', 'lineItems.timeEntry'])
        );
    }

    public function recordPayment(Request $request, Invoice $invoice, \App\Services\InvoicePaymentService $paymentService): InvoiceResource
    {
        $this->authorize('recordPayment', $invoice);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', Rule::in(Payment::MANUAL_GATEWAYS)],
            'notes' => ['nullable', 'string'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $method = $data['payment_method'] ?? 'bank_transfer';
        $paidAt = isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : null;

        $paymentService->applyPayment(
            invoice: $invoice,
            amount: (float) $data['amount'],
            gateway: $method,
            externalId: null,
            metadata: [
                'recorded_by' => $request->user()?->id,
                'payment_method' => $method,
            ],
            paymentMethod: $method,
            notes: $data['notes'] ?? null,
            paidAt: $paidAt,
        );

        return new InvoiceResource(
            $invoice->fresh()->load(['client', 'legalMatter', 'creator', 'lineItems.timeEntry'])
        );
    }

    public function exportPdf(Invoice $invoice)
    {
        $this->authorize('export', $invoice);

        $invoice->load(['client', 'legalMatter', 'lineItems', 'organization']);

        $html = view('invoices.pdf', ['invoice' => $invoice])->render();
        $filename = str_replace('"', '', $invoice->invoice_number);

        if (class_exists(\Dompdf\Dompdf::class)) {
            $dompdf = new \Dompdf\Dompdf;
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4');
            $dompdf->render();

            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
            ]);
        }

        return response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.html"',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $lineItems
     */
    protected function syncLineItems(Invoice $invoice, array $lineItems, int $organizationId): void
    {
        foreach (array_values($lineItems) as $index => $item) {
            $quantity = round((float) ($item['quantity'] ?? 1), 2);
            $unitPrice = round((float) ($item['unit_price'] ?? 0), 2);
            $amount = round($quantity * $unitPrice, 2);

            InvoiceLineItem::query()->create([
                'invoice_id' => $invoice->id,
                'organization_id' => $organizationId,
                'time_entry_id' => $item['time_entry_id'] ?? null,
                'case_expense_id' => $item['case_expense_id'] ?? null,
                'service_item_id' => $item['service_item_id'] ?? null,
                'line_type' => $item['line_type'] ?? 'service',
                'description' => $item['description'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'amount' => $amount,
                'sort_order' => $item['sort_order'] ?? $index,
            ]);
        }
    }

    protected function recalculateTotals(Invoice $invoice): void
    {
        $invoice->load('lineItems');
        $subtotal = round((float) $invoice->lineItems->sum('amount'), 2);
        $taxRate = (float) $invoice->tax_rate;
        $taxAmount = round($subtotal * ($taxRate / 100), 2);
        $discount = round((float) $invoice->discount_amount, 2);
        $total = max(0, round($subtotal + $taxAmount - $discount, 2));
        $paid = round((float) $invoice->amount_paid, 2);
        $balance = max(0, round($total - $paid, 2));

        $status = $invoice->status;
        if ($paid >= $total && $total > 0) {
            $status = 'paid';
            $balance = 0;
        } elseif ($paid > 0 && $balance > 0 && ! in_array($status, ['draft', 'cancelled'], true)) {
            $status = 'partial';
        }

        $invoice->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $total,
            'balance_due' => $balance,
            'status' => $status,
            'paid_at' => $status === 'paid' ? ($invoice->paid_at ?? now()) : $invoice->paid_at,
        ]);
    }

    protected function nextInvoiceNumber(int $organizationId): string
    {
        return app(NumberSequenceService::class)->nextInvoiceNumber($organizationId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedInvoiceData(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'client_id' => [$partial ? 'sometimes' : 'required', 'integer', 'exists:clients,id'],
            'legal_matter_id' => ['nullable', 'integer', 'exists:legal_matters,id'],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
            'line_items' => [$partial ? 'sometimes' : 'required', 'array', 'min:1'],
            'line_items.*.description' => ['required_with:line_items', 'string'],
            'line_items.*.quantity' => ['required_with:line_items', 'numeric', 'min:0'],
            'line_items.*.unit_price' => ['required_with:line_items', 'numeric', 'min:0'],
            'line_items.*.line_type' => ['nullable', 'string', Rule::in(InvoiceLineItem::TYPES)],
            'line_items.*.time_entry_id' => ['nullable', 'integer', 'exists:time_entries,id'],
            'line_items.*.case_expense_id' => ['nullable', 'integer', 'exists:case_expenses,id'],
            'line_items.*.service_item_id' => ['nullable', 'integer', 'exists:service_items,id'],
            'line_items.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Invoice>  $query
     * @return array<string, mixed>
     */
    protected function summarize($query): array
    {
        $rows = $query->get(['total_amount', 'balance_due', 'status']);

        return [
            'invoice_count' => $rows->count(),
            'total_billed' => round((float) $rows->sum('total_amount'), 2),
            'outstanding_balance' => round((float) $rows->whereIn('status', ['sent', 'partial', 'overdue'])->sum('balance_due'), 2),
            'unpaid_count' => $rows->whereIn('status', ['sent', 'partial', 'overdue'])->count(),
        ];
    }
}
