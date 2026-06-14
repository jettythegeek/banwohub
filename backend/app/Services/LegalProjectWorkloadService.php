<?php

namespace App\Services;

use App\Models\LegalMatter;
use App\Models\LegalProjectMilestone;
use App\Models\LegalTask;
use App\Models\User;

class LegalProjectWorkloadService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(int $organizationId): array
    {
        $closedStatuses = ['closed', 'archived', 'settled'];

        $openMatters = LegalMatter::query()
            ->where('organization_id', $organizationId)
            ->whereNotIn('status', $closedStatuses)
            ->get(['id', 'title', 'status', 'lead_lawyer_id']);

        $openTasks = LegalTask::query()
            ->where('organization_id', $organizationId)
            ->whereNotIn('status', ['completed'])
            ->get(['id', 'title', 'status', 'assignee_id', 'due_at', 'legal_matter_id']);

        $overdueTasks = $openTasks->filter(function (LegalTask $task): bool {
            return $task->due_at !== null
                && $task->due_at->isPast()
                && ! in_array($task->status, ['completed'], true);
        });

        $lawyerIds = $openMatters->pluck('lead_lawyer_id')
            ->merge($openTasks->pluck('assignee_id'))
            ->filter()
            ->unique()
            ->values();

        $lawyers = User::query()
            ->whereIn('id', $lawyerIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        $byLawyer = [];

        foreach ($lawyerIds as $lawyerId) {
            $lawyerOpenMatters = $openMatters->where('lead_lawyer_id', $lawyerId);
            $lawyerOpenTasks = $openTasks->where('assignee_id', $lawyerId);
            $lawyerOverdue = $overdueTasks->where('assignee_id', $lawyerId);

            $byLawyer[] = [
                'user_id' => (int) $lawyerId,
                'name' => $lawyers->get($lawyerId)?->name ?? 'Unknown',
                'open_matters' => $lawyerOpenMatters->count(),
                'open_tasks' => $lawyerOpenTasks->count(),
                'overdue_tasks' => $lawyerOverdue->count(),
                'matter_titles' => $lawyerOpenMatters->pluck('title')->take(5)->values()->all(),
            ];
        }

        usort($byLawyer, fn (array $a, array $b) => ($b['open_tasks'] + $b['open_matters']) <=> ($a['open_tasks'] + $a['open_matters']));

        $pendingMilestones = LegalProjectMilestone::query()
            ->where('organization_id', $organizationId)
            ->whereIn('status', ['pending', 'in_progress', 'overdue'])
            ->count();

        return [
            'totals' => [
                'open_matters' => $openMatters->count(),
                'open_tasks' => $openTasks->count(),
                'overdue_tasks' => $overdueTasks->count(),
                'pending_milestones' => $pendingMilestones,
            ],
            'by_lawyer' => array_values($byLawyer),
        ];
    }
}
