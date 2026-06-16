<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\LegalMatter;
use App\Models\TimeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    use ResolvesOrganization;

    public function summary(Request $request): JsonResponse
    {
        $organization = $this->organizationFor($request->user());
        $filters = $this->validatedDateFilters($request);

        return response()->json($this->buildSummary($organization->id, $filters));
    }

    public function exportCsv(Request $request): Response
    {
        $organization = $this->organizationFor($request->user());
        $filters = $this->validatedDateFilters($request);

        $dataset = $request->string('dataset')->toString();
        if ($dataset === '') {
            $dataset = 'all';
        }

        $summary = $this->buildSummary($organization->id, $filters);
        $rows = [];

        if (in_array($dataset, ['all', 'cases'], true)) {
            $rows[] = ['Cases by status'];
            $rows[] = ['Status', 'Count'];
            foreach ($summary['cases']['by_status'] as $row) {
                $rows[] = [$row['status'], (string) $row['count']];
            }
            $rows[] = ['', ''];
        }

        if (in_array($dataset, ['all', 'invoices'], true)) {
            $rows[] = ['Invoices'];
            $rows[] = ['Invoice number', 'Client', 'Status', 'Total', 'Paid', 'Balance due', 'Issue date'];
            $invoiceQuery = $this->invoiceQuery($organization->id, $filters)
                ->with('client:id,name')
                ->orderByDesc('issue_date');

            foreach ($invoiceQuery->get() as $invoice) {
                $rows[] = [
                    $invoice->invoice_number,
                    $invoice->client?->name ?? '',
                    $invoice->status,
                    (string) $invoice->total_amount,
                    (string) $invoice->amount_paid,
                    (string) $invoice->balance_due,
                    $invoice->issue_date?->toDateString() ?? '',
                ];
            }
            $rows[] = ['', ''];
        }

        if (in_array($dataset, ['all', 'time_by_lawyer'], true)) {
            $rows[] = ['Billable time by lawyer'];
            $rows[] = ['Lawyer', 'Billable hours', 'Billable amount', 'Entries'];
            foreach ($summary['time_by_lawyer'] as $row) {
                $rows[] = [
                    $row['name'],
                    (string) $row['billable_hours'],
                    (string) $row['billable_amount'],
                    (string) $row['entry_count'],
                ];
            }
        }

        $csv = collect($rows)
            ->map(fn (array $row) => '"'.implode('","', array_map(fn ($v) => str_replace('"', '""', (string) $v), $row)).'"')
            ->implode("\n");

        $filename = 'banwolaw-reports-'.now()->format('Y-m-d').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @return array{from_date: ?string, to_date: ?string}
     */
    protected function validatedDateFilters(Request $request): array
    {
        $data = $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'dataset' => ['nullable', 'string', Rule::in(['all', 'cases', 'invoices', 'time_by_lawyer'])],
        ]);

        return [
            'from_date' => isset($data['from_date']) ? Carbon::parse($data['from_date'])->toDateString() : null,
            'to_date' => isset($data['to_date']) ? Carbon::parse($data['to_date'])->toDateString() : null,
        ];
    }

    /**
     * @param  array{from_date: ?string, to_date: ?string}  $filters
     * @return array<string, mixed>
     */
    protected function buildSummary(int $organizationId, array $filters): array
    {
        $casesQuery = LegalMatter::query()->where('organization_id', $organizationId);
        $this->applyDateRange($casesQuery, 'created_at', $filters);

        $byStatus = (clone $casesQuery)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();

        $invoiceQuery = $this->invoiceQuery($organizationId, $filters);
        $invoices = (clone $invoiceQuery)->get(['total_amount', 'amount_paid', 'balance_due', 'status']);

        $unpaidStatuses = ['sent', 'partial', 'overdue'];
        $paidInvoices = $invoices->where('status', 'paid');

        $timeQuery = TimeEntry::query()
            ->where('organization_id', $organizationId)
            ->where('billable', true)
            ->where('is_running', false);
        $this->applyDateRange($timeQuery, 'started_at', $filters);

        $timeRows = (clone $timeQuery)
            ->with('user:id,name')
            ->get(['id', 'user_id', 'duration_minutes', 'rate']);

        $timeByLawyer = $timeRows
            ->groupBy('user_id')
            ->map(function ($entries, $userId) {
                $minutes = (int) $entries->sum('duration_minutes');
                $amount = $entries->reduce(function (float $carry, TimeEntry $entry): float {
                    return $carry + ($entry->rate !== null ? (float) $entry->rate * ($entry->duration_minutes / 60) : 0);
                }, 0.0);

                return [
                    'user_id' => (int) $userId,
                    'name' => $entries->first()?->user?->name ?? 'Unknown',
                    'billable_minutes' => $minutes,
                    'billable_hours' => round($minutes / 60, 2),
                    'billable_amount' => round($amount, 2),
                    'entry_count' => $entries->count(),
                ];
            })
            ->sortByDesc('billable_minutes')
            ->values()
            ->all();

        return [
            'filters' => $filters,
            'cases' => [
                'total' => (clone $casesQuery)->count(),
                'by_status' => $byStatus,
            ],
            'revenue' => [
                'total_billed' => round((float) $invoices->whereNotIn('status', ['draft', 'cancelled'])->sum('total_amount'), 2),
                'total_paid' => round((float) $invoices->sum('amount_paid'), 2),
                'paid_invoice_count' => $paidInvoices->count(),
                'paid_invoice_total' => round((float) $paidInvoices->sum('total_amount'), 2),
                'unpaid_total' => round((float) $invoices->whereIn('status', $unpaidStatuses)->sum('balance_due'), 2),
                'unpaid_invoice_count' => $invoices->whereIn('status', $unpaidStatuses)->count(),
            ],
            'time_by_lawyer' => $timeByLawyer,
        ];
    }

    /**
     * @param  array{from_date: ?string, to_date: ?string}  $filters
     */
    protected function invoiceQuery(int $organizationId, array $filters)
    {
        $query = Invoice::query()
            ->where('organization_id', $organizationId)
            ->whereNotIn('status', ['cancelled']);

        $this->applyDateRange($query, 'issue_date', $filters);

        return $query;
    }

    /**
     * @param  array{from_date: ?string, to_date: ?string}  $filters
     */
    protected function applyDateRange($query, string $column, array $filters): void
    {
        if ($filters['from_date'] !== null) {
            $query->whereDate($column, '>=', $filters['from_date']);
        }
        if ($filters['to_date'] !== null) {
            $query->whereDate($column, '<=', $filters['to_date']);
        }
    }
}
