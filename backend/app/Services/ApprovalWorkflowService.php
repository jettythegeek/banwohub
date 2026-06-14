<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\Invoice;
use App\Models\LegalDocument;
use App\Models\Organization;
use App\Models\User;
use App\Support\InAppNotifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ApprovalWorkflowService
{
    public function __construct(private InAppNotifier $notifier)
    {
    }

    /**
     * @return array{subject_type: string, subject_id: int}
     */
    public function subjectKey(Model $subject): array
    {
        return match ($subject::class) {
            LegalDocument::class => ['subject_type' => 'legal_document', 'subject_id' => $subject->id],
            Invoice::class => ['subject_type' => 'invoice', 'subject_id' => $subject->id],
            default => throw ValidationException::withMessages(['subject' => 'Unsupported approval subject.']),
        };
    }

    public function subjectRequiresApproval(Model $subject): bool
    {
        return (bool) ($subject->requires_approval ?? false);
    }

    public function isSendBlocked(Model $subject): bool
    {
        if (! $this->subjectRequiresApproval($subject)) {
            return false;
        }

        $key = $this->subjectKey($subject);
        $latest = ApprovalRequest::latestForSubject($key['subject_type'], $key['subject_id']);

        if (! $latest) {
            return true;
        }

        return ! $latest->isApproved();
    }

    public function assertSendAllowed(Model $subject): void
    {
        if ($this->isSendBlocked($subject)) {
            throw ValidationException::withMessages([
                'approval' => 'Approval is required before sending or sharing this item.',
            ]);
        }
    }

    public function submit(
        Model $subject,
        User $submitter,
        ?int $reviewerId = null,
        ?string $notes = null,
        bool $requiresApproval = true,
    ): ApprovalRequest {
        $organization = $submitter->organization;
        abort_unless($organization, 422, 'User organization is required.');

        $key = $this->subjectKey($subject);
        abort_unless($subject->organization_id === $organization->id, 403);

        $latest = ApprovalRequest::latestForSubject($key['subject_type'], $key['subject_id']);
        if ($latest && in_array($latest->status, ['submitted'], true)) {
            throw ValidationException::withMessages([
                'status' => 'This item is already awaiting review.',
            ]);
        }

        if ($reviewerId) {
            $reviewer = User::query()
                ->where('organization_id', $organization->id)
                ->where('id', $reviewerId)
                ->where('is_active', true)
                ->first();
            abort_unless($reviewer && $reviewer->can('approvals.review'), 422, 'Selected reviewer cannot approve items.');
        }

        $subject->update(['requires_approval' => $requiresApproval]);

        $request = ApprovalRequest::query()->create([
            'organization_id' => $organization->id,
            'subject_type' => $key['subject_type'],
            'subject_id' => $key['subject_id'],
            'status' => 'submitted',
            'requires_approval' => $requiresApproval,
            'submitted_by' => $submitter->id,
            'reviewer_id' => $reviewerId,
            'notes' => $notes,
            'submitted_at' => now(),
        ]);

        $this->notifySubmitted($request, $submitter, $organization);

        activity('approval')
            ->performedOn($request)
            ->causedBy($submitter)
            ->withProperties([
                'subject_type' => $key['subject_type'],
                'subject_id' => $key['subject_id'],
                'status' => 'submitted',
            ])
            ->log('Approval submitted');

        return $request->load(['submitter', 'reviewer']);
    }

    public function review(
        ApprovalRequest $request,
        User $reviewer,
        string $action,
        ?string $comment = null,
    ): ApprovalRequest {
        abort_unless($reviewer->can('approvals.review'), 403);
        abort_unless($request->organization_id === $reviewer->organization_id, 403);

        $newStatus = match ($action) {
            'approve' => 'approved',
            'reject' => 'rejected',
            'request_changes' => 'changes_requested',
            default => throw ValidationException::withMessages(['action' => 'Invalid review action.']),
        };

        abort_unless(
            $request->canTransitionTo($newStatus),
            422,
            'Invalid approval status transition.',
        );

        if ($request->reviewer_id && $request->reviewer_id !== $reviewer->id) {
            abort_unless(
                $reviewer->hasAnyRole(['Firm Admin', 'Partner']),
                403,
                'Only the assigned reviewer or a partner may decide this request.',
            );
        }

        $comments = $request->comments ?? [];
        if ($comment) {
            $comments[] = [
                'user_id' => $reviewer->id,
                'user_name' => $reviewer->name,
                'body' => $comment,
                'action' => $action,
                'created_at' => now()->toIso8601String(),
            ];
        }

        $request->update([
            'status' => $newStatus,
            'reviewer_id' => $reviewer->id,
            'comments' => $comments,
            'reviewed_at' => now(),
        ]);

        $this->notifyReviewed($request, $reviewer, $newStatus);

        activity('approval')
            ->performedOn($request)
            ->causedBy($reviewer)
            ->withProperties([
                'subject_type' => $request->subject_type,
                'subject_id' => $request->subject_id,
                'status' => $newStatus,
            ])
            ->log('Approval decision recorded');

        return $request->fresh()->load(['submitter', 'reviewer']);
    }

    public function markFinalized(Model $subject): void
    {
        $key = $this->subjectKey($subject);
        $latest = ApprovalRequest::latestForSubject($key['subject_type'], $key['subject_id']);

        if ($latest && $latest->status === 'approved') {
            $latest->update(['status' => 'finalized']);
        }
    }

    protected function notifySubmitted(ApprovalRequest $request, User $submitter, Organization $organization): void
    {
        $label = $this->subjectLabel($request);
        $data = $this->notificationData($request);

        if ($request->reviewer_id) {
            $reviewer = User::query()->find($request->reviewer_id);
            if ($reviewer) {
                $this->notifier->notifyUser(
                    $reviewer,
                    'approval_request',
                    'Approval requested',
                    "{$submitter->name} submitted {$label} for your review.",
                    $data,
                    $submitter,
                );

                return;
            }
        }

        $this->notifier->notifyPermission(
            $organization,
            'approvals.review',
            'approval_request',
            'Approval requested',
            "{$submitter->name} submitted {$label} for review.",
            $data,
            $submitter,
        );
    }

    protected function notifyReviewed(ApprovalRequest $request, User $reviewer, string $status): void
    {
        $submitter = $request->submitter;
        if (! $submitter) {
            return;
        }

        $label = $this->subjectLabel($request);
        $data = $this->notificationData($request);

        $type = match ($status) {
            'approved' => 'approval_completed',
            'rejected' => 'approval_rejected',
            'changes_requested' => 'approval_changes_requested',
            default => 'approval_completed',
        };

        $title = match ($status) {
            'approved' => 'Approval completed',
            'rejected' => 'Approval rejected',
            'changes_requested' => 'Changes requested',
            default => 'Approval updated',
        };

        $body = match ($status) {
            'approved' => "{$reviewer->name} approved {$label}.",
            'rejected' => "{$reviewer->name} rejected {$label}.",
            'changes_requested' => "{$reviewer->name} requested changes on {$label}.",
            default => "{$reviewer->name} updated {$label}.",
        };

        $this->notifier->notifyUser($submitter, $type, $title, $body, $data, $reviewer);
    }

    protected function subjectLabel(ApprovalRequest $request): string
    {
        $subject = $request->resolveSubject();

        return match ($request->subject_type) {
            'legal_document' => $subject instanceof LegalDocument ? "document \"{$subject->name}\"" : 'a document',
            'invoice' => $subject instanceof Invoice ? "invoice {$subject->invoice_number}" : 'an invoice',
            default => 'an item',
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function notificationData(ApprovalRequest $request): array
    {
        $subject = $request->resolveSubject();
        $data = [
            'approval_request_id' => $request->id,
            'subject_type' => $request->subject_type,
            'subject_id' => $request->subject_id,
            'status' => $request->status,
        ];

        if ($subject instanceof LegalDocument) {
            $data['legal_matter_id'] = $subject->legal_matter_id;
            $data['document_id'] = $subject->id;
        }

        if ($subject instanceof Invoice) {
            $data['legal_matter_id'] = $subject->legal_matter_id;
            $data['invoice_id'] = $subject->id;
        }

        return $data;
    }
}
