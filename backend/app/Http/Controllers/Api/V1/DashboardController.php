<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ResolvesOrganization;
use App\Http\Resources\MessageThreadResource;
use App\Models\ApprovalRequest;
use App\Models\Client;
use App\Models\CourtFiling;
use App\Models\Invoice;
use App\Models\LegalDocument;
use App\Models\LegalMatter;
use App\Models\LegalMotion;
use App\Models\LegalTask;
use App\Models\MessageThread;
use App\Models\Payment;
use App\Models\User;
use App\Services\LegalProjectWorkloadService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ResolvesOrganization;

    private const ACTIVE_STATUSES = ['new', 'active', 'in_court', 'awaiting_client_response'];

    private const OPEN_TASK_STATUSES = ['not_started', 'in_progress', 'awaiting_review', 'blocked', 'overdue'];

    public function index(Request $request): JsonResponse
    {
        $organization = $this->organizationFor($request->user());
        $user = $request->user();
        $dashboardType = $this->resolveDashboardType($user);
        $scopedToAssigned = $dashboardType !== 'admin';

        $matterQuery = LegalMatter::query()->where('organization_id', $organization->id);
        if ($scopedToAssigned) {
            $this->scopeToAssignedMatters($matterQuery, $user);
        }

        $assignedMatterIds = $scopedToAssigned
            ? (clone $matterQuery)->pluck('id')
            : collect();

        $filingQuery = CourtFiling::query()->where('organization_id', $organization->id);
        $motionQuery = LegalMotion::query()->where('organization_id', $organization->id);

        if ($scopedToAssigned && $assignedMatterIds->isNotEmpty()) {
            $filingQuery->whereIn('legal_matter_id', $assignedMatterIds);
            $motionQuery->whereIn('legal_matter_id', $assignedMatterIds);
        } elseif ($scopedToAssigned) {
            $filingQuery->whereRaw('1 = 0');
            $motionQuery->whereRaw('1 = 0');
        }

        $projectTotals = app(LegalProjectWorkloadService::class)
            ->summary($organization->id)['totals'];

        $assignedCasesCount = LegalMatter::query()
            ->where('organization_id', $organization->id)
            ->where(function ($q) use ($user) {
                $q->where('lead_lawyer_id', $user->id)
                    ->orWhereHas('assignedStaff', fn ($sq) => $sq->where('users.id', $user->id));
            })
            ->count();

        $stats = [
            'active_cases' => (clone $matterQuery)
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->count(),
            'total_clients' => $scopedToAssigned
                ? Client::query()
                    ->where('organization_id', $organization->id)
                    ->where('status', 'active')
                    ->whereHas('legalMatters', fn ($q) => $q->whereIn('legal_matters.id', $assignedMatterIds))
                    ->count()
                : Client::query()
                    ->where('organization_id', $organization->id)
                    ->where('status', 'active')
                    ->count(),
            'assigned_cases' => $assignedCasesCount,
            'open_tasks' => LegalTask::query()
                ->where('organization_id', $organization->id)
                ->where('assignee_id', $user->id)
                ->whereIn('status', self::OPEN_TASK_STATUSES)
                ->count(),
            'overdue_tasks' => LegalTask::query()
                ->where('organization_id', $organization->id)
                ->where('assignee_id', $user->id)
                ->whereIn('status', self::OPEN_TASK_STATUSES)
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->count(),
            'unread_messages' => $user->can('messages.view')
                ? $this->unreadMessagesCount($organization->id, $user, $scopedToAssigned, $assignedMatterIds)
                : 0,
        ];

        $legalOps = [
            'cases' => [
                'total' => $scopedToAssigned ? $assignedCasesCount : (clone $matterQuery)->count(),
                'active' => (clone $matterQuery)->whereIn('status', self::ACTIVE_STATUSES)->count(),
                'new' => (clone $matterQuery)->where('status', 'new')->count(),
                'assigned' => $assignedCasesCount,
            ],
            'filings' => [
                'total' => (clone $filingQuery)->count(),
                'pending_court' => (clone $filingQuery)
                    ->whereIn('status', ['filed', 'resubmitted', 'hearing_date_assigned'])
                    ->count(),
                'corrections' => (clone $filingQuery)->where('status', 'correction_required')->count(),
                'completed' => (clone $filingQuery)->where('status', 'completed')->count(),
            ],
            'motions' => [
                'total' => (clone $motionQuery)->count(),
                'draft' => (clone $motionQuery)->where('status', 'draft')->count(),
                'review' => (clone $motionQuery)->where('status', 'review')->count(),
                'filing_ready' => (clone $motionQuery)->where('status', 'filing_ready')->count(),
            ],
            'projects' => [
                'open_matters' => $scopedToAssigned
                    ? (clone $matterQuery)->whereNotIn('status', ['closed', 'archived'])->count()
                    : $projectTotals['open_matters'],
                'open_tasks' => $scopedToAssigned
                    ? LegalTask::query()
                        ->where('organization_id', $organization->id)
                        ->where('assignee_id', $user->id)
                        ->whereIn('status', self::OPEN_TASK_STATUSES)
                        ->count()
                    : $projectTotals['open_tasks'],
                'overdue_tasks' => $scopedToAssigned
                    ? $stats['overdue_tasks']
                    : $projectTotals['overdue_tasks'],
                'pending_milestones' => $scopedToAssigned ? 0 : $projectTotals['pending_milestones'],
            ],
        ];

        $myTasks = LegalTask::query()
            ->with('legalMatter:id,title,matter_number')
            ->where('organization_id', $organization->id)
            ->where('assignee_id', $user->id)
            ->whereIn('status', self::OPEN_TASK_STATUSES)
            ->orderByRaw('due_at is null, due_at asc')
            ->limit(8)
            ->get(['id', 'legal_matter_id', 'title', 'status', 'priority', 'due_at'])
            ->map(fn (LegalTask $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'priority' => $task->priority,
                'due_at' => $task->due_at?->toIso8601String(),
                'is_overdue' => $task->due_at !== null && $task->due_at->isPast() && $task->status !== 'completed',
                'case' => $task->legalMatter ? [
                    'id' => $task->legalMatter->id,
                    'title' => $task->legalMatter->title,
                    'matter_number' => $task->legalMatter->matter_number,
                ] : null,
            ]);

        $recentClientsQuery = Client::query()->where('organization_id', $organization->id);
        if ($scopedToAssigned && $assignedMatterIds->isNotEmpty()) {
            $recentClientsQuery->whereHas(
                'legalMatters',
                fn ($q) => $q->whereIn('legal_matters.id', $assignedMatterIds)
            );
        } elseif ($scopedToAssigned) {
            $recentClientsQuery->whereRaw('1 = 0');
        }

        $recentClients = $dashboardType === 'admin'
            ? $recentClientsQuery->latest()->limit(5)->get(['id', 'name', 'status', 'created_at'])
            : collect();

        $recentCases = (clone $matterQuery)
            ->with('client:id,name')
            ->latest()
            ->limit(5)
            ->get(['id', 'title', 'status', 'client_id', 'created_at']);

        $charts = [
            'cases_by_status' => $this->groupCountsByStatus(clone $matterQuery, 'status'),
            'filings_by_status' => $this->groupFilingCounts(clone $filingQuery),
            'motions_by_status' => $this->groupMotionCounts(clone $motionQuery),
            'invoices_by_status' => $dashboardType === 'admin'
                ? $this->invoicesByStatus($organization->id)
                : [],
            'task_workload' => $this->taskWorkload(
                $organization->id,
                $user,
                $scopedToAssigned
            ),
            'revenue_trend' => $dashboardType === 'admin'
                ? $this->revenueTrend($organization->id)
                : [],
            'case_activity_trend' => $this->activityTrend(clone $matterQuery),
            'filing_activity_trend' => $this->activityTrend(clone $filingQuery),
        ];

        $payload = [
            'dashboard_type' => $dashboardType,
            'stats' => $stats,
            'legal_ops' => $legalOps,
            'charts' => $charts,
            'recent_clients' => $recentClients,
            'recent_cases' => $recentCases,
            'my_tasks' => $myTasks,
        ];

        if ($user->can('messages.view')) {
            $payload['messages_preview'] = $this->messagesPreview(
                $organization->id,
                $user,
                $scopedToAssigned,
                $assignedMatterIds
            );
        }

        if ($user->can('approvals.review')) {
            $payload['pending_approvals'] = $this->pendingApprovals($organization->id, $user);
        }

        if ($dashboardType === 'paralegal') {
            $payload['documents_attention'] = $this->documentsNeedingAttention(
                $organization->id,
                $assignedMatterIds
            );
        }

        return response()->json($payload);
    }

    private function resolveDashboardType(User $user): string
    {
        if ($user->hasRole(['Firm Admin', 'System Admin', 'Partner'])) {
            return 'admin';
        }

        if ($user->hasRole('Paralegal')) {
            return 'paralegal';
        }

        return 'lawyer';
    }

    private function scopeToAssignedMatters(Builder $query, User $user): void
    {
        $query->where(function ($q) use ($user) {
            $q->where('lead_lawyer_id', $user->id)
                ->orWhereHas('assignedStaff', fn ($sq) => $sq->where('users.id', $user->id));
        });
    }

    /**
     * @return list<array{status: string, count: int}>
     */
    private function groupCountsByStatus(Builder $query, string $column): array
    {
        return $query
            ->selectRaw("{$column}, count(*) as count")
            ->groupBy($column)
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->{$column},
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{status: string, count: int}>
     */
    private function groupFilingCounts(Builder $query): array
    {
        return $query
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
    }

    /**
     * @return list<array{status: string, count: int}>
     */
    private function groupMotionCounts(Builder $query): array
    {
        return $query
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
    }

    /**
     * @return list<array{status: string, count: int, amount: float}>
     */
    private function invoicesByStatus(int $organizationId): array
    {
        $invoices = Invoice::query()
            ->where('organization_id', $organizationId)
            ->whereNotIn('status', ['cancelled'])
            ->get(['status', 'balance_due', 'total_amount']);

        $invoiceGroups = [
            'paid' => $invoices->where('status', 'paid'),
            'pending' => $invoices->whereIn('status', ['sent', 'partial', 'draft']),
            'overdue' => $invoices->where('status', 'overdue'),
        ];

        return collect(['paid', 'pending', 'overdue'])
            ->map(fn (string $key) => [
                'status' => $key,
                'count' => $invoiceGroups[$key]->count(),
                'amount' => round((float) match ($key) {
                    'paid' => $invoiceGroups[$key]->sum('total_amount'),
                    default => $invoiceGroups[$key]->sum('balance_due'),
                }, 2),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{status: string, count: int}>
     */
    private function taskWorkload(int $organizationId, User $user, bool $personalOnly): array
    {
        $query = LegalTask::query()
            ->where('organization_id', $organizationId)
            ->whereIn('status', self::OPEN_TASK_STATUSES);

        if ($personalOnly) {
            $query->where('assignee_id', $user->id);
        }

        return $query
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
    }

    /**
     * @return list<array{month: string, label: string, amount: float}>
     */
    private function revenueTrend(int $organizationId): array
    {
        $revenueTrend = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();

            $amount = Payment::query()
                ->where('status', 'completed')
                ->whereHas('invoice', fn ($q) => $q->where('organization_id', $organizationId))
                ->whereBetween('created_at', [$start, $end])
                ->sum('amount');

            $revenueTrend[] = [
                'month' => $start->format('Y-m'),
                'label' => $start->format('M'),
                'amount' => round((float) $amount, 2),
            ];
        }

        return $revenueTrend;
    }

    /**
     * @return list<array{month: string, label: string, count: int}>
     */
    private function activityTrend(Builder $query): array
    {
        $trend = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();

            $trend[] = [
                'month' => $start->format('Y-m'),
                'label' => $start->format('M'),
                'count' => (clone $query)->whereBetween('created_at', [$start, $end])->count(),
            ];
        }

        return $trend;
    }

    private function unreadMessagesCount(
        int $organizationId,
        User $user,
        bool $scopedToAssigned,
        $assignedMatterIds
    ): int {
        $query = MessageThread::query()
            ->where('organization_id', $organizationId)
            ->whereHas('messages', fn ($mq) => $mq
                ->where('sender_user_id', '!=', $user->id)
                ->whereNull('read_at'));

        if ($scopedToAssigned && $assignedMatterIds->isNotEmpty()) {
            $query->where(function ($q) use ($assignedMatterIds) {
                $q->whereIn('legal_matter_id', $assignedMatterIds)
                    ->orWhereNull('legal_matter_id');
            });
        } elseif ($scopedToAssigned) {
            return 0;
        }

        return $query->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function messagesPreview(
        int $organizationId,
        User $user,
        bool $scopedToAssigned,
        $assignedMatterIds
    ): array {
        $query = MessageThread::query()
            ->with([
                'client:id,name,email',
                'legalMatter:id,title,matter_number',
                'latestMessage.sender:id,name,client_id',
            ])
            ->where('organization_id', $organizationId);

        if ($scopedToAssigned && $assignedMatterIds->isNotEmpty()) {
            $query->where(function ($q) use ($assignedMatterIds) {
                $q->whereIn('legal_matter_id', $assignedMatterIds)
                    ->orWhereNull('legal_matter_id');
            });
        } elseif ($scopedToAssigned) {
            return [];
        }

        $threads = $query
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return MessageThreadResource::collection($threads)
            ->resolve();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pendingApprovals(int $organizationId, User $user): array
    {
        return ApprovalRequest::query()
            ->with(['submitter:id,name', 'reviewer:id,name'])
            ->where('organization_id', $organizationId)
            ->where('status', 'submitted')
            ->where(function ($q) use ($user) {
                $q->where('reviewer_id', $user->id)
                    ->orWhereNull('reviewer_id');
            })
            ->latest('submitted_at')
            ->limit(5)
            ->get()
            ->map(fn (ApprovalRequest $request) => [
                'id' => $request->id,
                'subject_type' => $request->subject_type,
                'subject_id' => $request->subject_id,
                'status' => $request->status,
                'submitted_at' => $request->submitted_at?->toIso8601String(),
                'submitter' => $request->submitter ? [
                    'id' => $request->submitter->id,
                    'name' => $request->submitter->name,
                ] : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function documentsNeedingAttention(int $organizationId, $assignedMatterIds): array
    {
        if ($assignedMatterIds->isEmpty()) {
            return [];
        }

        return LegalDocument::query()
            ->with('legalMatter:id,title,matter_number')
            ->where('organization_id', $organizationId)
            ->whereIn('legal_matter_id', $assignedMatterIds)
            ->where(function ($q) {
                $q->where(function ($sq) {
                    $sq->where('uploaded_by_client', true)
                        ->whereNull('portal_reviewed_at');
                })->orWhereIn('ai_review_status', ['generated', 'under_review']);
            })
            ->latest()
            ->limit(5)
            ->get(['id', 'name', 'legal_matter_id', 'document_type', 'created_at', 'ai_review_status', 'uploaded_by_client'])
            ->map(fn (LegalDocument $doc) => [
                'id' => $doc->id,
                'name' => $doc->name,
                'document_type' => $doc->document_type,
                'reason' => $doc->isPortalPendingReview() ? 'client_upload' : 'ai_review',
                'created_at' => $doc->created_at?->toIso8601String(),
                'case' => $doc->legalMatter ? [
                    'id' => $doc->legalMatter->id,
                    'title' => $doc->legalMatter->title,
                    'matter_number' => $doc->legalMatter->matter_number,
                ] : null,
            ])
            ->values()
            ->all();
    }
}
