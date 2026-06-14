<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\IntakeSubmission;
use App\Models\LegalMatter;
use App\Models\LegalTask;
use App\Models\Organization;
use App\Models\User;

class AutoTaskService
{
    public function onIntakeSubmitted(IntakeSubmission $submission, ?User $actor = null): ?LegalTask
    {
        $assigneeId = $this->resolveAssigneeId($submission->organization, $actor);
        if (! $assigneeId) {
            return null;
        }

        return LegalTask::query()->create([
            'organization_id' => $submission->organization_id,
            'legal_matter_id' => $submission->converted_legal_matter_id,
            'assignee_id' => $assigneeId,
            'created_by' => $actor?->id,
            'title' => 'Review intake submission',
            'description' => 'New intake submitted: '.($submission->submitter_name ?: 'Unknown submitter'),
            'status' => 'not_started',
            'priority' => 'high',
            'due_at' => now()->addDays(2),
        ]);
    }

    public function onCourtDateAdded(CalendarEvent $event, ?User $actor = null): ?LegalTask
    {
        $assigneeId = $event->user_id ?: $this->resolveAssigneeId($event->organization, $actor);
        if (! $assigneeId) {
            return null;
        }

        return LegalTask::query()->create([
            'organization_id' => $event->organization_id,
            'legal_matter_id' => $event->legal_matter_id,
            'assignee_id' => $assigneeId,
            'created_by' => $actor?->id,
            'title' => 'Prepare for '.$event->title,
            'description' => 'Court event scheduled for '.$event->starts_at?->format('M j, Y g:i A'),
            'status' => 'not_started',
            'priority' => 'high',
            'due_at' => $event->starts_at?->copy()->subDay(),
        ]);
    }

    public function onCaseStatusChanged(LegalMatter $matter, string $from, string $to, ?User $actor = null): ?LegalTask
    {
        if ($from === $to) {
            return null;
        }

        $assigneeId = $matter->lead_lawyer_id
            ?? $matter->assignedStaff()->value('users.id')
            ?? $this->resolveAssigneeId($matter->organization, $actor);

        if (! $assigneeId) {
            return null;
        }

        return LegalTask::query()->create([
            'organization_id' => $matter->organization_id,
            'legal_matter_id' => $matter->id,
            'assignee_id' => $assigneeId,
            'created_by' => $actor?->id,
            'title' => 'Follow up on status change',
            'description' => "Case status changed from {$from} to {$to}.",
            'status' => 'not_started',
            'priority' => 'normal',
            'due_at' => now()->addDays(3),
        ]);
    }

    protected function resolveAssigneeId(Organization $organization, ?User $actor = null): ?int
    {
        if ($actor?->id) {
            return $actor->id;
        }

        return User::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->value('id');
    }
}
