<?php

namespace App\Services;

use App\Models\LegalTask;
use App\Models\User;

class TaskWorkloadService
{
    /**
     * @return array<string, mixed>
     */
    public function board(int $organizationId): array
    {
        $openStatuses = array_values(array_filter(
            LegalTask::STATUSES,
            fn (string $status) => $status !== 'completed',
        ));

        $tasks = LegalTask::query()
            ->with(['legalMatter:id,title,matter_number', 'assignee:id,name'])
            ->where('organization_id', $organizationId)
            ->whereIn('status', $openStatuses)
            ->orderByRaw('due_at is null, due_at asc')
            ->get();

        $board = array_fill_keys($openStatuses, []);

        foreach ($tasks as $task) {
            $status = in_array($task->status, $openStatuses, true) ? $task->status : 'not_started';
            $board[$status][] = $this->taskPayload($task);
        }

        $assigneeIds = $tasks->pluck('assignee_id')->filter()->unique()->values();
        $assignees = User::query()
            ->whereIn('id', $assigneeIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        $byAssignee = [];

        foreach ($assigneeIds as $assigneeId) {
            $assigneeTasks = $tasks->where('assignee_id', $assigneeId);
            $overdue = $assigneeTasks->filter(fn (LegalTask $task) => $this->isOverdue($task));

            $byAssignee[] = [
                'user_id' => (int) $assigneeId,
                'name' => $assignees->get($assigneeId)?->name ?? 'Unassigned',
                'open_tasks' => $assigneeTasks->count(),
                'overdue_tasks' => $overdue->count(),
                'tasks' => $assigneeTasks->map(fn (LegalTask $task) => $this->taskPayload($task))->values()->all(),
            ];
        }

        usort($byAssignee, fn (array $a, array $b) => $b['open_tasks'] <=> $a['open_tasks']);

        return [
            'totals' => [
                'open_tasks' => $tasks->count(),
                'overdue_tasks' => $tasks->filter(fn (LegalTask $task) => $this->isOverdue($task))->count(),
            ],
            'board' => $board,
            'by_assignee' => array_values($byAssignee),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function taskPayload(LegalTask $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'status' => $task->status,
            'priority' => $task->priority,
            'due_at' => $task->due_at?->toIso8601String(),
            'is_overdue' => $this->isOverdue($task),
            'assignee' => $task->assignee ? [
                'id' => $task->assignee->id,
                'name' => $task->assignee->name,
            ] : null,
            'case' => $task->legalMatter ? [
                'id' => $task->legalMatter->id,
                'title' => $task->legalMatter->title,
                'matter_number' => $task->legalMatter->matter_number,
            ] : null,
        ];
    }

    protected function isOverdue(LegalTask $task): bool
    {
        return $task->due_at !== null
            && $task->due_at->isPast()
            && $task->status !== 'completed';
    }
}
