<?php

namespace App\Services;

use App\Models\LegalMatter;
use App\Models\LegalProjectMilestone;
use App\Models\LegalTask;
use Illuminate\Support\Carbon;

class LegalAnalyticsService
{
    public const DISCLAIMER = 'Analytics and predictive hints are informational only. They do not constitute legal advice. Verify all insights against source data before making strategic decisions.';

    /**
     * @return array<string, mixed>
     */
    public function dashboard(int $organizationId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $closedStatuses = ['closed', 'archived', 'settled'];

        $mattersQuery = LegalMatter::query()->where('organization_id', $organizationId);
        $this->applyDateRange($mattersQuery, 'created_at', $fromDate, $toDate);
        $matters = (clone $mattersQuery)->get(['id', 'status', 'case_type', 'opened_at', 'created_at', 'updated_at', 'lead_lawyer_id']);

        $durations = $matters->map(function (LegalMatter $matter) use ($closedStatuses): ?int {
            $start = $matter->opened_at ?? $matter->created_at;
            if ($start === null) {
                return null;
            }
            $end = in_array($matter->status, $closedStatuses, true)
                ? ($matter->updated_at ?? now())
                : now();

            return (int) Carbon::parse($start)->diffInDays(Carbon::parse($end));
        })->filter(fn (?int $days) => $days !== null);

        $avgDuration = $durations->isEmpty() ? 0 : round($durations->avg(), 1);

        $byStatus = $matters->groupBy('status')->map(fn ($group, $status) => [
            'status' => $status,
            'count' => $group->count(),
        ])->values()->sortByDesc('count')->values()->all();

        $byCaseType = $matters->groupBy(fn (LegalMatter $m) => $m->case_type ?: 'unspecified')
            ->map(fn ($group, $type) => [
                'case_type' => $type,
                'count' => $group->count(),
                'closed_count' => $group->whereIn('status', $closedStatuses)->count(),
            ])->values()->sortByDesc('count')->values()->all();

        $openTasks = LegalTask::query()
            ->where('organization_id', $organizationId)
            ->whereNotIn('status', ['completed'])
            ->count();

        $overdueTasks = LegalTask::query()
            ->where('organization_id', $organizationId)
            ->whereNotIn('status', ['completed'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        $pendingMilestones = LegalProjectMilestone::query()
            ->where('organization_id', $organizationId)
            ->whereIn('status', ['pending', 'in_progress', 'overdue'])
            ->count();

        return [
            'disclaimer' => self::DISCLAIMER,
            'filters' => ['from_date' => $fromDate, 'to_date' => $toDate],
            'case_duration' => [
                'average_days' => $avgDuration,
                'sample_size' => $durations->count(),
                'median_days' => $this->median($durations->values()->all()),
            ],
            'outcomes' => [
                'total_matters' => $matters->count(),
                'open_count' => $matters->whereNotIn('status', $closedStatuses)->count(),
                'closed_count' => $matters->whereIn('status', $closedStatuses)->count(),
                'by_status' => $byStatus,
            ],
            'case_type_performance' => $byCaseType,
            'workload' => [
                'open_tasks' => $openTasks,
                'overdue_tasks' => $overdueTasks,
                'pending_milestones' => $pendingMilestones,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function hints(int $organizationId): array
    {
        $dashboard = $this->dashboard($organizationId);
        $hints = [];

        if ($dashboard['workload']['overdue_tasks'] > 0) {
            $hints[] = [
                'type' => 'workload_risk',
                'severity' => 'high',
                'title' => 'Overdue task backlog',
                'message' => sprintf(
                    '%d open tasks are past due. Consider reassigning or reprioritizing workload.',
                    $dashboard['workload']['overdue_tasks']
                ),
            ];
        }

        if ($dashboard['outcomes']['open_count'] > 5 && $dashboard['case_duration']['average_days'] > 180) {
            $hints[] = [
                'type' => 'timeline',
                'severity' => 'medium',
                'title' => 'Extended matter duration',
                'message' => 'Open matters are averaging over six months. Review milestones and settlement opportunities on long-running files.',
            ];
        }

        $mattersWithoutMilestones = LegalMatter::query()
            ->where('organization_id', $organizationId)
            ->whereNotIn('status', ['closed', 'archived', 'settled'])
            ->whereDoesntHave('milestones')
            ->count();

        if ($mattersWithoutMilestones > 0) {
            $hints[] = [
                'type' => 'project_planning',
                'severity' => 'low',
                'title' => 'Missing project milestones',
                'message' => sprintf(
                    '%d open matters have no milestones defined. Add milestones to improve visibility and deadline compliance.',
                    $mattersWithoutMilestones
                ),
            ];
        }

        if ($hints === []) {
            $hints[] = [
                'type' => 'general',
                'severity' => 'info',
                'title' => 'No immediate alerts',
                'message' => 'Current firm metrics are within normal ranges based on available data.',
            ];
        }

        return [
            'disclaimer' => self::DISCLAIMER,
            'requires_review' => true,
            'hints' => $hints,
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<LegalMatter>  $query
     */
    protected function applyDateRange($query, string $column, ?string $fromDate, ?string $toDate): void
    {
        if ($fromDate !== null) {
            $query->whereDate($column, '>=', $fromDate);
        }
        if ($toDate !== null) {
            $query->whereDate($column, '<=', $toDate);
        }
    }

    /**
     * @param  list<int>  $values
     */
    protected function median(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }
        sort($values);
        $count = count($values);
        $mid = (int) floor($count / 2);

        if ($count % 2 === 0) {
            return round(($values[$mid - 1] + $values[$mid]) / 2, 1);
        }

        return (float) $values[$mid];
    }
}
